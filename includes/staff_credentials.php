<?php
// Only the successful administrator POST receives this page; credentials never enter session/mail/log storage.
if(!isset($credentials)||!isRole('admin'))abortRequest(404,'Page not found.');
$pageTitle='Staff account credentials';require __DIR__.'/header.php';?>
<section class="compact-panel"><p class="eyebrow">STAFF ACCOUNT READY</p><h1>Save these sign-in details.</h1><p>The account is ready to sign in. Give these details directly to the staff member through a private channel.</p>
<label for="staff-login-email">Login email ID</label><input id="staff-login-email" readonly value="<?=e($credentials['email'])?>">
<label for="temporary-password">Temporary password</label><div class="password-field"><input id="temporary-password" type="password" readonly autocomplete="off" value="<?=e($credentials['temporary_password'])?>"><button type="button" class="reveal" data-password-toggle="temporary-password" aria-label="Show temporary password" aria-pressed="false">Show</button></div>
<p class="notice">This password is shown only now. Staff must choose a different password on first sign-in and set up MFA before using staff features. Existing MFA stays enabled after a password reset.</p>
<p>This email ID is for signing in to Fairboard. It does not need an email inbox.</p><a class="button wide" href="admin.php">Back to admin</a></section>
<?php require __DIR__.'/footer.php';?>
