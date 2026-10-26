<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
if (isLoggedIn()) {
    redirect('index.php');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();
    $name = trim($_POST['name'] ?? '');
    $email = trim(strtolower($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';

    if ($name === '' || strlen($name) > 100) {
        $errors[] = 'Enter a name of up to 100 characters.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }
    if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        $errors[] = 'Password must have at least 8 characters, including letters and numbers.';
    }
    if (!in_array($role, ['user', 'creator'], true)) {
        $errors[] = 'Choose a valid account type.';
    }

    if (!$errors) {
        try {
            $statement = $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)');
            $statement->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
            flash('Registration complete. Please log in.');
            redirect('login.php');
        } catch (PDOException $exception) {
            $errors[] = 'An account with that email already exists.';
        }
    }
}

$pageTitle = 'Register';
require __DIR__ . '/includes/header.php';
?>
<h1>Create an account</h1>
<form method="post" novalidate>
  <?= csrfField() ?>
  <?php foreach ($errors as $error): ?>
    <p class="flash error"><?= e($error) ?></p>
  <?php endforeach; ?>
  <label for="name">Name</label>
  <input id="name" name="name" required value="<?= e($_POST['name'] ?? '') ?>">
  <label for="email">Email</label>
  <input id="email" type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>">
  <label for="password">Password</label>
  <input id="password" type="password" name="password" required aria-describedby="password-help">
  <p id="password-help" class="muted">At least 8 characters, with letters and numbers.</p>
  <label for="role">I am registering as</label>
  <select id="role" name="role" required>
    <option value="user" <?= ($_POST['role'] ?? 'user') === 'user' ? 'selected' : '' ?>>User</option>
    <option value="creator" <?= ($_POST['role'] ?? '') === 'creator' ? 'selected' : '' ?>>Event creator</option>
  </select>
  <div class="form-actions"><button>Create account</button></div>
</form>
<?php require __DIR__ . '/includes/footer.php';
