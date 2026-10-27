<?php
declare(strict_types=1);
function sendAccountEmail(string $email, string $subject, string $body): bool {
    global $config;
    $transport = $config['mail_transport'] ?? '';
    if ($transport === 'log' && ($config['app_env'] ?? '') === 'development') {
        $dir = __DIR__ . '/../storage/mail';
        if (!is_dir($dir) && !mkdir($dir, 0700, true)) return false;
        $file = $dir . '/' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(8)) . '.txt';
        $ok = file_put_contents($file, "To: $email\nSubject: $subject\n\n$body", LOCK_EX) !== false;
        if ($ok) chmod($file, 0600);
        return $ok;
    }
    $from = (string)($config['mail_from'] ?? '');
    if ($transport !== 'mail' || !filter_var($from, FILTER_VALIDATE_EMAIL) || !filter_var($email, FILTER_VALIDATE_EMAIL)) return false;
    return mail($email, $subject, $body, ['From'=>$from,'Content-Type'=>'text/plain; charset=UTF-8']);
}
function issueAccountToken(PDO $pdo, array $user, string $purpose): bool {
    global $config;
    if (!in_array($purpose, ['reset','verify'], true)) throw new InvalidArgumentException();
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    $expires = date('Y-m-d H:i:s', time() + ($purpose === 'reset' ? 1800 : 86400));
    $pdo->beginTransaction();
    try {
        $pdo->prepare('SELECT user_id FROM users WHERE user_id=? FOR UPDATE')->execute([$user['user_id']]);
        $pdo->prepare('DELETE FROM account_tokens WHERE user_id=? AND purpose=?')->execute([$user['user_id'],$purpose]);
        $pdo->prepare('INSERT INTO account_tokens (user_id,purpose,token_hash,expires_at) VALUES (?,?,?,?)')->execute([$user['user_id'],$purpose,$hash,$expires]);
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    $path = $purpose === 'reset' ? 'reset_password.php' : 'verify_email.php';
    $url = rtrim($config['base_url'], '/') . '/' . $path . '?token=' . $token;
    $subject = $purpose === 'reset' ? 'Reset your Fairboard password' : 'Verify your Fairboard email';
    $body = "$subject\n\nOpen this link and confirm the action:\n$url\n\n" . ($purpose === 'reset' ? 'Expires in 30 minutes.' : 'Expires in 24 hours.') . "\nIf you did not request this, ignore this message.";
    if (sendAccountEmail($user['email'], $subject, $body)) return true;
    $pdo->prepare('DELETE FROM account_tokens WHERE token_hash=?')->execute([$hash]);
    error_log('Fairboard: account email delivery failed; check mail transport.');
    return false;
}
function consumeAccountToken(PDO $pdo, string $raw, string $purpose, ?string $password = null): bool {
    if (!preg_match('/^[a-f0-9]{64}$/D', $raw)) return false;
    $hash = hash('sha256',$raw);
    // Always lock the user before tokens, matching token issuance and password changes.
    $q=$pdo->prepare('SELECT user_id FROM account_tokens WHERE token_hash=? AND purpose=?');$q->execute([$hash,$purpose]);
    $uid=$q->fetchColumn();if (!$uid) return false;
    $pdo->beginTransaction();
    try {
        $q=$pdo->prepare('SELECT * FROM users WHERE user_id=? AND is_active=1 FOR UPDATE');$q->execute([$uid]);$user=$q->fetch();
        $q=$pdo->prepare('SELECT token_id FROM account_tokens WHERE token_hash=? AND purpose=? AND expires_at>NOW() FOR UPDATE');$q->execute([$hash,$purpose]);$token=$q->fetch();
        if (!$user || !$token) { $pdo->rollBack(); return false; }
        if ($purpose === 'reset' && $password !== null && passwordMeetsPolicy($password)) {
            $pdo->prepare('UPDATE users SET password_hash=?,auth_version=auth_version+1 WHERE user_id=?')->execute([password_hash($password,PASSWORD_BCRYPT,['cost'=>12]),$uid]);
            $pdo->prepare('DELETE FROM account_tokens WHERE user_id=? AND purpose=?')->execute([$uid,'reset']);
            audit($pdo,'password.reset',(int)$uid);
        } elseif ($purpose === 'verify') {
            $pdo->prepare('UPDATE users SET email_verified_at=NOW(),school_email_verified=is_school_email,verified_at=NOW() WHERE user_id=?')->execute([$uid]);
            $pdo->prepare('DELETE FROM account_tokens WHERE token_id=?')->execute([$token['token_id']]);
            audit($pdo,'email.verified',(int)$uid);
        } else { $pdo->rollBack(); return false; }
        $pdo->commit();return true;
    } catch(Throwable $e) { if($pdo->inTransaction())$pdo->rollBack();throw $e; }
}
