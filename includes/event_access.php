<?php
declare(strict_types=1);

function canCreateEvent(array $user): bool
{
    return ($user['role'] ?? '') === 'creator';
}

function staffAssignedToCreator(PDO $pdo, int $staffUserId, int $creatorUserId): bool
{
    $statement = $pdo->prepare('SELECT 1 FROM staff_assignments WHERE staff_user_id = ? AND creator_user_id = ?');
    $statement->execute([$staffUserId, $creatorUserId]);
    return (bool) $statement->fetchColumn();
}

function canEditEvent(PDO $pdo, array $user, array $event): bool
{
    $role = (string) ($user['role'] ?? '');
    $userId = (int) ($user['user_id'] ?? 0);
    $creatorId = (int) ($event['organiser_id'] ?? 0);
    if ($role === 'creator') {
        return $userId > 0 && $userId === $creatorId;
    }
    return $role === 'staff'
        && $userId > 0
        && $creatorId > 0
        && staffAssignedToCreator($pdo, $userId, $creatorId);
}

function canManageAttendees(PDO $pdo, array $user, array $event): bool
{
    return canEditEvent($pdo, $user, $event);
}

function assignStaffToCreator(PDO $pdo, int $staffUserId, int $creatorUserId): bool
{
    $statement = $pdo->prepare('SELECT user_id, role FROM users WHERE user_id IN (?, ?)');
    $statement->execute([$staffUserId, $creatorUserId]);
    $roles = [];
    foreach ($statement->fetchAll() as $account) {
        $roles[(int) $account['user_id']] = (string) $account['role'];
    }
    if (($roles[$staffUserId] ?? '') !== 'staff' || ($roles[$creatorUserId] ?? '') !== 'creator') {
        return false;
    }
    if (staffAssignedToCreator($pdo, $staffUserId, $creatorUserId)) {
        return false;
    }
    $statement = $pdo->prepare('INSERT INTO staff_assignments (staff_user_id, creator_user_id) VALUES (?, ?)');
    return $statement->execute([$staffUserId, $creatorUserId]);
}

function unassignStaffFromCreator(PDO $pdo, int $staffUserId, int $creatorUserId): bool
{
    $statement = $pdo->prepare('DELETE FROM staff_assignments WHERE staff_user_id = ? AND creator_user_id = ?');
    $statement->execute([$staffUserId, $creatorUserId]);
    return $statement->rowCount() === 1;
}

function setAccountActive(PDO $pdo, int $adminUserId, int $targetUserId, bool $active): bool
{
    if ($adminUserId <= 0 || $targetUserId <= 0 || $adminUserId === $targetUserId) {
        return false;
    }
    $statement = $pdo->prepare('UPDATE users SET is_active = ? WHERE user_id = ?');
    $statement->execute([$active ? 1 : 0, $targetUserId]);
    return $statement->rowCount() === 1;
}
