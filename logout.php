<?php
require __DIR__ . '/includes/bootstrap.php';
requirePost();
resetSession();
flash('You have been logged out.');
redirect('index.php');
