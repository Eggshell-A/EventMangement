<?php
// Use: php -S 127.0.0.1:8080 router.php (local development only).
$path=rawurldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH) ?: '/');
if (str_contains($path,'..') || preg_match('~(?:^|/)\.[^/]*|^/(?:storage|includes|tools|tests|database|migrations|docs|vendor)(?:/|$)|(?:^|/)(?:config(?:\.example)?\.php|composer\.(?:json|lock)|router\.php|security_test_results\.json)$|\.(?:sql|md|pem|log|txt|py|cjs)$~i',$path)) {http_response_code(404);exit;}
return false;
