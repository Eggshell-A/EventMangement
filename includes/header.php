<?php
if (!isset($pageTitle)) { $pageTitle = 'Fairboard'; }
$currentPage = basename($_SERVER['PHP_SELF'] ?? 'index.php');
function navCurrent(array $pages, string $currentPage): string { return in_array($currentPage, $pages, true) ? ' aria-current="page"' : ''; }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#17233b">
  <title><?= e($pageTitle) ?> | Fairboard</title>
  <link rel="stylesheet" href="assets/style.css">
  <?php if (!empty($extraStylesheet)): ?><link rel="stylesheet" href="<?= e($extraStylesheet) ?>"><?php endif; ?>
  <script src="assets/home.js" defer></script>
</head>
<body>
<a class="skip-link" href="#main-content">Skip to main content</a>
<header class="site-header">
  <div class="container nav">
    <a class="brand" href="index.php" aria-label="Fairboard home"><span aria-hidden="true">F</span>Fairboard</a>
    <nav aria-label="Main navigation">
      <a href="index.php"<?= navCurrent(['index.php', 'event.php'], $currentPage) ?>>Explore</a>
      <?php if (isLoggedIn()): ?>
        <?php if (isRole('user')): ?><a href="my_bookings.php"<?= navCurrent(['my_bookings.php', 'booked.php'], $currentPage) ?>>My activity</a><?php endif; ?>
        <?php if (isRole('creator')): ?><a href="dashboard.php"<?= navCurrent(['dashboard.php', 'attendees.php'], $currentPage) ?>>My events</a><a href="event_form.php"<?= navCurrent(['event_form.php'], $currentPage) ?>>Create</a><?php endif; ?>
        <?php if (isRole('staff')): ?><a href="staff.php"<?= navCurrent(['staff.php', 'attendees.php', 'event_form.php'], $currentPage) ?>>Assigned events</a><?php endif; ?>
        <?php if (isRole('admin')): ?><a href="admin.php"<?= navCurrent(['admin.php'], $currentPage) ?>>Admin</a><?php endif; ?>
        <span class="account-chip" title="<?= e(ucfirst((string) currentUser()['role'])) ?> account"><?= e(currentUser()['name']) ?></span>
        <form class="inline-form" method="post" action="logout.php">
          <?= csrfField() ?>
          <button class="nav-logout" type="submit">Log out</button>
        </form>
      <?php else: ?>
        <a href="login.php"<?= navCurrent(['login.php'], $currentPage) ?>>Log in</a>
        <a class="button small" href="register.php"<?= navCurrent(['register.php'], $currentPage) ?>>Create account</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main id="main-content"<?php if (empty($bareMain)): ?> class="container"<?php endif; ?>>
<?php showFlashes(); ?>
