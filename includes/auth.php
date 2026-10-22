<?php
declare(strict_types=1);

function authBucketKey(string $scope, string $identity): string {
    return hash('sha256', $scope . ':' . $identity);
}

/** Reserve attempts before verifying credentials, across sessions and PHP workers. */
function consumeAuthLimits(PDO $pdo, array $limits, int $windowSeconds = 900): int {
    $now = time();
    ksort($limits); // Stable lock order avoids cross-account/IP deadlocks.
    $pdo->beginTransaction();
    try {
        $rows = [];
        $retryAfter = 0;
        foreach ($limits as $key => $limit) {
            $statement = $pdo->prepare('INSERT INTO auth_rate_limits (bucket_key,attempts,window_started) VALUES (?,0,?) ON DUPLICATE KEY UPDATE bucket_key=bucket_key');
            $statement->execute([$key, $now]);
            $statement = $pdo->prepare('SELECT attempts,window_started FROM auth_rate_limits WHERE bucket_key=? FOR UPDATE');
            $statement->execute([$key]);
            $row = $statement->fetch();
            $start = (int) $row['window_started'];
            $attempts = (int) $row['attempts'];
            if ($start > $now || $now - $start >= $windowSeconds) {
                $start = $now;
                $attempts = 0;
            }
            if ($attempts >= $limit) {
                $retryAfter = max($retryAfter, $windowSeconds - ($now - $start));
            }
            $rows[$key] = [$attempts, $start];
        }
        foreach ($rows as $key => [$attempts, $start]) {
            $statement = $pdo->prepare('UPDATE auth_rate_limits SET attempts=?,window_started=? WHERE bucket_key=?');
            $statement->execute([$attempts + ($retryAfter === 0 ? 1 : 0), $start, $key]);
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
    // Keep old buckets bounded without storing raw email addresses or IP addresses.
    if (random_int(1, 100) === 1) {
        $pdo->prepare('DELETE FROM auth_rate_limits WHERE window_started < ?')->execute([$now - 86400]);
    }
    return $retryAfter;
}

function loginRetryAfter(PDO $pdo, string $email): int {
    // REMOTE_ADDR comes from the web server; never trust a client-supplied forwarding header.
    return consumeAuthLimits($pdo, [
        authBucketKey('login-account', $email) => 5,
        authBucketKey('login-ip', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown')) => 30,
    ]);
}

function registrationRetryAfter(PDO $pdo): int {
    return consumeAuthLimits($pdo, [authBucketKey('register-ip', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown')) => 10]);
}

function establishLogin(array $user): void {
    resetSession();
    unset($user['password_hash']);
    $_SESSION['user'] = $user;
    $_SESSION['security_version'] = 1;
    $_SESSION['authenticated_at'] = time();
    $_SESSION['last_activity'] = time();
    csrfToken();
}
