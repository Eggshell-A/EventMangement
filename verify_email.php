<?php
require __DIR__ . '/includes/bootstrap.php';

header('X-Robots-Tag: noindex, nofollow');

$message = '';
$showForm = false;
$activeChallenge = '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    $challenge = postString('challenge');
    $retry = consumeAuthLimits(
        $pdo,
        [authBucketKey('verify-ip', $_SERVER['REMOTE_ADDR'] ?? '') => 20]
    );

    $pending = $_SESSION['pending_email_verifications'] ?? [];
    $entry = is_array($pending) && preg_match('/^[a-f0-9]{32}$/D', $challenge)
        ? ($pending[$challenge] ?? null)
        : null;

    if ($retry) {
        http_response_code(429);
        header('Retry-After: ' . $retry);
        $message = 'Too many attempts. Try again later.';
        if (
            is_array($entry)
            && isset($entry['token_hash'], $entry['expires_at'])
            && is_string($entry['token_hash'])
            && preg_match('/^[a-f0-9]{64}$/D', $entry['token_hash'])
            && is_int($entry['expires_at'])
            && $entry['expires_at'] >= time()
        ) {
            $showForm = true;
            $activeChallenge = $challenge;
        }
    } else {
        $tokenHash = '';
        if (
            is_array($entry)
            && isset($entry['token_hash'], $entry['expires_at'])
            && is_string($entry['token_hash'])
            && preg_match('/^[a-f0-9]{64}$/D', $entry['token_hash'])
            && is_int($entry['expires_at'])
            && $entry['expires_at'] >= time()
        ) {
            $tokenHash = $entry['token_hash'];
        }

        if (is_array($pending)) {
            unset($pending[$challenge]);
            $_SESSION['pending_email_verifications'] = $pending;
        }

        $verified = $tokenHash !== ''
            && consumeAccountTokenDigest($pdo, $tokenHash, 'verify');

        header(
            'Location: verify_email.php?status=' . ($verified ? 'success' : 'invalid'),
            true,
            303
        );
        exit;
    }
} else {
    $status = queryString('status');

    if ($status === 'success') {
        $message = 'Email verified. Thank you!';
    } elseif ($status === 'invalid') {
        $message = 'This link is invalid, expired or already used.';
    } elseif ($status !== '') {
        $message = 'Invalid verification link.';
    } else {
        $rawToken = queryString('token');

        if ($rawToken !== '') {
            if (!preg_match('/^[a-f0-9]{64}$/D', $rawToken)) {
                $message = 'This link is invalid, expired or already used.';
            } else {
                // Store only the token digest in a short-lived, session-bound challenge.
                $pending = $_SESSION['pending_email_verifications'] ?? [];
                if (!is_array($pending)) $pending = [];

                foreach ($pending as $key => $item) {
                    if (
                        !is_array($item)
                        || !isset($item['token_hash'], $item['expires_at'])
                        || !is_string($item['token_hash'])
                        || !preg_match('/^[a-f0-9]{64}$/D', $item['token_hash'])
                        || !is_int($item['expires_at'])
                        || $item['expires_at'] < time()
                    ) {
                        unset($pending[$key]);
                    }
                }

                while (count($pending) >= 5) {
                    array_shift($pending);
                }

                $challenge = bin2hex(random_bytes(16));
                $pending[$challenge] = [
                    'token_hash' => hash('sha256', $rawToken),
                    'expires_at' => time() + 600,
                ];
                $_SESSION['pending_email_verifications'] = $pending;

                // The bearer token is removed from the browser URL before the form is shown.
                header(
                    'Location: verify_email.php?challenge=' . $challenge,
                    true,
                    303
                );
                exit;
            }
        } else {
            $challenge = queryString('challenge');
            $pending = $_SESSION['pending_email_verifications'] ?? [];
            $entry = is_array($pending) && preg_match('/^[a-f0-9]{32}$/D', $challenge)
                ? ($pending[$challenge] ?? null)
                : null;

            if (
                is_array($entry)
                && isset($entry['token_hash'], $entry['expires_at'])
                && is_string($entry['token_hash'])
                && preg_match('/^[a-f0-9]{64}$/D', $entry['token_hash'])
                && is_int($entry['expires_at'])
                && $entry['expires_at'] >= time()
            ) {
                $showForm = true;
                $activeChallenge = $challenge;
            } else {
                $message = 'Invalid or expired verification link. Open the latest link from your email.';
            }
        }
    }
}

$pageTitle = 'Verify email';
require __DIR__ . '/includes/header.php';
?>
<section class="compact-panel">
    <p class="eyebrow">ONE LAST THING</p>
    <h1>Verify your email.</h1>

    <?php if ($message): ?>
        <p class="notice" role="status"><?= e($message) ?></p>
    <?php endif; ?>

    <?php if ($showForm): ?>
        <p>Confirm that this email address belongs to you.</p>
        <p class="fine-print">The verification link expires 10 minutes after it was sent. Opening the link alone does not verify your email.</p>
        <form method="post" action="verify_email.php">
            <?= csrfField() ?>
            <input type="hidden" name="challenge" value="<?= e($activeChallenge) ?>">
            <button type="submit">Verify my email</button>
        </form>
    <?php else: ?>
        <a class="button" href="<?= isLoggedIn() ? 'account.php' : 'login.php' ?>">Continue</a>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
