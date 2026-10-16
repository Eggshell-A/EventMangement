<?php
require __DIR__ . '/includes/bootstrap.php';
requireRole('admin');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}
$action = $_POST['action'] ?? '';
if ($action === 'verify_school') {
    $userId = (int) ($_POST['user_id'] ?? 0);
    $statement = $pdo->prepare('SELECT user_id FROM users WHERE user_id = ? AND is_school_email = 1 AND school_email_verified = 0');
    $statement->execute([$userId]);
    if (!$statement->fetch()) {
        http_response_code(404);
        exit('No school email is waiting for verification.');
    }
    $pdo->prepare('UPDATE users SET school_email_verified = 1, verified_at = NOW(), verified_by = ? WHERE user_id = ?')->execute([currentUser()['user_id'], $userId]);
    $pdo->prepare("UPDATE creator_plans SET plan_code = 'verified_society' WHERE user_id = ?")->execute([$userId]);
    flash('School email verified. A verified society shows no monthly fee.');
    redirect('admin.php');
}
$id = (int) ($_POST['event_id'] ?? 0);
$map = ['approve' => 'approved', 'reject' => 'rejected', 'remove' => 'removed'];
if (!isset($map[$action])) {
    http_response_code(400);
    exit('Invalid action.');
}
$statement = $pdo->prepare('UPDATE events SET status = ? WHERE event_id = ?');
$statement->execute([$map[$action], $id]);
flash('Event status updated.');
redirect('admin.php');
