<?php if (!isset($pageTitle)) { $pageTitle = 'EventLink'; } ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> | EventLink</title><link rel="stylesheet" href="assets/style.css">
  <?php if (!empty($extraStylesheet)): ?><link rel="stylesheet" href="<?= e($extraStylesheet) ?>"><?php endif; ?>
</head>
<body>
<header class="site-header"><div class="container nav"><a class="brand" href="index.php">EventLink</a><nav aria-label="Main navigation">
<a href="index.php">Events</a>
<?php if (isLoggedIn()): ?>
  <?php if (isRole('attendee')): ?><a href="my_bookings.php">My bookings</a><?php endif; ?>
  <?php if (isRole('organiser')): ?><a href="dashboard.php">Dashboard</a><a href="event_form.php">Create event</a><?php endif; ?>
  <?php if (isRole('admin')): ?><a href="admin.php">Admin</a><?php endif; ?>
  <form class="inline-form" method="post" action="logout.php"><button class="link-button" type="submit">Log out</button></form>
<?php else: ?><a href="login.php">Log in</a><a class="button small" href="register.php">Register</a><?php endif; ?>
</nav></div></header><main class="container"><?php showFlashes(); ?>
