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
def login(email,password=PW):
    s=requests.Session();r=post(s,'login.php',{'email':email,'password':password});check(r.status_code==302,'password stage');return s,r
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
    staff_email='staff-'+os.urandom(6).hex()+'@eventlink.invalid'
    guest=requests.Session()
    check(post(user,'admin_action.php',{'action':'create_staff','name':'New staff','email':staff_email}).status_code==403,'user cannot create staff')
    check(post(guest,'admin_action.php',{'action':'create_staff','name':'New staff','email':staff_email}).status_code==302,'anonymous cannot create staff')
    check(scalar('SELECT COUNT(*) FROM users WHERE email=?',[staff_email])==0,'denied creation leaves no account')
    r=post(admin,'admin_action.php',{'action':'create_staff','name':'New staff','email':staff_email,'role':'admin'})
    check(r.status_code==200 and 'Save these sign-in details' in r.text,'admin directly creates staff account')
    temporary=re.search(r'id="temporary-password"[^>]*value="([^"]+)"',r.text).group(1)
    row=db('SELECT * FROM users WHERE email=?',[staff_email])['rows'][0];sid=int(row['user_id']);ids.append(sid)
    check(row['role']=='staff' and row['must_change_password']==1 and row['is_active']==1,'role server-set and initial password replacement required')
    check(row['email_verified_at'] is None,'login ID is not falsely marked as a verified mailbox')
    check(row['password_hash'].startswith('$2y$12$') and temporary not in row['password_hash'],'temporary password is stored only as a bcrypt hash')
    check('no-store' in r.headers.get('Cache-Control',''),'credential response is not cacheable')
    check(temporary not in get(admin,'admin.php').text and 'temporary-password' not in get(admin,'admin_action.php').text,'credentials cannot be redisplayed with GET')
    before_hash=row['password_hash']
    r=post(admin,'admin_action.php',{'action':'create_staff','name':'Duplicate','email':staff_email.upper()})
    check(r.status_code==302 and scalar('SELECT password_hash FROM users WHERE user_id=?',[sid])==before_hash,'duplicate login ID never overwrites an account')
    check('already exists' in get(admin,'admin.php').text,'duplicate account gives usable feedback')
    check(get(guest,'accept_staff.php?token='+'a'*64).status_code==410 and post(guest,'accept_staff.php',{'token':'a'*64,'password':PW}).status_code==410,'retired invitation endpoint cannot create accounts')
    check('Choose a valid account type' in post(guest,'register.php',{'name':'Public staff','email':'public-'+staff_email,'role':'staff','password':PW,'password_confirm':PW}).text,'public registration cannot create staff')
    staff,r=login(staff_email,temporary);check(r.headers.get('Location')=='change_initial_password.php','temporary password enters forced replacement flow')
    second_staff,_=login(staff_email,temporary)
    for path in ['staff.php','account.php','mfa.php','developer.php']:
        check(get(staff,path).url.endswith('/change_initial_password.php'),'initial password gate protects '+path)
    check(post(staff,'mfa.php',{'action':'start','password':temporary}).headers.get('Location')=='change_initial_password.php','POST cannot bypass initial password gate')
    check('incorrect' in post(staff,'change_initial_password.php',{'current_password':'wrong','password':PW,'password_confirm':PW}).text,'initial replacement requires correct temporary password')
    check('different' in post(staff,'change_initial_password.php',{'current_password':temporary,'password':temporary,'password_confirm':temporary}).text,'temporary password cannot be kept')
    old_csrf=csrf(staff);old_sid=staff.cookies.get('FAIRBOARDSESSID')
    r=post(staff,'change_initial_password.php',{'current_password':temporary,'password':PW,'password_confirm':PW})
    check(r.status_code==302 and r.headers.get('Location')=='mfa.php','own password leads to mandatory MFA')
    check(staff.cookies.get('FAIRBOARDSESSID')!=old_sid and csrf(staff)!=old_csrf,'initial replacement rotates session and CSRF')
    check('login.php' in get(second_staff,'staff.php').url,'initial replacement invalidates other sessions')
    check(post(guest,'login.php',{'email':staff_email,'password':temporary}).status_code==200,'temporary password no longer works after replacement')
    check(get(staff,'staff.php').url.endswith('/mfa.php'),'staff cannot skip authenticator enrollment')
    staff_secret,staff_codes,_=enroll(staff)
    check(get(staff,'staff.php').url.endswith('/staff.php'),'staff signs in without an email inbox')
    check('Managed staff login' in get(staff,'account.php').text and 'Resend verification email' not in get(staff,'account.php').text,'account explains managed staff login')
    check(post(staff,'admin_action.php',{'action':'create_staff','name':'Denied','email':'nested-'+staff_email}).status_code==403,'staff cannot create another staff account')
    check(post(staff,'admin_action.php',{'action':'reset_staff_password','user_id':sid}).status_code==403,'staff cannot issue temporary passwords')
    post(staff,'account.php',{'action':'verify'})
    post(guest,'forgot_password.php',{'email':staff_email})
    check(scalar('SELECT COUNT(*) FROM account_tokens WHERE user_id=?',[sid])==0,'staff login IDs cannot receive verification or reset tokens')
    # Even a legacy token must not recover a managed staff identity via email.
    saved_hash=scalar('SELECT password_hash FROM users WHERE user_id=?',[sid])
    for purpose in ['reset','verify']:
        raw=os.urandom(32).hex();db("INSERT INTO account_tokens(user_id,purpose,token_hash,expires_at)VALUES(?,?,?,DATE_ADD(NOW(),INTERVAL 1 DAY))",[sid,purpose,hashlib.sha256(raw.encode()).hexdigest()])
        r=post(guest,'reset_password.php' if purpose=='reset' else 'verify_email.php',{'token':raw,'password':PW,'password_confirm':PW})
        check('invalid' in r.text,'legacy staff '+purpose+' token is refused')
    check(scalar('SELECT password_hash FROM users WHERE user_id=?',[sid])==saved_hash and scalar('SELECT email_verified_at FROM users WHERE user_id=?',[sid]) is None,'legacy links cannot alter staff credentials or mailbox status')
    check(scalar('SELECT COUNT(*) FROM notifications WHERE user_id=?',[sid])>0,'staff security notices are delivered in-app')
    check(not any(staff_email in f.read_text() for f in (ROOT/'storage/mail').glob('*.txt')),'creation and MFA do not email a managed login ID')
    mfa_before=db('SELECT * FROM user_mfa WHERE user_id=?',[sid])['rows'][0]
    r=post(admin,'admin_action.php',{'action':'reset_staff_password','user_id':sid})
    reset_password=re.search(r'id="temporary-password"[^>]*value="([^"]+)"',r.text).group(1)
    check(r.status_code==200 and reset_password!=temporary,'admin can issue a fresh temporary password')
    check('login.php' in get(staff,'staff.php').url,'admin password reset invalidates staff sessions')
    check(db('SELECT * FROM user_mfa WHERE user_id=?',[sid])['rows'][0]==mfa_before,'admin password reset preserves authenticator and recovery hashes')
    check(scalar('SELECT COUNT(*) FROM account_tokens WHERE user_id=?',[sid])==0,'admin reset revokes previous account tokens')
    staff,r=login(staff_email,reset_password);check(r.headers.get('Location')=='mfa_challenge.php','reset staff must still prove existing MFA')
    check(post(staff,'mfa_challenge.php',{'code':staff_codes[0]}).status_code==302,'existing recovery code completes reset sign-in')
    check(get(staff,'staff.php').url.endswith('/change_initial_password.php'),'reset still requires own password before staff access')
    check(post(staff,'change_initial_password.php',{'current_password':reset_password,'password':PW,'password_confirm':PW}).headers.get('Location')=='staff.php','reset password flow returns enrolled staff to workspace')
    # Password recovery is not a way to modify a regular account, admin, or disabled staff.
    other_hash=scalar('SELECT password_hash FROM users WHERE user_id=?',[uid])
    check(post(admin,'admin_action.php',{'action':'reset_staff_password','user_id':uid}).status_code==302 and scalar('SELECT password_hash FROM users WHERE user_id=?',[uid])==other_hash,'staff reset cannot target another role')
    db('UPDATE users SET is_active=0 WHERE user_id=?',[sid]);saved_hash=scalar('SELECT password_hash FROM users WHERE user_id=?',[sid])
    post(admin,'admin_action.php',{'action':'reset_staff_password','user_id':sid})
    check(scalar('SELECT password_hash FROM users WHERE user_id=?',[sid])==saved_hash,'disabled staff cannot receive a new password')
    db('UPDATE users SET is_active=1 WHERE user_id=?',[sid])
    db('DELETE FROM auth_rate_limits')
    # Verified ownership gates and action idempotency.
    cid,cemail=account('creator');creator,_=login(cemail);eid=event(cid)
    check(get(staff,'event_form.php?id='+str(eid)).status_code==403,'new staff has no implicit event access')
    post(admin,'admin_action.php',{'action':'assign_staff','staff_id':sid,'creator_id':cid})
    r=get(staff,'event_form.php?id='+str(eid));check(r.status_code==200 and 'event_form.php' in r.url,'assigned staff edits events without a mailbox verification gate')
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
    admin_page=get(admin,'admin.php').text
    check(row['email'] not in admin_page and f"#{uid}" in admin_page,'admin separates anonymized records from active account list')
    registered_match=re.search(r'<strong>(\d+)</strong><span>Registered accounts</span>',admin_page)
    check(registered_match and int(registered_match.group(1))==scalar('SELECT COUNT(*) FROM users WHERE deleted_at IS NULL'),'registered account count excludes deleted accounts')
    check('login.php' in get(other,'account.php').url,'deletion invalidates other sessions')
    check(scalar('SELECT user_id FROM bookings WHERE event_id=?',[eid2])==wid,'account deletion promotes waiting attendee under event lock')
    check('upcoming events' in post(creator,'delete_account.php',{'confirmation':'Delete','password':PW}).text,'creator must resolve upcoming events before deletion')
    check('handover' in post(admin,'delete_account.php',{'confirmation':'Delete','password':PW,'code':codes[2]}).text,'admin self-deletion blocked')
    check(post(admin,'admin_action.php',{'action':'set_active','user_id':uid,'active':'1'}).status_code==403,'deleted account cannot be reactivated')
    # Generated assets are served and used by the same A4 poster template.
    for category in ['Learning','Sports','Social','Other']:
        db('UPDATE events SET category=?,artwork=? WHERE event_id=?',[category,category.lower(),eid2])
        asset_url='assets/art/'+category.lower()+'.svg'
        r=get(guest,'poster.php?id='+str(eid2));check(asset_url in r.text,'poster uses '+category+' SVG artwork')
        check(get(guest,asset_url).status_code==200,'SVG asset served '+category)
    # Saturation persists across sessions.
    for _ in range(31): r=post(waiter,'leave_waitlist.php',{'event_id':eid2})
    check(r.status_code==429 and 'Retry-After' in r.headers,'booking mutation rate limit enforced')
    (ROOT/'test-results').mkdir(exist_ok=True)
    (ROOT/'test-results/defense.json').write_text(json.dumps({'passed':checks,'failed':0}))
    print(f'{checks} defense integration assertions passed.',flush=True)
finally:
    for eid in events:db('DELETE FROM events WHERE event_id=?',[eid])
    for uid in ids:
        db('DELETE FROM staff_assignments WHERE staff_user_id=? OR creator_user_id=?',[uid,uid]);db('DELETE FROM creator_invoices WHERE user_id=?',[uid]);db('DELETE FROM creator_plans WHERE user_id=?',[uid]);db('DELETE FROM users WHERE user_id=?',[uid])
    db('DELETE FROM auth_rate_limits')
