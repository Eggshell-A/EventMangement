<?php
require __DIR__.'/includes/bootstrap.php';$token=$_SERVER['REQUEST_METHOD']==='POST'?postString('token'):queryString('token');$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 $password=postString('password');
 $retry=consumeAuthLimits($pdo,[authBucketKey('reset-consume',$_SERVER['REMOTE_ADDR']??'')=>15]);
 if($retry){http_response_code(429);header('Retry-After: '.$retry);$error='Too many requests. Please try again later.';}
 elseif(!passwordMeetsPolicy($password))$error='Use 12+ characters, uppercase, lowercase, a number and a symbol (maximum 72 bytes).';
 elseif($password!==postString('password_confirm'))$error='Passwords do not match.';
 elseif(consumeAccountToken($pdo,$token,'reset',$password)){resetSession();flash('Password updated. Please log in again. All previous sessions have been invalidated.');redirect('login.php');}
 else $error='This link is invalid, expired or already used. Please request another.';
}
$pageTitle='Choose a new password';require __DIR__.'/includes/header.php';?>
<section class="compact-panel"><p class="eyebrow">A FRESH START</p><h1>Choose a new password.</h1><?php if($error):?><p class="flash error" role="alert"><?=e($error)?></p><?php endif;?>
<form method="post"><?=csrfField()?><input type="hidden" name="token" value="<?=e($token)?>"><label for="password">New password</label><div class="password-field"><input id="password" name="password" type="password" required minlength="12" maxlength="72" autocomplete="new-password"><button class="reveal" type="button" data-password-toggle="password" aria-label="Show password" aria-pressed="false">Show</button></div><p class="fine-print">12+ characters, uppercase, lowercase, a number and a symbol. Maximum 72 bytes.</p><label for="confirm">Confirm new password</label><input id="confirm" name="password_confirm" type="password" required autocomplete="new-password"><button class="wide">Update password</button></form><p><a href="forgot_password.php">Request another reset link</a></p></section>
<?php require __DIR__.'/includes/footer.php';?>
