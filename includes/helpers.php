<?php
require_once __DIR__ . '/role_access.php';
function e(?string $value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function isLoggedIn(): bool { return isset($_SESSION['user']); }
function currentUser(): ?array { return $_SESSION['user'] ?? null; }
function isRole(string $role): bool { return isLoggedIn() && currentUser()['role'] === $role; }
function requireLogin(): void { if (!isLoggedIn()) { flash('Please log in to continue.', 'error'); redirect('login.php'); } }
function requireRole(string $role): void { requireLogin(); if (!isRole($role)) { http_response_code(403); exit('You do not have permission to access this page.'); } }
function redirect(string $path): never { header('Location: ' . $path); exit; }
function flash(string $message, string $type = 'success'): void { $_SESSION['flash'][] = ['message' => $message, 'type' => $type]; }
function showFlashes(): void { foreach ($_SESSION['flash'] ?? [] as $item) { echo '<div class="flash ' . e($item['type']) . '" role="status">' . e($item['message']) . '</div>'; } unset($_SESSION['flash']); }
function eventIsAccessible(array $event): bool { return $event['status'] === 'approved' || isRole('admin') || (isRole('creator') && (int) currentUser()['user_id'] === (int) $event['organiser_id']); }
function eventCapacityInfo(PDO $pdo, int $eventId): array {
    $statement = $pdo->prepare('SELECT COUNT(*) FROM bookings WHERE event_id = ?');
    $statement->execute([$eventId]); $bookings = (int) $statement->fetchColumn();
    $statement = $pdo->prepare('SELECT COUNT(*) FROM waiting_list WHERE event_id = ?');
    $statement->execute([$eventId]); $waiting = (int) $statement->fetchColumn();
    return ['bookings' => $bookings, 'waiting' => $waiting];
}
function isEventOwnerOrAdmin(array $event): bool { return isRole('admin') || (isRole('creator') && (int) currentUser()['user_id'] === (int) $event['organiser_id']); }
function staffAssistsCreator(PDO $pdo, int $creatorId): bool {
    if (!isRole('staff')) {
        return false;
    }
    $statement = $pdo->prepare('SELECT 1 FROM staff_assignments WHERE staff_user_id = ? AND creator_user_id = ?');
    $statement->execute([(int) currentUser()['user_id'], $creatorId]);
    return (bool) $statement->fetchColumn();
}
function canEditEvent(PDO $pdo, array $event): bool {
    if (isRole('creator') && (int) currentUser()['user_id'] === (int) $event['organiser_id']) {
        return true;
    }
    return staffAssistsCreator($pdo, (int) $event['organiser_id']);
}
function safeEventNext(mixed $value): ?string {
    if (is_string($value) && preg_match('/^event\.php\?id=([1-9][0-9]*)$/', $value, $match)) {
        return 'event.php?id=' . $match[1];
    }
    return null;
}
function loginRedirectFor(array $user, ?string $next): string {
    if (($user['role'] ?? '') === 'user' && $next) {
        return $next;
    }
    return roleHome((string) ($user['role'] ?? 'user'));
}
function planFeeLabel(?string $plan): string {
    if ($plan === 'verified_society') {
        return 'Verified society · no monthly fee';
    }
    if ($plan === 'external_monthly') {
        return 'External group · HK$80 a month';
    }
    return '';
}
function posterTone(string $title): int { return (int) (abs(crc32($title)) % 6); }
function eventCardSql(): string {
    return 'SELECT e.event_id, e.organiser_id, e.title, e.description, e.start_time, e.location, e.capacity, e.status, u.name AS organiser_name, p.organisation_name, p.plan_code, (SELECT COUNT(*) FROM bookings b WHERE b.event_id = e.event_id) AS booking_count, (SELECT COUNT(*) FROM waiting_list w WHERE w.event_id = e.event_id) AS waiting_count FROM events e JOIN users u ON u.user_id = e.organiser_id LEFT JOIN creator_plans p ON p.user_id = e.organiser_id';
}
function placesLeft(array $event): int { return max(0, (int) $event['capacity'] - (int) $event['booking_count']); }
function organisationName(array $event): string {
    $name = trim((string) ($event['organisation_name'] ?? ''));
    return $name !== '' ? $name : (string) $event['organiser_name'];
}
