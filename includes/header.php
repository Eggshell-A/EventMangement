<?php
$pageTitle=$pageTitle??'Discover';$currentPage=basename($_SERVER['SCRIPT_NAME']??'');$unread=0;
if(isLoggedIn()){$navNotificationQuery=$pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0');$navNotificationQuery->execute([currentUser()['user_id']]);$unread=(int)$navNotificationQuery->fetchColumn();}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="dark"><meta name="csrf-token" content="<?=e(csrfToken())?>"><meta name="description" content="Discover community events, book your next experience and bring people together with Fairboard."><title><?=e($pageTitle)?> · Fairboard</title><link rel="icon" href="assets/favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="assets/style.css"><?php if(!empty($extraStylesheet)):?><link rel="stylesheet" href="<?=e($extraStylesheet)?>"><?php endif;?><script src="assets/app.js" defer></script><script src="assets/ticket.js" defer></script></head>
<body><a class="skip-link" href="#main">Skip to content</a><header class="site-header"><div class="nav-shell"><a class="brand" href="index.php" aria-label="Fairboard home"><span class="brand-icon" aria-hidden="true">f</span>fairboard<span class="brand-dot">.</span></a><button type="button" class="menu-toggle secondary" data-menu-toggle aria-expanded="false" aria-controls="main-nav">Menu</button><nav id="main-nav" aria-label="Main navigation"><a class="<?=$currentPage==='index.php'?'active':''?>" href="index.php">Discover</a>
<?php if(isLoggedIn()):?>
<?php if(isRole('user')):?><a href="my_bookings.php" class="<?=$currentPage==='my_bookings.php'?'active':''?>">My tickets</a><?php endif;?>
<?php if(isRole('creator')):?><a href="dashboard.php">My events</a><a href="event_form.php">Create event</a><?php endif;?>
<?php if(isRole('staff')):?><a href="staff.php">Assigned events</a><?php endif;?>
<?php if(isRole('admin')):?><a href="admin.php">Admin</a><?php endif;?>
<?php if(in_array(currentUser()['role'],['creator','staff'],true)):?><a href="scan.php">Scanner</a><?php endif;?>
<?php if(isDeveloper()):?><a class="dev-badge" href="developer.php">SQL <span>DEV</span></a><?php endif;?>
<a href="notifications.php" aria-label="Updates<?= $unread?' · '.$unread.' unread':''?>">Updates<?php if($unread):?><span class="count"><?=min($unread,99)?></span><?php endif;?></a><a class="avatar" href="account.php" aria-label="Your account"><?=e(mb_strtoupper(mb_substr(currentUser()['name'],0,1)))?></a><form class="inline-form" method="post" action="logout.php"><?=csrfField()?><button class="nav-logout" type="submit">Log out</button></form>
<?php else:?><span class="nav-divider"></span><a href="login.php">Log in</a><a class="button small" href="register.php">Join Fairboard ↗</a><?php endif;?></nav></div></header>
<main id="main" class="<?=empty($bareMain)?'container':'full-main'?>"><div class="flash-container"><?php showFlashes();?></div>
