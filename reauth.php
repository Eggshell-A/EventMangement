<?php
require __DIR__.'/includes/bootstrap.php'; requireLogin(); $error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (reauthenticate($pdo, postString('password'), trim(postString('code')))) { flash('Identity confirmed for 10 minutes. Repeat your action.'); redirect(isRole('admin')?'admin.php':'account.php'); }
    $error='Password or verification code is incorrect.';
}
$pageTitle='Confirm your identity';require __DIR__.'/includes/header.php';?>
<section class="compact-panel"><h1>Confirm it's you.</h1><?php if($error):?><p class="flash error"><?=e($error)?></p><?php endif;?><form method="post"><?=csrfField()?><label for="password">Current password</label><input id="password" name="password" type="password" required maxlength="72" autocomplete="current-password"><?=secondFactorField()?><button class="wide">Confirm identity</button></form></section>
<?php require __DIR__.'/includes/footer.php';?>
