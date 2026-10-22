<?php
require __DIR__ . '/includes/bootstrap.php';
$next = safeEventNext($_POST['next'] ?? $_GET['next'] ?? null);
if (isLoggedIn()) redirect(loginRedirectFor(currentUser(), $next));
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim(postString('email')));
    $password = postString('password');
    try {
        $retryAfter = loginRetryAfter($pdo, $email);
        if ($retryAfter > 0) {
            http_response_code(429);
            header('Retry-After: ' . $retryAfter);
            $error = 'Too many login attempts. Please try again later.';
        } else {
            $user = false;
            // The same email validation used at registration also prevents collation aliases
            // (such as Unicode accents) from bypassing the normalised account limiter.
            if (strlen($email) <= 254 && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $statement = $pdo->prepare('SELECT user_id,name,email,password_hash,role FROM users WHERE email=?');
                $statement->execute([$email]);
                $user = $statement->fetch();
            }
            // Verify a dummy hash for unknown accounts; do not expose account existence in errors.
            $hash = $user['password_hash'] ?? '$2y$12$GCW3vG.lIC1FyYOHhRnxHOB62k.MpvHbeFCgPq9JOkBY94BTOo37a';
            $valid = strlen($password) <= 1024 && password_verify($password, $hash);
            if ($user && $valid) {
                if (password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 12])) {
                    $pdo->prepare('UPDATE users SET password_hash=? WHERE user_id=? AND password_hash=?')->execute([
                        password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]), $user['user_id'], $hash,
                    ]);
                }
                $pdo->prepare('DELETE FROM auth_rate_limits WHERE bucket_key=?')->execute([authBucketKey('login-account', $email)]);
                establishLogin($user);
                flash('You are now logged in.');
                redirect(loginRedirectFor($user, $next));
            }
            $error = 'Invalid email or password.';
        }
    } catch (Throwable $exception) {
        serverError($exception);
    }
}
$pageTitle = 'Log in';
require __DIR__ . '/includes/header.php';
?>
<h1>Log in</h1>
<?php if ($next): ?><p class="notice">Log in to join this event. You will come back to it afterwards.</p><?php endif; ?>
<form method="post">
  <?= csrfField() ?>
  <?php if ($next): ?><input type="hidden" name="next" value="<?= e($next) ?>"><?php endif; ?>
  <?php if ($error): ?><p class="flash error"><?= e($error) ?></p><?php endif; ?>
  <label for="email">Email</label>
  <input id="email" type="email" name="email" required autocomplete="email" maxlength="254">
  <label for="password">Password</label>
  <input id="password" type="password" name="password" required autocomplete="current-password">
  <div class="form-actions"><button>Log in</button></div>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>
