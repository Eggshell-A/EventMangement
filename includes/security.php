<?php
declare(strict_types=1);

const SESSION_IDLE_SECONDS = 1800;
const LOGIN_FAILURE_LIMIT = 5;
const LOGIN_FAILURE_WINDOW = 900;

function configureSessionCookies(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function csrfToken(): string
{
    if (empty($_SESSION['_csrf_token']) || !is_string($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function csrfTokenValid(?string $token): bool
{
    return is_string($token)
        && isset($_SESSION['_csrf_token'])
        && is_string($_SESSION['_csrf_token'])
        && hash_equals($_SESSION['_csrf_token'], $token);
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8')
        . '">';
}

function requireValidCsrf(): void
{
    if (!csrfTokenValid(isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : null)) {
        http_response_code(403);
        exit('This form has expired. Go back, refresh the page, and try again.');
    }
}

function sessionIsExpired(array $session, ?int $now = null): bool
{
    $lastActivity = isset($session['last_activity']) ? (int) $session['last_activity'] : 0;
    if ($lastActivity <= 0) {
        return false;
    }
    return (($now ?? time()) - $lastActivity) > SESSION_IDLE_SECONDS;
}

function touchSession(?int $now = null): void
{
    $_SESSION['last_activity'] = $now ?? time();
}

function loginAllowed(string $key, ?int $now = null): bool
{
    $entry = $_SESSION['_login_failures'][$key] ?? null;
    if (!is_array($entry)) {
        return true;
    }
    $timestamp = (int) ($entry['last_at'] ?? 0);
    if (($now ?? time()) - $timestamp > LOGIN_FAILURE_WINDOW) {
        unset($_SESSION['_login_failures'][$key]);
        return true;
    }
    return (int) ($entry['count'] ?? 0) < LOGIN_FAILURE_LIMIT;
}

function recordLoginFailure(string $key, ?int $now = null): void
{
    $timestamp = $now ?? time();
    $entry = $_SESSION['_login_failures'][$key] ?? ['count' => 0, 'last_at' => 0];
    if ($timestamp - (int) ($entry['last_at'] ?? 0) > LOGIN_FAILURE_WINDOW) {
        $entry = ['count' => 0, 'last_at' => 0];
    }
    $_SESSION['_login_failures'][$key] = [
        'count' => (int) ($entry['count'] ?? 0) + 1,
        'last_at' => $timestamp,
    ];
}

function clearLoginFailures(string $key): void
{
    unset($_SESSION['_login_failures'][$key]);
}

function accountIsActive(array $account): bool
{
    return !array_key_exists('is_active', $account) || (int) $account['is_active'] === 1;
}
