<?php
require __DIR__ . '/includes/bootstrap.php';
$next = safeEventNext($_POST['next'] ?? $_GET['next'] ?? null);
if (isLoggedIn()) {
    redirect(loginRedirectFor(currentUser(), $next));
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();
    $email = strtolower(trim($_POST['email'] ?? ''));
    $loginKey = $email . '|' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    if (!loginAllowed($loginKey)) {
        $error = 'Too many login attempts. Please wait 15 minutes and try again.';
    }
    $statement = $pdo->prepare('SELECT user_id, name, email, password_hash, role, is_active, failed_login_count, last_failed_login_at FROM users WHERE email=?');
    $statement->execute([$email]);
    $user = $statement->fetch();
    $databaseThrottled = $user
        && (int) $user['failed_login_count'] >= LOGIN_FAILURE_LIMIT
        && !empty($user['last_failed_login_at'])
        && strtotime((string) $user['last_failed_login_at']) >= time() - LOGIN_FAILURE_WINDOW;
    if ($error === '' && !$databaseThrottled && $user && accountIsActive($user) && password_verify($_POST['password'] ?? '', $user['password_hash'])) {
        session_regenerate_id(true);
        $pdo->prepare('UPDATE users SET failed_login_count = 0, last_failed_login_at = NULL WHERE user_id = ?')->execute([$user['user_id']]);
        clearLoginFailures($loginKey);
        unset($user['password_hash']);
        unset($user['failed_login_count'], $user['last_failed_login_at']);
        $_SESSION['user'] = $user;
        touchSession();
        flash('You are now logged in.');
        redirect(loginRedirectFor($user, $next));
    }
    if ($error === '') {
        recordLoginFailure($loginKey);
        if ($user) {
            $pdo->prepare('UPDATE users SET failed_login_count = IF(last_failed_login_at IS NULL OR last_failed_login_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE), 1, failed_login_count + 1), last_failed_login_at = NOW() WHERE user_id = ?')->execute([$user['user_id']]);
        }
        $error = $databaseThrottled
            ? 'Too many login attempts. Please wait 15 minutes and try again.'
            : 'Invalid email or password.';
    }
}
$pageTitle = 'Log in';
require __DIR__ . '/includes/header.php';
?>
<h1>Log in</h1>
<?php if ($next): ?><p class="notice">Log in to join this event. You will come back to it afterwards.</p><?php endif; ?>
<form method="post">
<?= csrfField() ?>
<?php if ($error): ?><p class="flash error"><?= e($error) ?></p><?php endif; ?>
<?php if ($next): ?><input type="hidden" name="next" value="<?= e($next) ?>"><?php endif; ?>
<label for="email">Email</label>
<input id="email" type="email" name="email" required autocomplete="email" value="<?= e($_POST['email'] ?? '') ?>">
<label for="password">Password</label>
<input id="password" type="password" name="password" required autocomplete="current-password">
<div class="form-actions"><button>Log in</button></div>
</form>
<?php require __DIR__ . '/includes/footer.php';
