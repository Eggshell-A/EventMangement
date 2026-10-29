"""Security feature regression against a dedicated local *_test database only."""
import os, json, re, time, subprocess, hashlib, hmac, base64, struct, concurrent.futures
from pathlib import Path
import requests
if os.getenv('EVENTLINK_TEST_ALLOW_MUTATION') != '1': raise SystemExit('Dedicated test opt-in required')
ROOT=Path(__file__).resolve().parents[1]; URL=os.getenv('EVENTLINK_TEST_URL','http://127.0.0.1:8080'); PW='DefenseTest!2026'; checks=0; ids=[]; events=[]
def fixture(v):
    return json.loads(subprocess.run(['php',str(ROOT/'tests/db_fixture.php')],input=json.dumps(v),text=True,capture_output=True,check=True).stdout)
def db(sql,params=()): return fixture({'sql':sql,'params':list(params)})
def scalar(sql,params=()): return next(iter(db(sql,params)['rows'][0].values()))
def check(ok,label):
    global checks
    assert ok,label
    checks+=1; print('PASS',label,flush=True)
def get(s,path): return s.get(URL+'/'+path,timeout=20)
def csrf(s):
    r=get(s,'index.php'); m=re.search(r'name="csrf_token" value="([a-f0-9]+)"|name="csrf-token" content="([a-f0-9]+)"',r.text); return m.group(1) or m.group(2)
def post(s,path,data=None): return s.post(URL+'/'+path,data={**(data or {}),'csrf_token':csrf(s)},timeout=30,allow_redirects=False)
def otp(secret,offset=0):
    counter=int(time.time()//30)+offset; digest=hmac.new(base64.b32decode(secret),struct.pack('>Q',counter),hashlib.sha1).digest();o=digest[-1]&15
    return str((struct.unpack('>I',digest[o:o+4])[0]&0x7fffffff)%1000000).zfill(6)
def account(role='user',verified=True):
    email='def-'+os.urandom(6).hex()+'@fairboard.test';uid=int(db('INSERT INTO users(name,email,password_hash,role,email_verified_at)VALUES(?,?,?,?,?)',['Defense tester',email,fixture({'hash':PW}),role,'2026-01-01 00:00:00' if verified else None])['id']);ids.append(uid);return uid,email
def login(email):
    s=requests.Session();r=post(s,'login.php',{'email':email,'password':PW});check(r.status_code==302,'password stage');return s,r
def enroll(s):
    r=post(s,'mfa.php',{'action':'start','password':PW});check(r.status_code==200,'MFA setup starts after password')
    secret=re.search(r'class="secret-key">([A-Z2-7]+)',r.text).group(1)
    enrollment_code=otp(secret);r=post(s,'mfa.php',{'action':'enable','code':enrollment_code});check('Save your recovery codes' in r.text,'MFA enrollment confirms actual TOTP')
    codes=re.findall(r'\b[a-f0-9]{16}\b',re.search(r'<pre class="recovery-codes">(.*?)</pre>',r.text,re.S).group(1));check(len(codes)==10,'recovery codes shown once');return secret,codes,enrollment_code
def event(owner,title='Defense event',capacity=1):
    eid=int(db("INSERT INTO events(organiser_id,title,description,start_time,location,capacity,original_capacity,status,artwork,category)VALUES(?,?,'Test',DATE_ADD(NOW(),INTERVAL 1 DAY),'Hall',?,?,'approved','sports','Sports')",[owner,title,capacity,capacity])['id']);events.append(eid);return eid
try:
    db('DELETE FROM auth_rate_limits')
    aid,aemail=account('admin');admin,_=login(aemail)
    check(get(admin,'admin.php').url.endswith('/mfa.php'),'admin cannot bypass mandatory enrollment')
    secret,codes,enrollment_code=enroll(admin)
    check(get(admin,'admin.php').status_code==200,'enrolled admin access')
    check(codes[0] not in get(admin,'mfa.php').text,'recovery codes are not redisplayed')
    check(secret not in scalar('SELECT secret_cipher FROM user_mfa WHERE user_id=?',[aid]),'secret encrypted in database')
    check(codes[0] not in scalar('SELECT recovery_hashes FROM user_mfa WHERE user_id=?',[aid]),'only recovery hashes stored')
    check('must keep MFA' in post(admin,'mfa.php',{'action':'disable'}).text,'admin cannot disable MFA')
    pending,r=login(aemail);check(r.headers['Location']=='mfa_challenge.php','second factor required')
    check('login.php' in get(pending,'admin.php').url,'password-only session cannot access admin')
    check(post(pending,'mfa_challenge.php',{'code':enrollment_code}).status_code==200,'enrollment TOTP cannot be replayed for login')
    check(post(pending,'mfa_challenge.php',{'code':codes[0]}).status_code==302,'recovery code completes login')
    pending2,_=login(aemail);check(post(pending2,'mfa_challenge.php',{'code':codes[0]}).status_code==200,'consumed recovery code rejected')
    pending3,_=login(aemail)
    # Race two distinct sessions using the same still-unused recovery code.
    ts=[csrf(pending2),csrf(pending3)]
    with concurrent.futures.ThreadPoolExecutor() as ex:
        results=list(ex.map(lambda x:x[0].post(URL+'/mfa_challenge.php',data={'csrf_token':x[1],'code':codes[1]},allow_redirects=False,timeout=30),zip([pending2,pending3],ts)))
    check(sorted(r.status_code for r in results)==[200,302],'concurrent recovery code accepted exactly once')
    db('DELETE FROM auth_rate_limits')
    totp_session,_=login(aemail)
    check(post(totp_session,'mfa_challenge.php',{'code':otp(secret,1)}).status_code==302,'fresh authenticator TOTP completes login')
    db('DELETE FROM auth_rate_limits')
    # Staff creation is exclusively an authenticated admin action, never a public role parameter.
    uid,email=account();user,_=login(email)
    invite_email='staff-'+os.urandom(6).hex()+'@fairboard.test'
    check(post(user,'admin_action.php',{'action':'invite_staff','name':'New staff','email':invite_email}).status_code==403,'user cannot invite staff')
    check(post(admin,'admin_action.php',{'action':'invite_staff','name':'New staff','email':invite_email}).status_code==302,'admin sends staff invite')
    raw=None
    for f in sorted((ROOT/'storage/mail').glob('*.txt'),key=lambda f:f.stat().st_mtime,reverse=True):
        body=f.read_text()
        if invite_email in body and 'accept_staff.php' in body: raw=re.search(r'token=([a-f0-9]{64})',body).group(1);break
    check(raw is not None,'staff invite delivered through development transport')
    check(scalar('SELECT token_hash FROM staff_invites WHERE email=?',[invite_email])==hashlib.sha256(raw.encode()).hexdigest(),'invitation stored hashed')
    guest=requests.Session();get(guest,'accept_staff.php?token='+raw)
    check(scalar('SELECT COUNT(*) FROM users WHERE email=?',[invite_email])==0,'GET invite never creates an account')
    r=post(guest,'accept_staff.php',{'token':raw,'password':PW,'password_confirm':PW});check(r.status_code==302,'staff accepts invitation once')
    sid=int(scalar('SELECT user_id FROM users WHERE email=?',[invite_email]));ids.append(sid)
    check(scalar('SELECT role FROM users WHERE user_id=?',[sid])=='staff','role is server-set staff')
    check('already used' in post(guest,'accept_staff.php',{'token':raw,'password':PW,'password_confirm':PW}).text,'invitation replay rejected')
    staff,_=login(invite_email);check(get(staff,'staff.php').url.endswith('/mfa.php'),'invited staff must enroll MFA')
    # Verified ownership gates and action idempotency.
    cid,cemail=account('creator');creator,_=login(cemail);eid=event(cid)
    unid,unemail=account(verified=False);unverified,_=login(unemail)
    check(post(unverified,'booking_action.php',{'event_id':eid,'action':'book'}).headers.get('Location')=='account.php','unverified attendee denied booking')
    check(scalar('SELECT COUNT(*) FROM bookings WHERE user_id=?',[unid])==0,'verification denial has no booking side effect')
    post(user,'booking_action.php',{'event_id':eid,'action':'book'})
    post(creator,'organiser_action.php',{'event_id':eid,'action':'cancel'});before=int(scalar('SELECT COUNT(*) FROM notifications WHERE event_id=?',[eid]));post(creator,'organiser_action.php',{'event_id':eid,'action':'cancel'})
    check(before==int(scalar('SELECT COUNT(*) FROM notifications WHERE event_id=?',[eid])),'repeated cancellation does not resend notifications')
    # Deletion confirmation, immutable admin, cancellation/promotion and session invalidation.
    eid2=event(cid);post(user,'booking_action.php',{'event_id':eid2,'action':'book'})
    wid,wemail=account();waiter,_=login(wemail);post(waiter,'booking_action.php',{'event_id':eid2,'action':'wait'})
    other,_=login(email)
    check('Type Delete exactly' in post(user,'delete_account.php',{'confirmation':'delete','password':PW}).text,'case-sensitive deletion confirmation')
    check('incorrect' in post(user,'delete_account.php',{'confirmation':'Delete','password':'wrong'}).text,'deletion requires current password')
    check(post(user,'delete_account.php',{'confirmation':'Delete','password':PW}).status_code==302,'confirmed own account deleted')
    row=db('SELECT name,email,is_active,deleted_at FROM users WHERE user_id=?',[uid])['rows'][0]
    check(row['name']=='Deleted account' and row['email']!=email and not row['is_active'] and row['deleted_at'],'identity erased and account disabled')
    check('login.php' in get(other,'account.php').url,'deletion invalidates other sessions')
    check(scalar('SELECT user_id FROM bookings WHERE event_id=?',[eid2])==wid,'account deletion promotes waiting attendee under event lock')
    check('upcoming events' in post(creator,'delete_account.php',{'confirmation':'Delete','password':PW}).text,'creator must resolve upcoming events before deletion')
    check('handover' in post(admin,'delete_account.php',{'confirmation':'Delete','password':PW,'code':codes[2]}).text,'admin self-deletion blocked')
    check(post(admin,'admin_action.php',{'action':'set_active','user_id':uid,'active':'1'}).status_code==403,'deleted account cannot be reactivated')
    # Generated assets are served and used by the same A4 poster template.
    for category in ['Learning','Sports','Social','Other']:
        db('UPDATE events SET category=?,artwork=? WHERE event_id=?',[category,category.lower(),eid2])
        r=get(guest,'poster.php?id='+str(eid2));check('assets/art/'+category.lower()+'.webp' in r.text,'poster uses '+category+' art')
        check(get(guest,'assets/art/'+category.lower()+'.webp').status_code==200,'asset served '+category)
    # Saturation persists across sessions.
    for _ in range(31): r=post(waiter,'leave_waitlist.php',{'event_id':eid2})
    check(r.status_code==429 and 'Retry-After' in r.headers,'booking mutation rate limit enforced')
    (ROOT/'test-results').mkdir(exist_ok=True)
    (ROOT/'test-results/defense.json').write_text(json.dumps({'passed':checks,'failed':0}))
    print(f'{checks} defense integration assertions passed.',flush=True)
finally:
    for eid in events:db('DELETE FROM events WHERE event_id=?',[eid])
    for uid in ids:
        db('DELETE FROM staff_invites WHERE invited_by=?',[uid]);db('DELETE FROM staff_assignments WHERE staff_user_id=? OR creator_user_id=?',[uid,uid]);db('DELETE FROM creator_invoices WHERE user_id=?',[uid]);db('DELETE FROM creator_plans WHERE user_id=?',[uid]);db('DELETE FROM users WHERE user_id=?',[uid])
    db('DELETE FROM auth_rate_limits')
