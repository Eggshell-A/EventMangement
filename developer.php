<?php
require __DIR__.'/includes/bootstrap.php';requireLogin();if(!isDeveloper())abortRequest(404,'Page not found.');
$error='';$recent=(int)($_SESSION['developer_verified_at']??0)>time()-600;
if($_SERVER['REQUEST_METHOD']==='POST'){
 $retry=consumeAuthLimits($pdo,[authBucketKey('developer-reauth',(string)currentUser()['user_id'])=>5]);
 if($retry){http_response_code(429);header('Retry-After: '.$retry);$error='Too many attempts. Try again later.';}
 else{if(reauthenticate($pdo,postString('password'),trim(postString('code')))){$_SESSION['developer_verified_at']=time();audit($pdo,'developer.unlock');redirect('developer.php');}else $error='Password or verification code is incorrect.';}
}
$pageTitle='SQL explorer';
if(!$recent){require __DIR__.'/includes/header.php';?><section class="compact-panel"><p class="eyebrow">DEVELOPER ACCESS</p><h1>SQL explorer.</h1><p class="muted">Confirm your password to view table information for 10 minutes.</p><?php if($error):?><p class="flash error"><?=e($error)?></p><?php endif;?><form method="post"><?=csrfField()?><label for="password">Your password</label><input id="password" name="password" type="password" required autocomplete="current-password"><?=secondFactorField()?><button class="wide">Unlock table explorer</button></form></section><?php require __DIR__.'/includes/footer.php';exit;}
// Identifiers ONLY come from this literal allowlist. Never execute user-supplied SQL.
$tables=[
 'users'=>['user_id','name','email','role','is_active','must_change_password','is_school_email','school_email_verified','email_verified_at','created_at'],
 'roles'=>['role_id','code','label'],
 'events'=>['event_id','organiser_id','title','category','start_time','location','capacity','status'],
 'bookings'=>['booking_id','event_id','user_id','checked_in','checked_in_at','created_at'],
 'waiting_list'=>['entry_id','event_id','user_id','joined_at'],
 'staff_assignments'=>['assignment_id','staff_user_id','creator_user_id'],
 'creator_plans'=>['user_id','organisation_name','plan_code'],
 'creator_invoices'=>['invoice_id','user_id','billing_month','amount_cents','status','recorded_at'],
 'notifications'=>['notification_id','user_id','event_id','message','is_read','created_at'],
 'audit_log'=>['audit_id','actor_id','action','subject_id','created_at'],
 'account_tokens'=>['token_id','user_id','purpose','expires_at'],
 'user_mfa'=>['user_id','enabled_at'],
 'auth_rate_limits'=>['attempts','window_started'],
];
$table=queryString('table','events');if(!isset($tables[$table]))abortRequest(400,'Unknown table.');
$count=(int)$pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();$page=max(1,min(max(1,(int)ceil($count/25)),(int)queryString('page','1')));$offset=($page-1)*25;
$q=$pdo->prepare('SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_KEY FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION');$q->execute([$table]);$columns=$q->fetchAll();
$names='`'.implode('`,`',$tables[$table]).'`';$rows=$pdo->query("SELECT $names FROM `$table` ORDER BY ".$names." LIMIT 25 OFFSET $offset")->fetchAll();
audit($pdo,'developer.view');
require __DIR__.'/includes/header.php';?>
<div class="page-head"><div><p class="eyebrow">DEVELOPMENT ONLY · READ ONLY</p><h1>Under the hood.</h1></div><span class="pill">SQL explorer</span></div><p class="notice">Names, email addresses and business records are visible to authorized developer admins. Password hashes, MFA secrets, recovery codes, tokens, IP hashes and private payment references remain hidden.</p>
<div class="schema-layout"><nav class="schema-nav" aria-label="Database tables"><?php foreach($tables as $name=>$c):?><a class="<?=$name===$table?'selected':''?>" href="?table=<?=e($name)?>"><?=e($name)?></a><?php endforeach;?></nav><section><h2><?=e($table)?> <span class="muted">· <?=$count?> rows</span></h2><h3>Table structure</h3><div class="table-wrap"><table><thead><tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Row preview</th></tr></thead><tbody><?php foreach($columns as $c):?><tr><td class="code"><?=e($c['COLUMN_NAME'])?></td><td><?=e($c['COLUMN_TYPE'])?></td><td><?=e($c['IS_NULLABLE'])?></td><td><?=e($c['COLUMN_KEY'])?></td><td><?=in_array($c['COLUMN_NAME'],$tables[$table],true)?'Visible':'Hidden'?></td></tr><?php endforeach;?></tbody></table></div><h3>Rows · page <?=$page?></h3><div class="table-wrap"><table><thead><tr><?php foreach($tables[$table]as $c):?><th><?=e($c)?></th><?php endforeach;?></tr></thead><tbody><?php foreach($rows as $row):?><tr><?php foreach($row as $value):?><td><?=e($value??'NULL')?></td><?php endforeach;?></tr><?php endforeach;?><?php if(!$rows):?><tr><td colspan="<?=count($tables[$table])?>">This table is empty.</td></tr><?php endif;?></tbody></table></div><div class="pagination"><?php if($page>1):?><a class="button secondary" href="?table=<?=e($table)?>&page=<?=$page-1?>">← Previous</a><?php endif;?><?php if($offset+25<$count):?><a class="button secondary" href="?table=<?=e($table)?>&page=<?=$page+1?>">Next →</a><?php endif;?></div></section></div>
<?php require __DIR__.'/includes/footer.php';?>
