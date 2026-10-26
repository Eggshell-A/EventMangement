<?php
declare(strict_types=1);

require __DIR__ . '/../includes/security.php';

$failures = [];

function expectSecurity(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        $failures[] = $message;
    }
}

$_SESSION = [];
$firstToken = csrfToken();
$secondToken = csrfToken();
expectSecurity($firstToken === $secondToken, 'CSRF token must remain stable for the session.');
expectSecurity((bool) preg_match('/^[a-f0-9]{64}$/', $firstToken), 'CSRF token must be a 256-bit hexadecimal value.');
expectSecurity(csrfTokenValid($firstToken), 'The current CSRF token must validate.');
expectSecurity(!csrfTokenValid('wrong-token'), 'A different CSRF token must be rejected.');
expectSecurity(!csrfTokenValid(null), 'A missing CSRF token must be rejected.');

expectSecurity(!sessionIsExpired(['last_activity' => 1_000], 2_799), 'A session just inside 30 minutes must remain valid.');
expectSecurity(sessionIsExpired(['last_activity' => 1_000], 2_801), 'A session beyond 30 minutes must expire.');
expectSecurity(!sessionIsExpired([], 5_000), 'A session without activity history must not expire immediately.');

$_SESSION = [];
$key = 'person@example.com|127.0.0.1';
for ($attempt = 0; $attempt < 5; $attempt++) {
    expectSecurity(loginAllowed($key, 10_000), 'Login must remain available before five failures.');
    recordLoginFailure($key, 10_000 + $attempt);
}
expectSecurity(!loginAllowed($key, 10_100), 'Five failures must throttle further attempts during the window.');
expectSecurity(loginAllowed($key, 10_004 + LOGIN_FAILURE_WINDOW + 1), 'Login must be available after the throttle window following the most recent failure.');
clearLoginFailures($key);
expectSecurity(loginAllowed($key, 10_100), 'A successful login must clear the throttle.');

expectSecurity(accountIsActive(['is_active' => 1]), 'An active account must be allowed.');
expectSecurity(!accountIsActive(['is_active' => 0]), 'A disabled account must be denied.');
expectSecurity(accountIsActive([]), 'Legacy account data without the field must remain active during migration.');

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "ok\n";
