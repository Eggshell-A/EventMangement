#!/usr/bin/env python3
"""Linux/WSL HTTP/MySQL tests. Creates and drops only a random, isolated test database."""
import argparse
import concurrent.futures
import hashlib
import http.cookiejar
import json
import os
from pathlib import Path
import re
import shutil
import signal
import socket
import subprocess
import tempfile
import threading
import time
import urllib.error
import urllib.parse
import urllib.request
from html.parser import HTMLParser

ROOT = Path(__file__).resolve().parents[1]
PHP = os.environ.get("PHP_BIN", "php")
HOST = os.environ.get("EVENTLINK_TEST_DB_HOST", "127.0.0.1")
PORT = int(os.environ.get("EVENTLINK_TEST_DB_PORT", "3306"))
USER = os.environ.get("EVENTLINK_TEST_DB_USER", "root")
PASSWORD = os.environ.get("EVENTLINK_TEST_DB_PASS", "")
DB = "eventlink_security_" + os.urandom(6).hex()
RESULTS = []
ENV = os.environ.copy()
ENV.update(TEST_DSN=f"mysql:host={HOST};port={PORT};charset=utf8mb4", TEST_USER=USER, TEST_PASS=PASSWORD)
BRIDGE = r"""
$c=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
$p=new PDO(getenv('TEST_DSN').($c['database']?';dbname='.$c['database']:''),getenv('TEST_USER'),getenv('TEST_PASS'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
if($c['exec']) {$p->exec($c['sql']);echo 'null';}
else {$s=$p->prepare($c['sql']);$s->execute($c['params']);echo json_encode($s->fetchAll());}
"""

def php(code, env=None):
    result = subprocess.run([PHP, "-r", code], capture_output=True, text=True, env=env or ENV, timeout=30)
    if result.returncode:
        raise RuntimeError(result.stderr or result.stdout)
    return result.stdout.strip()

def sql(query, params=(), database=DB, execute=False):
    result = subprocess.run([PHP, "-r", BRIDGE], input=json.dumps(
        {"sql": query, "params": list(params), "database": database, "exec": execute}),
        capture_output=True, text=True, env=ENV, timeout=30)
    if result.returncode:
        raise RuntimeError(result.stderr or result.stdout)
    return json.loads(result.stdout)

def scalar(query, params=()):
    return next(iter(sql(query, params)[0].values()))

def check(name, condition):
    if not condition:
        RESULTS.append({"test": name, "status": "FAIL"})
        raise AssertionError(name)
    RESULTS.append({"test": name, "status": "PASS"})
    print("PASS", name, flush=True)

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None

class Client:
    def __init__(self, base):
        self.base = base
        self.jar = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.ProxyHandler({}),
            urllib.request.HTTPCookieProcessor(self.jar), NoRedirect())
        self.token = ""

    def request(self, path, data=None, headers=None, method=None):
        encoded = None if data is None else urllib.parse.urlencode(data).encode()
        req = urllib.request.Request(self.base + "/" + path, data=encoded, headers=headers or {}, method=method)
        try:
            response = self.opener.open(req, timeout=30)
        except urllib.error.HTTPError as error:
            response = error
        return response.code, response.headers, response.read().decode("utf-8")

    def get_token(self, path="login.php"):
        status, headers, body = self.request(path)
        assert status == 200, (path, status, body)
        match = re.search(r'name="csrf_token" value="([a-f0-9]{64})"', body)
        assert match, path
        self.token = match[1]
        return status, headers, body

    def post(self, path, data, headers=None):
        return self.request(path, {"csrf_token": self.token, **data}, headers)

    def login(self, email, password="TestPass1!"):
        self.get_token()
        status, headers, body = self.post("login.php", {"email": email, "password": password})
        assert status == 302, (email, status, body)
        self.get_token("index.php")
        return headers["Location"]

    def sid(self):
        return next(c.value for c in self.jar if c.name == "PHPSESSID")

class FormParser(HTMLParser):
    def __init__(self):
        super().__init__()
        self.forms = []
        self.current = None
    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag == "form" and attrs.get("method", "").lower() == "post":
            self.current = []
        elif tag == "input" and self.current is not None and attrs.get("name") == "csrf_token":
            self.current.append(attrs.get("value", ""))
    def handle_endtag(self, tag):
        if tag == "form" and self.current is not None:
            self.forms.append(self.current)
            self.current = None

def main():
    if os.name != 'posix':
        raise SystemExit('Run this multi-worker test suite in Linux or WSL.')
    parser = argparse.ArgumentParser()
    parser.add_argument("--output", help="Optional JSON test result file")
    args = parser.parse_args()
    server = None
    created = False
    log = None
    with tempfile.TemporaryDirectory(prefix="eventlink-test-") as temporary:
        temp = Path(temporary)
        app = temp / "app"
        shutil.copytree(ROOT, app, ignore=shutil.ignore_patterns(".git", "config.php", "demo", "__pycache__"))
        sessions = temp / "sessions"
        sessions.mkdir()
        config = {"db_host": HOST, "db_port": PORT, "db_name": DB, "db_user": USER,
                  "db_pass": PASSWORD, "base_url": "http://localhost", "session_secure": False}
        def write_config():
            (app / "config.php").write_text("<?php return json_decode(" +
                "'" + json.dumps(config).replace("\\", "\\\\").replace("'", "\\'") + "',true);")
        try:
            sql(f"CREATE DATABASE {DB} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci", database="", execute=True)
            created = True
            schema = re.sub(r"^(?:CREATE DATABASE[^\n]*|USE [^\n]*)\n", "", (ROOT / "database.sql").read_text(), flags=re.M)
            sql(schema, execute=True)
            sql("DROP TABLE auth_rate_limits", execute=True)
            migration = (ROOT / "migrations/001_security_hardening.sql").read_text()
            sql(migration, execute=True)
            sql(migration, execute=True)
            check("fresh schema and repeatable existing-database migration", scalar("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=?", [DB]) == 8)
            password_hash = php("echo password_hash('TestPass1!',PASSWORD_DEFAULT);")
            people = [("Owner", "owner@example.test", "creator"),
                      ("Other", "other@example.test", "creator"),
                      ("Admin", "admin@example.test", "admin"),
                      ("Assigned staff", "staff@example.test", "staff"),
                      ("Other staff", "otherstaff@example.test", "staff")]
            people += [(f"Attendee {n}", f"attendee{n}@example.test", "user") for n in range(12)]
            for name, email, role in people:
                sql("INSERT INTO users(name,email,password_hash,role) VALUES(?,?,?,?)", [name, email, password_hash, role])
            ids = {row["email"]: int(row["user_id"]) for row in sql("SELECT user_id,email FROM users")}
            def event(capacity=1, status="approved", title="Regression event"):
                sql("INSERT INTO events(organiser_id,title,description,start_time,location,capacity,status) VALUES(?,?,?,'2030-01-01 12:00:00','Test venue',?,?)",
                    [ids["owner@example.test"], title, "Test description", capacity, status])
                return int(scalar("SELECT MAX(event_id) FROM events"))
            sql("INSERT INTO staff_assignments(staff_user_id,creator_user_id) VALUES(?,?)", [ids["staff@example.test"], ids["owner@example.test"]])
            sql("UPDATE users SET is_school_email=1 WHERE email='other@example.test'")
            sql("INSERT INTO creator_plans(user_id,organisation_name,plan_code) VALUES(?,'Other group','external_monthly')", [ids["other@example.test"]])
            event_id = event()
            pending_id = event(status="pending")
            with socket.socket() as s:
                s.bind(("127.0.0.1", 0))
                web_port = s.getsockname()[1]
            base = f"http://127.0.0.1:{web_port}"
            config["base_url"] = base
            write_config()
            log = open(temp / "php-server.log", "w+")
            server_env = ENV.copy()
            server_env["PHP_CLI_SERVER_WORKERS"] = "8"
            server = subprocess.Popen([PHP, "-d", f"session.save_path={sessions}", "-S", f"127.0.0.1:{web_port}", "-t", str(app)],
                stdout=log, stderr=log, env=server_env, start_new_session=True)
            for _ in range(100):
                try:
                    if Client(base).request("login.php")[0] == 200: break
                except (OSError, urllib.error.URLError): pass
                time.sleep(.05)
            else: raise RuntimeError("PHP server did not start")
            anonymous = Client(base)
            status, headers, body = anonymous.get_token()
            cookie = headers.get("Set-Cookie", "").lower()
            check("HTTP local cookie has HttpOnly and SameSite=Lax", "httponly" in cookie and "samesite=lax" in cookie and "secure" not in cookie)
            check("CSP, clickjacking, MIME and cache headers", "script-src 'self'" in headers["Content-Security-Policy"] and "unsafe-inline" not in headers["Content-Security-Policy"] and headers["X-Frame-Options"] == "DENY" and headers["X-Content-Type-Options"] == "nosniff" and "no-store" in headers["Cache-Control"])
            endpoints = ["login.php", "register.php", "event_form.php", "booking_action.php",
                         "cancel_booking.php", "organiser_action.php", "admin_action.php", "checkin.php", "logout.php"]
            for path in endpoints:
                check(f"{path}: missing CSRF rejected", anonymous.request(path, {})[0] == 403)
                check(f"{path}: wrong CSRF rejected", anonymous.request(path, {"csrf_token": "bad"})[0] == 403)
            check("array-valued CSRF rejected", anonymous.request("login.php", {"csrf_token[]": "bad"})[0] == 403)
            foreign = Client(base)
            foreign.get_token()
            check("another session's CSRF rejected", foreign.request("register.php", {"csrf_token": anonymous.token})[0] == 403)
            supplied = Client(base)
            _, fixed_headers, _ = supplied.request("login.php", headers={"Cookie": "PHPSESSID=" + "a"*26})
            check("strict session mode rejects supplied session ID", "a"*26 not in fixed_headers.get("Set-Cookie", ""))
            initial_sid, old_token = anonymous.sid(), anonymous.token
            anonymous.login("attendee0@example.test")
            check("login rotates session ID and CSRF token", anonymous.sid() != initial_sid and anonymous.token != old_token)
            check("pre-login token no longer authorises a POST", anonymous.request("booking_action.php", {"csrf_token": old_token, "event_id": event_id, "action": "book"})[0] == 403)
            old_client = Client(base)
            check("old pre-login session cannot access bookings", old_client.request("my_bookings.php", headers={"Cookie": f"PHPSESSID={initial_sid}"})[0] == 302)
            owner, other, admin = [Client(base) for _ in range(3)]
            check("creator login keeps the dashboard redirect", owner.login("owner@example.test") == "dashboard.php")
            other.login("other@example.test")
            check("admin login keeps the admin redirect", admin.login("admin@example.test") == "admin.php")
            for client, path in [(anonymous,"my_bookings.php"),(owner,"dashboard.php"),(owner,"event_form.php"),
                                 (owner,f"attendees.php?event_id={event_id}"),(admin,"admin.php"),(anonymous,f"event.php?id={event_id}")]:
                _, _, html = client.get_token(path)
                forms = FormParser()
                forms.feed(html)
                check(f"{path}: all rendered POST forms contain one token", bool(forms.forms) and all(len(f)==1 and len(f[0])==64 for f in forms.forms))
            check("foreign organiser cannot read attendee list", other.request(f"attendees.php?event_id={event_id}")[0] == 403)
            check("attendee cannot access admin page", anonymous.request("admin.php")[0] == 403)
            check("attendee cannot approve event by direct POST", anonymous.post("admin_action.php", {"event_id": pending_id, "action": "approve"})[0] == 403)
            check("organiser cannot cancel another organiser's event", other.post("organiser_action.php", {"event_id": event_id, "action": "cancel"})[0] == 403)
            edit_data = {"event_id": event_id, "title":"Edited", "description":"Changed", "start_time":"2030-01-01T12:00", "location":"Test", "capacity":1}
            check("organiser cannot edit another organiser's event", other.post("event_form.php", edit_data)[0] == 403)
            check("organiser cannot add places to another organiser's event", other.post("organiser_action.php", {"event_id":event_id,"add_places":2})[0] == 403)
            check("failed ownership requests preserve event", scalar("SELECT title FROM events WHERE event_id=?", [event_id]) == "Regression event" and scalar("SELECT capacity FROM events WHERE event_id=?", [event_id]) == 1 and scalar("SELECT status FROM events WHERE event_id=?", [event_id]) == "approved")
            check("valid admin approval works", admin.post("admin_action.php", {"event_id":pending_id,"action":"approve"})[0] == 302 and scalar("SELECT status FROM events WHERE event_id=?", [pending_id]) == "approved")
            staff, otherstaff = Client(base), Client(base)
            check("staff login keeps the staff redirect", staff.login("staff@example.test") == "staff.php")
            otherstaff.login("otherstaff@example.test")
            staff_event_id = event(3)
            staff_edit = {**edit_data, "event_id":staff_event_id,"title":"Staff edit","capacity":3}
            _, _, staff_form = staff.get_token(f"event_form.php?id={staff_event_id}")
            parsed = FormParser()
            parsed.feed(staff_form)
            check("assigned staff edit form includes CSRF tokens", bool(parsed.forms) and all(len(f)==1 for f in parsed.forms))
            status, staff_headers, _ = staff.post("event_form.php", staff_edit)
            check("assigned staff can edit and return to staff page", status == 302 and staff_headers["Location"] == "staff.php" and scalar("SELECT title FROM events WHERE event_id=?", [staff_event_id]) == "Staff edit")
            check("unassigned staff cannot read or edit an event", otherstaff.request(f"event_form.php?id={staff_event_id}")[0] == 403 and otherstaff.post("event_form.php", staff_edit)[0] == 403)
            check("staff cannot create events by GET or direct POST", staff.request("event_form.php")[0] == 403 and staff.post("event_form.php", {**staff_edit,"event_id":0})[0] == 403)
            sql("DELETE FROM staff_assignments WHERE staff_user_id=?", [ids["staff@example.test"]])
            check("revoked staff assignment takes effect without logout", staff.post("event_form.php", staff_edit)[0] == 403)
            check("staff cannot access creator, booking or admin actions", staff.request("dashboard.php")[0] == 403 and staff.post("booking_action.php", {"event_id":event_id,"action":"book"})[0] == 403 and staff.post("admin_action.php", {"action":"verify_school","user_id":ids["other@example.test"]})[0] == 403)
            check("user cannot verify school email by direct POST", anonymous.post("admin_action.php", {"action":"verify_school","user_id":ids["other@example.test"]})[0] == 403)
            check("admin verification rejects array identifiers", admin.post("admin_action.php", {"action":"verify_school","user_id[]":ids["other@example.test"]})[0] == 400)
            check("school verification and creator plan update still work", admin.post("admin_action.php", {"action":"verify_school","user_id":ids["other@example.test"]})[0] == 302 and scalar("SELECT school_email_verified FROM users WHERE email='other@example.test'") == 1 and scalar("SELECT plan_code FROM creator_plans WHERE user_id=?", [ids["other@example.test"]]) == "verified_society")
            sql("UPDATE users SET is_school_email=1 WHERE email='owner@example.test'")
            sql("INSERT INTO creator_plans(user_id,organisation_name,plan_code) VALUES(?,'Owner group','external_monthly')", [ids["owner@example.test"]])
            sql("CREATE TRIGGER security_plan_failure BEFORE UPDATE ON creator_plans FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='secret_plan_error'", execute=True)
            status, _, body = admin.post("admin_action.php", {"action":"verify_school","user_id":ids["owner@example.test"]})
            check("failed school verification rolls back user and plan changes", status == 503 and "secret_plan_error" not in body and scalar("SELECT school_email_verified FROM users WHERE email='owner@example.test'") == 0 and scalar("SELECT plan_code FROM creator_plans WHERE user_id=?", [ids["owner@example.test"]]) == "external_monthly")
            sql("DROP TRIGGER security_plan_failure", execute=True)
            next_user = Client(base)
            check("array-valued login destination does not crash", next_user.request("login.php?next[]=bad")[0] == 200)
            next_user.get_token()
            status, next_headers, _ = next_user.post("login.php", {"email":"attendee0@example.test","password":"TestPass1!","next":f"event.php?id={event_id}"})
            check("user login returns to the selected event", status == 302 and next_headers["Location"] == f"event.php?id={event_id}")
            check("external login destination cannot redirect off-site", next_user.request("login.php?next=https://example.test")[1]["Location"] == "index.php")
            check("array-valued identifier rejected", anonymous.post("booking_action.php", {"event_id[]":event_id,"action":"book"})[0] == 400)
            check("SQL-shaped event identifier rejected", anonymous.post("booking_action.php", {"event_id":"1 OR 1=1","action":"book"})[0] == 400)
            check("valid booking still works", anonymous.post("booking_action.php", {"event_id":event_id,"action":"book"})[0] == 302 and scalar("SELECT COUNT(*) FROM bookings WHERE event_id=?", [event_id]) == 1)
            booking_id = int(scalar("SELECT booking_id FROM bookings WHERE event_id=?", [event_id]))
            attendee1 = Client(base)
            attendee1.login("attendee1@example.test")
            check("attendee cannot cancel another user's booking", attendee1.post("cancel_booking.php", {"booking_id":booking_id})[0] == 403)
            check("foreign organiser cannot check in booking", other.post("checkin.php", {"booking_id":booking_id,"event_id":event_id})[0] == 403)
            check("owner check-in works", owner.post("checkin.php", {"booking_id":booking_id,"event_id":event_id})[0] == 302 and scalar("SELECT checked_in FROM bookings WHERE booking_id=?", [booking_id]) == 1)
            anonymous.post("booking_action.php", {"event_id":event_id,"action":"wait"})
            check("confirmed attendee cannot also join waitlist", scalar("SELECT COUNT(*) FROM waiting_list WHERE user_id=?", [ids["attendee0@example.test"]]) == 0)
            check("waitlist works when full", attendee1.post("booking_action.php", {"event_id":event_id,"action":"wait"})[0] == 302 and scalar("SELECT COUNT(*) FROM waiting_list WHERE event_id=?", [event_id]) == 1)
            anonymous.post("cancel_booking.php", {"booking_id":booking_id})
            _, _, cancel_body = anonymous.get_token("my_bookings.php")
            check("cancellation preserves queue privacy", "Attendee 1" not in cancel_body)
            check("cancellation automatically promotes the first waiter", scalar("SELECT user_id FROM bookings WHERE event_id=?", [event_id]) == ids["attendee1@example.test"] and scalar("SELECT COUNT(*) FROM waiting_list WHERE event_id=?", [event_id]) == 0)
            _, _, promoted = attendee1.get_token(f"event.php?id={event_id}")
            check("promoted waiter sees the current booking confirmation", "View your booking" in promoted)
            claim_id = event(1)
            anonymous.post("booking_action.php", {"event_id":claim_id,"action":"book"})
            attendee1.post("booking_action.php", {"event_id":claim_id,"action":"wait"})
            owner.post("organiser_action.php", {"event_id":claim_id,"add_places":1})
            _, _, reopened = attendee1.get_token(f"event.php?id={claim_id}")
            check("waitlisted user can claim a place after capacity increases", 'name="action" value="book"' in reopened)
            attendee1.post("booking_action.php", {"event_id":claim_id,"action":"book"})
            check("claim removes stale waitlist membership atomically", scalar("SELECT COUNT(*) FROM bookings WHERE event_id=?", [claim_id]) == 2 and scalar("SELECT COUNT(*) FROM waiting_list WHERE event_id=?", [claim_id]) == 0)
            check("owner can add places", owner.post("organiser_action.php", {"event_id":event_id,"add_places":1})[0] == 302 and scalar("SELECT capacity FROM events WHERE event_id=?", [event_id]) == 2)
            edit_data["capacity"] = 0
            check("invalid capacity rejected without update", owner.post("event_form.php", edit_data)[0] == 200 and scalar("SELECT capacity FROM events WHERE event_id=?", [event_id]) == 2)
            owner.post("organiser_action.php", {"event_id":event_id,"action":"cancel"})
            check("removed event cannot be booked", anonymous.post("booking_action.php", {"event_id":event_id,"action":"book"})[0] == 302 and scalar("SELECT COUNT(*) FROM bookings WHERE event_id=?", [event_id]) == 1)
            # A held parent row ensures the competing requests arrive before any seat commits.
            racers = [Client(base) for _ in range(8)]
            for n, client in enumerate(racers): client.login(f"attendee{n+2}@example.test")
            race_id = event(1)
            gate_code = r"""$p=new PDO(getenv('TEST_DSN').';dbname='.getenv('TEST_DB'),getenv('TEST_USER'),getenv('TEST_PASS'));$p->beginTransaction();$s=$p->prepare('SELECT event_id FROM events WHERE event_id=? FOR UPDATE');$s->execute([(int)getenv('TEST_EVENT')]);echo "locked\n";flush();fgets(STDIN);$p->commit();"""
            gate_env = ENV.copy()
            gate_env.update(TEST_DB=DB, TEST_EVENT=str(race_id))
            gate = subprocess.Popen([PHP,"-r",gate_code],stdin=subprocess.PIPE,stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True,env=gate_env)
            assert gate.stdout.readline().strip() == "locked"
            barrier = threading.Barrier(len(racers))
            def book_race(client):
                barrier.wait(timeout=10)
                return client.post("booking_action.php", {"event_id":race_id,"action":"book"})[0]
            try:
                with concurrent.futures.ThreadPoolExecutor(max_workers=len(racers)) as pool:
                    futures = [pool.submit(book_race, c) for c in racers]
                    time.sleep(.3)
                    gate.stdin.write("commit\n")
                    gate.stdin.flush()
                    statuses = [f.result() for f in futures]
                gate.wait(timeout=10)
            finally:
                if gate.poll() is None: gate.kill()
            check("eight concurrent final-seat requests leave exactly one booking", all(s==302 for s in statuses) and scalar("SELECT COUNT(*) FROM bookings WHERE event_id=?", [race_id]) == 1)
            cancel_race_id = event(2)
            for email in ["attendee0@example.test", "attendee1@example.test"]:
                sql("INSERT INTO bookings(event_id,user_id) VALUES(?,?)", [cancel_race_id, ids[email]])
            for email in ["attendee2@example.test", "attendee3@example.test"]:
                sql("INSERT INTO waiting_list(event_id,user_id) VALUES(?,?)", [cancel_race_id, ids[email]])
            cancel_ids = {int(row["user_id"]):int(row["booking_id"]) for row in sql("SELECT booking_id,user_id FROM bookings WHERE event_id=?", [cancel_race_id])}
            with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
                futures = [pool.submit(client.post, "cancel_booking.php", {"booking_id":cancel_ids[ids[email]]}) for client, email in [(anonymous,"attendee0@example.test"),(attendee1,"attendee1@example.test")]]
                outcomes = [f.result()[0] for f in futures]
            promoted_ids = {int(row["user_id"]) for row in sql("SELECT user_id FROM bookings WHERE event_id=?", [cancel_race_id])}
            check("concurrent cancellations preserve FIFO promotion and capacity", outcomes == [302,302] and promoted_ids == {ids["attendee2@example.test"],ids["attendee3@example.test"]} and scalar("SELECT COUNT(*) FROM waiting_list WHERE event_id=?", [cancel_race_id]) == 0)
            edit_race_id = event(2)
            sql("INSERT INTO bookings(event_id,user_id) VALUES(?,?)", [edit_race_id, ids["attendee0@example.test"]])
            shrink = {**edit_data,"event_id":edit_race_id,"capacity":1}
            with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
                futures = [pool.submit(racers[0].post, "booking_action.php", {"event_id":edit_race_id,"action":"book"}),
                           pool.submit(owner.post, "event_form.php", shrink)]
                outcomes = [f.result()[0] for f in futures]
            check("concurrent capacity reduction never falls below bookings", all(s in (200,302) for s in outcomes) and scalar("SELECT capacity FROM events WHERE event_id=?", [edit_race_id]) >= scalar("SELECT COUNT(*) FROM bookings WHERE event_id=?", [edit_race_id]))
            duplicate_id = event(3)
            racers[0].post("booking_action.php", {"event_id":duplicate_id,"action":"book"})
            racers[0].post("booking_action.php", {"event_id":duplicate_id,"action":"book"})
            check("duplicate request leaves one booking", scalar("SELECT COUNT(*) FROM bookings WHERE event_id=?", [duplicate_id]) == 1)
            failed_id = event()
            sql("CREATE TRIGGER security_test_failure BEFORE INSERT ON bookings FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='secret_test_db_error'", execute=True)
            status, _, failure_body = racers[0].post("booking_action.php", {"event_id":failed_id,"action":"book"})
            check("database failure rolls back and hides SQL detail", status == 503 and "secret_test_db_error" not in failure_body and scalar("SELECT COUNT(*) FROM bookings WHERE event_id=?", [failed_id]) == 0)
            sql("DROP TRIGGER security_test_failure", execute=True)
            check("booking recovers after a rolled-back database failure", racers[0].post("booking_action.php", {"event_id":failed_id,"action":"book"})[0] == 302 and scalar("SELECT COUNT(*) FROM bookings WHERE event_id=?", [failed_id]) == 1)
            rollback_id = event(1)
            sql("INSERT INTO bookings(event_id,user_id) VALUES(?,?)", [rollback_id, ids["attendee0@example.test"]])
            rollback_booking = scalar("SELECT booking_id FROM bookings WHERE event_id=?", [rollback_id])
            sql("INSERT INTO waiting_list(event_id,user_id) VALUES(?,?)", [rollback_id, ids["attendee1@example.test"]])
            sql("CREATE TRIGGER security_promotion_failure BEFORE INSERT ON bookings FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='secret_promotion_error'", execute=True)
            status, _, failure_body = anonymous.post("cancel_booking.php", {"booking_id":rollback_booking})
            check("failed promotion rolls back cancellation and queue atomically", status == 503 and "secret_promotion_error" not in failure_body and scalar("SELECT user_id FROM bookings WHERE event_id=?", [rollback_id]) == ids["attendee0@example.test"] and scalar("SELECT COUNT(*) FROM waiting_list WHERE event_id=?", [rollback_id]) == 1)
            sql("DROP TRIGGER security_promotion_failure", execute=True)
            xss_id = event(title="<script>alert(1)</script>")
            _, _, xss_body = Client(base).request(f"event.php?id={xss_id}")
            check("stored event title is output-encoded", "<script>alert(1)</script>" not in xss_body and "&lt;script&gt;alert(1)&lt;/script&gt;" in xss_body)
            visitor = Client(base)
            visitor.get_token("register.php")
            for n, weak in enumerate(["abcdefgh1","ABCDEFG1!","abcdefg1!","Abcdefgh!","Abcdefg1","Ab1!xyz","Abcdefghijklmnop1!"]):
                _, _, body = visitor.post("register.php", {"name":"Test","email":f"weak{n}@example.test","password":weak,"role":"user"})
                check(f"password policy rejects case {n+1}", "Password must have 8" in body and scalar("SELECT COUNT(*) FROM users WHERE email=?", [f"weak{n}@example.test"]) == 0)
            check("registration cannot choose admin role", visitor.post("register.php", {"name":"Test","email":"badrole@example.test","password":"TestPass1!","role":"admin"})[0] == 200 and scalar("SELECT COUNT(*) FROM users WHERE email='badrole@example.test'") == 0)
            check("strong-password registration works", visitor.post("register.php", {"name":"Test","email":"valid@example.test","password":"TestPass1!","role":"user"})[0] == 302 and scalar("SELECT COUNT(*) FROM users WHERE email='valid@example.test'") == 1)
            check("new passwords use bcrypt cost 12", scalar("SELECT password_hash FROM users WHERE email='valid@example.test'").startswith("$2y$12$"))
            sql("DELETE FROM auth_rate_limits", execute=True)
            visitor.get_token("register.php")
            check("duplicate registration creates no extra account", visitor.post("register.php", {"name":"Test","email":"valid@example.test","password":"TestPass1!","role":"user"})[0] == 200 and scalar("SELECT COUNT(*) FROM users WHERE email='valid@example.test'") == 1)
            for n in range(9):
                visitor.post("register.php", {"name":"","email":f"invalid{n}","password":"x","role":"user"})
            check("registration is IP-rate-limited", visitor.post("register.php", {"name":"Test","email":"limited@example.test","password":"TestPass1!","role":"user"})[0] == 429 and scalar("SELECT COUNT(*) FROM users WHERE email='limited@example.test'") == 0)
            sql("DELETE FROM auth_rate_limits", execute=True)
            attacker = Client(base)
            attacker.get_token()
            statuses = [attacker.post("login.php", {"email":"no-account@example.test","password":"wrong"})[0] for _ in range(6)]
            check("unknown accounts have the same account lockout", statuses == [200]*5+[429])
            fresh_attacker = Client(base)
            fresh_attacker.get_token()
            status, retry_headers, _ = fresh_attacker.post("login.php", {"email":"no-account@example.test","password":"wrong"})
            check("new session cannot bypass account limiter and Retry-After is set", status == 429 and int(retry_headers["Retry-After"]) > 0)
            sql("DELETE FROM auth_rate_limits", execute=True)
            for _ in range(5): attacker.post("login.php", {"email":"attendee11@example.test","password":"wrong"})
            check("known account temporarily blocks even valid password", attacker.post("login.php", {"email":"attendee11@example.test","password":"TestPass1!"})[0] == 429)
            sql("UPDATE auth_rate_limits SET window_started=window_started-901", execute=True)
            check("expired lockout permits login", attacker.post("login.php", {"email":"attendee11@example.test","password":"TestPass1!"})[0] == 302)
            sql("DELETE FROM auth_rate_limits", execute=True)
            spray = Client(base)
            spray.get_token()
            statuses = [spray.post("login.php", {"email":f"spray{n}@example.test","password":"wrong"}, {"X-Forwarded-For":f"10.0.0.{n+1}"})[0] for n in range(31)]
            check("IP spray limit cannot be bypassed with forwarding headers", statuses == [200]*30+[429])
            sql("DELETE FROM auth_rate_limits", execute=True)
            alias = Client(base)
            alias.get_token()
            check("Unicode collation alias cannot authenticate", alias.post("login.php", {"email":"áttendee11@example.test","password":"TestPass1!"})[0] == 200)
            check("array-valued password rejected", alias.post("login.php", {"email":"attendee11@example.test","password[]":"TestPass1!"})[0] == 400)
            old_hash = php("echo password_hash('TestPass1!',PASSWORD_BCRYPT,['cost'=>10]);")
            sql("UPDATE users SET password_hash=? WHERE email='attendee11@example.test'", [old_hash])
            upgrade = Client(base)
            upgrade.login("ATTENDEE11@EXAMPLE.TEST")
            check("existing password hash upgrades on successful login", scalar("SELECT password_hash FROM users WHERE email='attendee11@example.test'").startswith("$2y$12$"))
            sql("DELETE FROM auth_rate_limits", execute=True)
            timeout = Client(base)
            timeout.login("attendee10@example.test")
            def age_session(client, field, age):
                code = "$c=json_decode(getenv('TEST_SESSION'),true);session_save_path($c['path']);session_id($c['sid']);session_start();$_SESSION[$c['field']]=time()-$c['age'];session_write_close();"
                env = ENV.copy()
                env["TEST_SESSION"] = json.dumps({"path":str(sessions),"sid":client.sid(),"field":field,"age":age})
                php(code, env)
            age_session(timeout,"last_activity",1801)
            check("idle timeout removes authenticated access", timeout.request("my_bookings.php")[0] == 302)
            timeout.login("attendee10@example.test")
            age_session(timeout,"authenticated_at",28801)
            check("absolute timeout applies despite recent activity", timeout.request("my_bookings.php")[0] == 302)
            sql("UPDATE users SET role='user' WHERE email='other@example.test'")
            check("role revocation takes effect without logout", other.request("dashboard.php")[0] == 403)
            check("state-changing GET is rejected", owner.request("logout.php")[0] == 405 and admin.request("admin_action.php")[0] == 405)
            previous_sid, previous_token = owner.sid(), owner.token
            check("valid logout works", owner.post("logout.php", {})[0] == 302)
            check("logout invalidates previous session and CSRF", owner.sid() != previous_sid and owner.request("dashboard.php")[0] == 302 and owner.request("logout.php", {"csrf_token":previous_token})[0] == 403)
            check("poster uses CSP-compatible external print handler", "onclick=" not in (app/"poster.php").read_text() and 'assets/print.js' in (app/"poster.php").read_text())
            status, qr_headers, qr_body = Client(base).request(f"qr.php?id={xss_id}")
            check("installed QR library returns approved-event SVG", status == 200 and 'image/svg+xml' in qr_headers.get('Content-Type','') and '<svg' in qr_body)
            unpublished_id = event(status='pending')
            check("QR endpoint hides unapproved events", Client(base).request(f"qr.php?id={unpublished_id}")[0] == 404)
            status, poster_headers, poster_body = Client(base).request(f"poster.php?id={xss_id}")
            check("poster and external script load under restrictive CSP", status == 200 and "script-src 'self'" in poster_headers['Content-Security-Policy'] and 'onclick=' not in poster_body and 'assets/print.js' in poster_body and Client(base).request('assets/print.js')[0] == 200)
            if os.environ.get("EVENTLINK_BROWSER_SMOKE") == "1":
                subprocess.run(["node", str(ROOT/"tests/browser_smoke.cjs"), base, str(xss_id), str(temp/"poster-smoke.png")], check=True, timeout=45)
                check("browser: QR, poster styling, CSP and print handler work", True)
            config.update(base_url="https://example.test",session_secure=True,hsts_max_age=3600)
            write_config()
            _, secure_headers, _ = Client(base).request("login.php")
            check("HTTPS configuration sets Secure cookie and optional HSTS", "secure" in secure_headers.get("Set-Cookie","").lower() and secure_headers.get("Strict-Transport-Security") == "max-age=3600")
            # A missing migration must fail closed, not silently bypass authentication throttling.
            config.update(base_url=base,session_secure=False,hsts_max_age=0)
            write_config()
            sql("DROP TABLE auth_rate_limits", execute=True)
            no_table = Client(base)
            no_table.get_token()
            status, _, body = no_table.post("login.php", {"email":"attendee11@example.test","password":"TestPass1!"})
            check("missing authentication migration fails closed", status == 503 and "SQLSTATE" not in body)
        except BaseException as exception:
            if not RESULTS or RESULTS[-1]['status'] != 'FAIL':
                RESULTS.append({'test':'suite execution','status':'FAIL','detail':str(exception)})
            raise
        finally:
            if server:
                try: os.killpg(server.pid, signal.SIGTERM)
                except ProcessLookupError: pass
                server.wait(timeout=10)
            if log:
                log.flush()
                log.seek(0)
                server_log = log.read()
                log.close()
                if not RESULTS or RESULTS[-1]["status"] == "FAIL":
                    print(server_log[-5000:])
            if created:
                assert re.fullmatch(r"eventlink_security_[a-f0-9]{12}", DB)
                sql(f"DROP DATABASE {DB}", database="", execute=True)
            if args.output:
                Path(args.output).write_text(json.dumps({"tests":RESULTS,"passed":sum(t["status"]=="PASS" for t in RESULTS),"failed":sum(t["status"]=="FAIL" for t in RESULTS)},indent=2)+"\n")
    print(f"{len(RESULTS)} security regression checks passed; isolated database removed.")

if __name__ == "__main__":
    main()
