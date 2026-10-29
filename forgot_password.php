<?php
require __DIR__.'/includes/bootstrap.php';$message='';$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 $email=strtolower(trim(postString('email')));
 $retry=consumeAuthLimits($pdo,[authBucketKey('reset-ip',$_SERVER['REMOTE_ADDR']??'')=>10,authBucketKey('reset-email',$email)=>3]);
 if($retry){http_response_code(429);header('Retry-After: '.$retry);$error='Too many requests. Please try again later.';}
 else{
  $user=false;
  if(strlen($email)<=254 && filter_var($email,FILTER_VALIDATE_EMAIL)){ $q=$pdo->prepare('SELECT user_id,email FROM users WHERE email=? AND is_active=1');$q->execute([$email]);$user=$q->fetch(); }
  if($user)issueAccountToken($pdo,$user,'reset');
  $message='If an active account matches that email, a reset link has been sent. It expires in 30 minutes.';
 }
}
$pageTitle='Reset your password';require __DIR__.'/includes/header.php';?>
<section class="compact-panel"><p class="eyebrow">LET'S GET YOU BACK IN</p><h1>Forgot your password?</h1><p class="muted">Enter your email and we'll send you a one-use reset link.</p>
<?php if($message):?><p class="notice" role="status"><?=e($message)?></p><?php endif;?><?php if($error):?><p class="flash error"><?=e($error)?></p><?php endif;?>
<form method="post"><?=csrfField()?><label for="email">Email address</label><input type="email" id="email" name="email" required maxlength="254" autocomplete="email"><button class="wide">Send reset link</button></form><p><a href="login.php">← Back to log in</a></p></section>
<?php require __DIR__.'/includes/footer.php';?>
