<?php
declare(strict_types=1);

function requestUsesHttps(): bool {
    return (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

function resetSession(): void {
    $_SESSION = [];
    session_regenerate_id(true);
}

function startSecureSession(array $config): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name('FAIRBOARDSESSID');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        $secure = requestUsesHttps() || ($config['session_secure'] ?? false)
            || strtolower((string) parse_url($config['base_url'], PHP_URL_SCHEME)) === 'https';
        session_set_cookie_params([
            'lifetime' => 0, 'path' => rtrim((string)(parse_url($config['base_url'], PHP_URL_PATH) ?? ''), '/') . '/', 'secure' => $secure,
            'httponly' => true, 'samesite' => 'Lax',
        ]);
        session_start();
    }

    if (isset($_SESSION['user'])) {
        // Upgrade pre-release authenticated sessions so the browser receives the new cookie flags.
        if (($_SESSION['security_version'] ?? 0) !== 1) {
            session_regenerate_id(true);
            unset($_SESSION['csrf_token']);
            $_SESSION['security_version'] = 1;
        }
        $now = time();
        $idleTimeout = max(60, (int) ($config['session_idle_timeout'] ?? 1800));
        $absoluteTimeout = max($idleTimeout, (int) ($config['session_absolute_timeout'] ?? 28800));
        $lastActivity = (int) ($_SESSION['last_activity'] ?? $now);
        $authenticatedAt = (int) ($_SESSION['authenticated_at'] ?? $now);
        if ($now - $lastActivity >= $idleTimeout || $now - $authenticatedAt >= $absoluteTimeout) {
            resetSession();
            $_SESSION['flash'][] = ['message' => 'Your session has expired. Please log in again.', 'type' => 'error'];
        } else {
            $_SESSION['authenticated_at'] = $authenticatedAt;
            if (basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'ticket_qr.php') $_SESSION['last_activity'] = $now;
        }
    }
}

function sendSecurityHeaders(array $config): void {
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; object-src 'none'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header('Permissions-Policy: camera=(self), microphone=(), geolocation=()');
    header('Cache-Control: no-store, private');
    $hsts = max(0, (int) ($config['hsts_max_age'] ?? 0));
    if ($hsts > 0 && (requestUsesHttps() || strtolower((string) parse_url($config['base_url'], PHP_URL_SCHEME)) === 'https')) {
        header('Strict-Transport-Security: max-age=' . $hsts);
    }
}

function csrfToken(): string {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function requireValidCsrf(): void {
    $token = $_POST['csrf_token'] ?? null;
    $expected = $_SESSION['csrf_token'] ?? null;
    if (!is_string($token) || !is_string($expected) || !hash_equals($expected, $token)) {
        securityEvent('request.csrf_rejected');
        http_response_code(403);
        exit('Invalid or expired form. Reload the page and try again.');
    }
}

function requirePost(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        header('Allow: POST');
        http_response_code(405);
        exit('Method not allowed.');
    }
}

function postString(string $name, string $default = ''): string {
    $value = $_POST[$name] ?? $default;
    if (!is_string($value)) {
        http_response_code(400);
        exit('Invalid form field.');
    }
    return $value;
}

function postPositiveInt(string $name): int {
    $value = filter_var(postString($name), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 4294967295]]);
    if ($value === false) {
        http_response_code(400);
        exit('Invalid identifier.');
    }
    return $value;
}

function passwordMeetsPolicy(string $password): bool {
    return mb_check_encoding($password, 'UTF-8') && !str_contains($password, "\0")
        && mb_strlen($password, 'UTF-8') >= 12 && strlen($password) <= 72
        && preg_match('/[A-Z]/', $password) === 1 && preg_match('/[a-z]/', $password) === 1
        && preg_match('/[0-9]/', $password) === 1 && preg_match('/[^\p{L}\p{N}\s]/u', $password) === 1;
}
function queryString(string $name, string $default = ''): string {
    $value = $_GET[$name] ?? $default;
    if (!is_string($value)) { http_response_code(400); exit('Invalid query.'); }
    return $value;
}
function queryId(string $name = 'id'): int {
    $value = filter_var(queryString($name), FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>4294967295]]);
    if ($value === false) { http_response_code(400); exit('Invalid identifier.'); }
    return $value;
}
function abortRequest(int $status, string $message): never {
    if (in_array($status, [403,429], true)) securityEvent('request.rejected_'.$status);
    http_response_code($status);
    exit(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
}
function applicationKey(): string {
    global $config;
    $key = (string)($config['app_key'] ?? '');
    if (strlen($key) < 32) throw new RuntimeException('Configure an application key of at least 32 bytes.');
    return $key;
}
function isDeveloper(): bool {
    global $config;
    return isRole('admin') && ($config['app_env'] ?? '') === 'development'
        && ($config['enable_developer_tools'] ?? false) === true
        && in_array((int)currentUser()['user_id'], array_map('intval', $config['developer_user_ids'] ?? []), true);
}
function audit(PDO $pdo, string $action, ?int $subjectId = null): void {
    $ip = hash_hmac('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'cli'), applicationKey());
    securityEvent($action, $subjectId);
    $pdo->prepare('INSERT INTO audit_log (actor_id,action,subject_id,ip_hash) VALUES (?,?,?,?)')
        ->execute([currentUser()['user_id'] ?? null, $action, $subjectId, $ip]);
}

function serverError(Throwable $exception): never {
    // Log technical detail on the server; never render database errors or credentials.
    error_log('EventLink: ' . get_class($exception) . ' [' . $exception->getCode() . ']');
    http_response_code(503);
    exit('The service is temporarily unavailable. Please try again later.');
}

/** Structured records go to PHP's server log for central collection; never log credentials or URLs. */
function securityEvent(string $action, ?int $subjectId = null): void {
    error_log('FairboardSecurity ' . json_encode([
        'time'=>gmdate('c'), 'action'=>substr($action,0,64),
        'actor'=>$_SESSION['user']['user_id'] ?? null, 'subject'=>$subjectId,
        'ip_hash'=>hash_hmac('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'cli'), applicationKey()),
    ], JSON_UNESCAPED_SLASHES));
}
function requireVerifiedEmail(): void {
    if (empty(currentUser()['email_verified_at'])) {
        flash('Verify your email in Your account before booking or creating events.', 'error');
        redirect('account.php');
    }
}
