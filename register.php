<?php
require __DIR__.'/includes/bootstrap.php';
if(isLoggedIn())redirect(roleHome(currentUser()['role']));
$next=safeEventNext($_POST['next']??$_GET['next']??null);
$errors=[];$name=$email=$organisation='';$role='user';
if($_SERVER['REQUEST_METHOD']==='POST'){
 $name=trim(postString('name'));$email=strtolower(trim(postString('email')));$role=postString('role');$organisation=trim(postString('organisation'));$password=postString('password');
 $retry=registrationRetryAfter($pdo);if($retry){http_response_code(429);header('Retry-After: '.$retry);$errors[]='Too many attempts. Please try again later.';}
 if($name===''||!mb_check_encoding($name)||mb_strlen($name)>100)$errors[]='Enter a name of up to 100 characters.';
 if(strlen($email)>254||!filter_var($email,FILTER_VALIDATE_EMAIL))$errors[]='Enter a valid email address.';
 if(!passwordMeetsPolicy($password))$errors[]='Use at least 12 characters, with uppercase, lowercase, a number and a symbol (maximum 72 bytes).';
 if($password!==postString('password_confirm'))$errors[]='Passwords do not match.';
 if(!in_array($role,['user','creator'],true))$errors[]='Choose a valid account type.';
 if($role==='creator'&&($organisation===''||!mb_check_encoding($organisation)||mb_strlen($organisation)>120))$errors[]='Enter an organisation name of up to 120 characters.';
 if(!$errors){
  try{
   $pdo->beginTransaction();
   $pdo->prepare('INSERT INTO users(name,email,password_hash,role,is_school_email)VALUES(?,?,?,?,?)')->execute([$name,$email,password_hash($password,PASSWORD_BCRYPT,['cost'=>12]),$role,schoolEmail($email)?1:0]);
   $uid=(int)$pdo->lastInsertId();
   if($role==='creator')$pdo->prepare("INSERT INTO creator_plans(user_id,organisation_name,plan_code)VALUES(?,?,'external_monthly')")->execute([$uid,$organisation]);
   audit($pdo,'account.register',$uid);$pdo->commit();
   $sent=issueAccountToken($pdo,['user_id'=>$uid,'email'=>$email],'verify');
   flash($sent?'Account created. Check your email to verify your address, then log in.':'Account created. Email could not be sent; you can log in and request verification from Account.');
   redirect('login.php'.($next?'?next='.rawurlencode($next):''));
  }catch(PDOException $ex){if($pdo->inTransaction())$pdo->rollBack();if((int)($ex->errorInfo[1]??0)===1062)$errors[]='Unable to register this address. Try signing in or resetting your password.';else throw $ex;}
 }
}
$pageTitle='Create an account';require __DIR__.'/includes/header.php';
?>
<section class="auth-layout register-layout"><div class="auth-art"><img src="assets/art/city.svg" alt="Illustrated city after dark"><div><span class="eyebrow">MORE THAN ANOTHER SCROLL</span><h2>Be there.<br>Be part of it.</h2><p>Small discoveries. Unforgettable experiences.</p></div></div>
<div class="auth-panel"><a class="text-link" href="index.php">← Back to discover</a><p class="eyebrow">MAKE ROOM FOR SOMETHING GOOD</p><h1>Join the scene.</h1><p class="muted">One account for every experience.</p>
<form method="post" class="auth-form" data-registration>
<?=csrfField()?><?php if($next):?><input type="hidden" name="next" value="<?=e($next)?>"><?php endif;?>
<?php foreach($errors as $error):?><p class="flash error" role="alert"><?=e($error)?></p><?php endforeach;?>
<label for="name">Full name</label><input id="name" name="name" maxlength="100" required autocomplete="name" value="<?=e($name)?>" placeholder="What should we call you?">
<label for="email">Email address</label><input id="email" name="email" type="email" required maxlength="254" autocomplete="email" value="<?=e($email)?>" placeholder="you@example.com">
<div class="field-grid"><div><label for="password">Password</label><div class="password-field"><input id="password" name="password" type="password" required minlength="12" maxlength="72" autocomplete="new-password" aria-describedby="password-help"><button type="button" class="reveal" data-password-toggle="password" aria-label="Show password" aria-pressed="false">Show</button></div></div><div><label for="password-confirm">Confirm password</label><input id="password-confirm" name="password_confirm" type="password" required autocomplete="new-password" maxlength="72"></div></div>
<p id="password-help" class="fine-print">12+ characters with uppercase, lowercase, a number and a symbol. Maximum 72 bytes.</p><p class="field-feedback" data-password-feedback aria-live="polite"></p>
<label for="role">I'm here to</label><select id="role" name="role"><option value="user" <?=$role==='user'?'selected':''?>>Discover and join events</option><option value="creator" <?=$role==='creator'?'selected':''?>>Create and host events</option></select>
<div data-creator-fields><label for="organisation">Organisation / society <span class="muted">(creators only)</span></label><input id="organisation" name="organisation" maxlength="120" value="<?=e($organisation)?>"><p class="fine-print">External groups: HK$80 / month. Verified societies can request a fee waiver. Registration does not charge you.</p></div>
<button class="wide" type="submit">Create my account <span aria-hidden="true">↗</span></button>
</form><p class="auth-switch">Already a member? <a href="login.php">Log in</a></p></div></section>
<?php require __DIR__.'/includes/footer.php'; ?>
