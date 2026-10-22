<?php
require __DIR__ . '/includes/bootstrap.php';
if (isLoggedIn()) redirect('index.php');
$errors = [];
$name = $email = $role = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim(postString('name'));
    $email = strtolower(trim(postString('email')));
    $password = postString('password');
    $role = postString('role');
    try {
        $retryAfter = registrationRetryAfter($pdo);
        if ($retryAfter > 0) {
            http_response_code(429);
            header('Retry-After: ' . $retryAfter);
            $errors[] = 'Too many registration attempts. Please try again later.';
        }
        if ($name === '' || !mb_check_encoding($name, 'UTF-8') || mb_strlen($name, 'UTF-8') > 100) $errors[] = 'Enter a name of up to 100 characters.';
        if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
        if (!passwordMeetsPolicy($password)) $errors[] = 'Password must have 8–16 characters, including uppercase and lowercase letters, a number and a special character.';
        if (!in_array($role, ['user', 'creator'], true)) $errors[] = 'Choose a valid account type.';
        if (!$errors) {
            $statement = $pdo->prepare('INSERT INTO users (name,email,password_hash,role) VALUES (?,?,?,?)');
            $statement->execute([$name, $email, password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]), $role]);
            flash('Registration complete. Please log in.');
            redirect('login.php');
        }
    } catch (PDOException $exception) {
        if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
            $errors[] = 'An account with that email already exists.';
        } else {
            serverError($exception);
        }
    } catch (Throwable $exception) {
        serverError($exception);
    }
}
$pageTitle = 'Register';
require __DIR__ . '/includes/header.php';
?>
<h1>Create an account</h1>
<form method="post" novalidate>
  <?= csrfField() ?>
  <?php foreach ($errors as $error): ?><p class="flash error"><?= e($error) ?></p><?php endforeach; ?>
  <label for="name">Name</label><input id="name" name="name" required maxlength="100" value="<?= e($name) ?>">
  <label for="email">Email</label><input id="email" type="email" name="email" required maxlength="254" value="<?= e($email) ?>">
  <label for="password">Password</label><input id="password" type="password" name="password" required autocomplete="new-password" aria-describedby="password-help">
  <p id="password-help" class="muted">8–16 characters, with uppercase and lowercase letters, a number and a special character.</p>
  <label for="role">I am registering as</label>
  <select id="role" name="role" required><option value="user">User</option><option value="creator" <?= $role === 'creator' ? 'selected' : '' ?>>Event creator</option></select>
  <div class="form-actions"><button>Create account</button></div>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>
