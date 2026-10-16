<?php
require __DIR__ . '/includes/bootstrap.php';
$next = safeEventNext($_POST['next'] ?? $_GET['next'] ?? null);
if (isLoggedIn()) {
    redirect(loginRedirectFor(currentUser(), $next));
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $statement = $pdo->prepare('SELECT user_id, name, email, password_hash, role FROM users WHERE email=?');
    $statement->execute([strtolower(trim($_POST['email'] ?? ''))]);
    $user = $statement->fetch();
    if ($user && password_verify($_POST['password'] ?? '', $user['password_hash'])) {
        session_regenerate_id(true);
        unset($user['password_hash']);
        $_SESSION['user'] = $user;
        flash('You are now logged in.');
        redirect(loginRedirectFor($user, $next));
    }
    $error = 'Invalid email or password.';
}
$pageTitle = 'Log in';
require __DIR__ . '/includes/header.php';
?>
<h1>Log in</h1>
<?php if ($next): ?><p class="notice">Log in to join this event. You will come back to it afterwards.</p><?php endif; ?>
<form method="post">
<?php if ($error): ?><p class="flash error"><?= e($error) ?></p><?php endif; ?>
<?php if ($next): ?><input type="hidden" name="next" value="<?= e($next) ?>"><?php endif; ?>
<label for="email">Email</label>
<input id="email" type="email" name="email" required autocomplete="email" value="<?= e($_POST['email'] ?? '') ?>">
<label for="password">Password</label>
<input id="password" type="password" name="password" required autocomplete="current-password">
<div class="form-actions"><button>Log in</button></div>
</form>
<?php require __DIR__ . '/includes/footer.php';
