<?php
require __DIR__ . '/includes/bootstrap.php';
requirePost();
requireRole('admin');
$action = postString('action');
if ($action === 'verify_school') {
    $userId = postPositiveInt('user_id');
    try {
        $pdo->beginTransaction();
        $statement = $pdo->prepare('SELECT user_id FROM users WHERE user_id=? AND is_school_email=1 AND school_email_verified=0 FOR UPDATE');
        $statement->execute([$userId]);
        if (!$statement->fetch()) {
            $pdo->rollBack();
            http_response_code(404);
            exit('No school email is waiting for verification.');
        }
        $pdo->prepare('UPDATE users SET school_email_verified=1,verified_at=NOW(),verified_by=? WHERE user_id=?')->execute([currentUser()['user_id'], $userId]);
        $pdo->prepare("UPDATE creator_plans SET plan_code='verified_society' WHERE user_id=?")->execute([$userId]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        serverError($exception);
    }
    flash('School email verified. A verified society shows no monthly fee.');
    redirect('admin.php');
}
$id = postPositiveInt('event_id');
$map = ['approve' => 'approved', 'reject' => 'rejected', 'remove' => 'removed'];
if (!isset($map[$action])) {
    http_response_code(400);
    exit('Invalid action.');
}
// UPDATE takes the same event row lock as booking and capacity writers.
try {
    $statement = $pdo->prepare('UPDATE events SET status=? WHERE event_id=?');
    $statement->execute([$map[$action], $id]);
} catch (Throwable $exception) {
    serverError($exception);
}
flash('Event status updated.');
redirect('admin.php');
