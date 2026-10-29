<?php
require __DIR__.'/includes/bootstrap.php';require_once __DIR__.'/includes/staff_invites.php';
$raw=$_SERVER['REQUEST_METHOD']==='POST'?postString('token'):queryString('token');$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    enforceActionLimit($pdo,'staff-accept',10,hash('sha256',$raw));
    $password=postString('password');
    if(!passwordMeetsPolicy($password)||$password!==postString('password_confirm'))$error='Passwords must match: 12+ characters, uppercase, lowercase, a number and a symbol; maximum 72 bytes.';
    elseif(acceptStaffInvite($pdo,$raw,$password)){resetSession();flash('Staff account created. Log in and set up your authenticator to continue.');redirect('login.php');}
    else $error='Invitation invalid, expired, already used, or this email is already registered. Contact the administrator.';
}
$pageTitle='Accept staff invitation';require __DIR__.'/includes/header.php';?>
<section class="compact-panel"><p class="eyebrow">YOU'RE INVITED</p><h1>Join the staff team.</h1><p>Choose a password. You will set up an authenticator after login.</p><?php if($error):?><p class="flash error" role="alert"><?=e($error)?></p><?php endif;?><form method="post"><?=csrfField()?><input type="hidden" name="token" value="<?=e($raw)?>"><label for="password">Your new password</label><input id="password" name="password" type="password" minlength="12" maxlength="72" required autocomplete="new-password"><label for="confirm">Confirm password</label><input id="confirm" name="password_confirm" type="password" required autocomplete="new-password"><button class="wide">Create my staff account</button></form></section>
<?php require __DIR__.'/includes/footer.php';?>
