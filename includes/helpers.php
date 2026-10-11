<?php
function e(?string $value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function isLoggedIn(): bool { return isset($_SESSION['user']); }
function currentUser(): ?array { return $_SESSION['user'] ?? null; }
function isRole(string $role): bool { return isLoggedIn() && currentUser()['role'] === $role; }
function requireLogin(): void { if (!isLoggedIn()) { flash('Please log in to continue.', 'error'); redirect('login.php'); } }
function requireRole(string $role): void { requireLogin(); if (!isRole($role)) { http_response_code(403); exit('You do not have permission to access this page.'); } }
function redirect(string $path): never { header('Location: ' . $path); exit; }
function flash(string $message, string $type = 'success'): void { $_SESSION['flash'][] = ['message' => $message, 'type' => $type]; }
function showFlashes(): void { foreach ($_SESSION['flash'] ?? [] as $item) { echo '<div class="flash ' . e($item['type']) . '" role="status">' . e($item['message']) . '</div>'; } unset($_SESSION['flash']); }
function eventIsAccessible(array $event): bool { return $event['status'] === 'approved' || (isLoggedIn() && (currentUser()['role'] === 'admin' || (int) currentUser()['user_id'] === (int) $event['organiser_id'])); }
function eventCapacityInfo(PDO $pdo, int $eventId): array {
    $statement = $pdo->prepare('SELECT COUNT(*) FROM bookings WHERE event_id = ?');
    $statement->execute([$eventId]); $bookings = (int) $statement->fetchColumn();
    $statement = $pdo->prepare('SELECT COUNT(*) FROM waiting_list WHERE event_id = ?');
    $statement->execute([$eventId]); $waiting = (int) $statement->fetchColumn();
    return ['bookings' => $bookings, 'waiting' => $waiting];
}
function isEventOwnerOrAdmin(array $event): bool { return isRole('admin') || (isRole('organiser') && (int) currentUser()['user_id'] === (int) $event['organiser_id']); }
