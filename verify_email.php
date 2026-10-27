<?php
require __DIR__.'/includes/bootstrap.php';$token=$_SERVER['REQUEST_METHOD']==='POST'?postString('token'):queryString('token');$message='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 $retry=consumeAuthLimits($pdo,[authBucketKey('verify-ip',$_SERVER['REMOTE_ADDR']??'')=>20]);
 if($retry){http_response_code(429);header('Retry-After: '.$retry);$message='Too many attempts. Try again later.';}
 else $message=consumeAccountToken($pdo,$token,'verify')?'Email verified. Thank you!':'This link is invalid, expired or already used.';
}
$pageTitle='Verify email';require __DIR__.'/includes/header.php';?>
<section class="compact-panel"><p class="eyebrow">ONE LAST THING</p><h1>Verify your email.</h1><?php if($message):?><p class="notice" role="status"><?=e($message)?></p><a class="button" href="<?=isLoggedIn()?'account.php':'login.php'?>">Continue</a><?php else:?><p>Confirm that this email address belongs to you.</p><form method="post"><?=csrfField()?><input type="hidden" name="token" value="<?=e($token)?>"><button>Verify my email</button></form><?php endif;?></section>
<?php require __DIR__.'/includes/footer.php';?>
