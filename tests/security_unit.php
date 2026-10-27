<?php
require __DIR__.'/../includes/security.php';require __DIR__.'/../includes/helpers.php';require __DIR__.'/../includes/tickets.php';require __DIR__.'/../includes/booking.php';
$config=['app_key'=>str_repeat('test-key-',8),'app_env'=>'development','enable_developer_tools'=>true,'developer_user_ids'=>[4]];
$passed=0;
function check(bool $condition,string $name):void {global $passed;if(!$condition)throw new RuntimeException($name);$passed++;echo "PASS $name\n";}
$_SESSION=[];$csrf=csrfToken();check($csrf===csrfToken(),'CSRF token stable within session');check((bool)preg_match('/^[a-f0-9]{64}$/D',$csrf),'CSRF token has 256 bits');
check(passwordMeetsPolicy('StrongPhrase!2026'),'accept long strong password');
foreach(['Short1!','abcdefghijklmnop','ABCDEFGHIJK123!','LongPassword123',str_repeat('a',73).'A1!',"AstrongPass123!\0"]as $bad)check(!passwordMeetsPolicy($bad),'reject invalid password');
check(safeEventNext('https://evil.example')===null,'reject external redirect');check(safeEventNext(['event.php'])===null,'reject array redirect');check(safeEventNext('event.php?id=7')==='event.php?id=7','accept event return');
$token=ticketToken(11,22,3000);check(verifyTicketToken($token,3000)===['booking_id'=>11,'event_id'=>22],'valid ticket');check(verifyTicketToken($token,3029)!==null,'valid until last second');check(verifyTicketToken($token,3030)===null,'expires exactly at thirty seconds');check(verifyTicketToken($token,2999)===null,'future token rejected');check(ticketToken(11,22,3030)!==$token,'next window rotates token');
check(verifyTicketToken(str_replace('v1.11.','v1.12.',$token),3000)===null,'booking tamper rejected');check(verifyTicketToken(str_replace('.22.','.23.',$token),3000)===null,'event tamper rejected');check(verifyTicketToken($token.'junk',3000)===null,'trailing data rejected');check(verifyTicketToken('v1.0.22.100.'.str_repeat('0',64),3000)===null,'zero identifier rejected');
$_SESSION=['user'=>['user_id'=>4,'role'=>'admin']];check(isDeveloper(),'allowlisted development admin');$config['app_env']='production';check(!isDeveloper(),'developer tools unavailable in production');$config['app_env']='development';$_SESSION['user']['user_id']=5;check(!isDeveloper(),'non-allowlisted admin denied');$_SESSION['user']=['user_id'=>4,'role'=>'user'];check(!isDeveloper(),'normal user denied');
$config['school_email_domains']=['stu.vtc.edu.hk'];check(schoolEmail('x@stu.vtc.edu.hk'),'exact school domain accepted');check(!schoolEmail('x@stu.vtc.edu.hk.evil.example'),'school suffix spoof rejected');
check(!str_contains(e('<script>"test"</script>'),'<script>'),'HTML escaped');
check(waitingRefillLimit(['original_capacity'=>100,'capacity'=>200])===10,'refill budget based on original capacity');
check(waitingRefillRemaining(['original_capacity'=>100,'capacity'=>200,'refill_count'=>9])===1,'tenth refill remains available');
check(waitingRefillRemaining(['original_capacity'=>100,'capacity'=>200,'refill_count'=>10])===0,'eleventh refill refused');
check(waitingRefillLimit(['original_capacity'=>9,'capacity'=>20])===1,'small events retain one refill');
check(validEventUrl('https://example.org/event'),'HTTPS event link accepted');
check(!validEventUrl('javascript:alert(1)'),'script event link refused');
echo "$passed security unit assertions passed.\n";
