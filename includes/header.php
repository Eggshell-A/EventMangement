<?php if (!isset($pageTitle)) { $pageTitle = 'Fairboard'; } ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> | Fairboard</title>
  <link rel="stylesheet" href="assets/style.css">
  <?php if (!empty($extraStylesheet)): ?><link rel="stylesheet" href="<?= e($extraStylesheet) ?>"><?php endif; ?>
</head>
<body>
<header class="site-header">
  <div class="container nav">
    <a class="brand" href="index.php">Fairboard</a>
    <nav aria-label="Main navigation">
      <a href="index.php">Events</a>
      <?php if (isLoggedIn()): ?>
        <?php if (isRole('user')): ?><a href="my_bookings.php">My bookings</a><?php endif; ?>
        <?php if (isRole('creator')): ?><a href="dashboard.php">My events</a><a href="event_form.php">Create event</a><?php endif; ?>
        <?php if (isRole('staff')): ?><a href="staff.php">Staff</a><?php endif; ?>
        <?php if (isRole('admin')): ?><a href="admin.php">Admin</a><?php endif; ?>
        <form class="inline-form" method="post" action="logout.php">
          <button class="nav-logout" type="submit">Log out</button>
        </form>
      <?php else: ?>
        <a href="login.php">Log in</a>
        <a class="button small" href="register.php">Register</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main<?php if (empty($bareMain)): ?> class="container"<?php endif; ?>>
<?php showFlashes(); ?>
