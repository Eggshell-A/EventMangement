<?php
// Compatibility entry point: the application now uses persistent DB rate limits.
// HTTP tests cover cross-session throttling; these are the current pure checks.
require __DIR__ . '/security_unit.php';
