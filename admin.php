<?php
require __DIR__ . '/includes/bootstrap.php';
requireRole('admin');
$statement = $pdo->query("SELECT e.*, u.name organiser_name FROM events e JOIN users u ON u.user_id = e.organiser_id WHERE e.status = 'pending' ORDER BY e.created_at ASC");
$pending = $statement->fetchAll();
$statement = $pdo->query("SELECT e.*, u.name organiser_name FROM events e JOIN users u ON u.user_id = e.organiser_id WHERE e.status IN ('approved','rejected','removed') ORDER BY e.updated_at DESC LIMIT 30");
$all = $statement->fetchAll();
$users = $pdo->query('SELECT u.user_id, u.name, u.email, u.role, u.is_school_email, u.school_email_verified, p.organisation_name, p.plan_code FROM users u LEFT JOIN creator_plans p ON p.user_id = u.user_id ORDER BY u.name, u.email')->fetchAll();
$pageTitle = 'Admin';
require __DIR__ . '/includes/header.php';
?>
<h1>Admin</h1>
<h2>Pending events</h2>
<?php if (!$pending): ?>
  <p class="notice">No pending events.</p>
<?php else: ?>
  <div class="table-wrap"><table><thead><tr><th>Event</th><th>Creator</th><th class="no-print">Actions</th></tr></thead><tbody>
  <?php foreach ($pending as $event): ?>
    <tr>
      <td><strong><?= e($event['title']) ?></strong><br><?= e(date('j M Y, H:i', strtotime($event['start_time']))) ?><br><?= e($event['location']) ?></td>
      <td><?= e($event['organiser_name']) ?></td>
      <td class="no-print"><div class="actions">
        <form method="post" action="admin_action.php"><input type="hidden" name="event_id" value="<?= (int) $event['event_id'] ?>"><input type="hidden" name="action" value="approve"><button>Approve</button></form>
        <form method="post" action="admin_action.php"><input type="hidden" name="event_id" value="<?= (int) $event['event_id'] ?>"><input type="hidden" name="action" value="reject"><button class="danger">Reject</button></form>
        <form method="post" action="admin_action.php"><input type="hidden" name="event_id" value="<?= (int) $event['event_id'] ?>"><input type="hidden" name="action" value="remove"><button class="danger">Remove</button></form>
      </div></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php endif; ?>
<h2>Recent decisions</h2>
<div class="table-wrap"><table><thead><tr><th>Event</th><th>Status</th><th class="no-print">Action</th></tr></thead><tbody>
<?php foreach ($all as $event): ?>
  <tr>
    <td><?= e($event['title']) ?></td>
    <td><span class="status <?= e($event['status']) ?>"><?= e($event['status']) ?></span></td>
    <td class="no-print"><form method="post" action="admin_action.php"><input type="hidden" name="event_id" value="<?= (int) $event['event_id'] ?>"><input type="hidden" name="action" value="remove"><button class="danger">Remove</button></form></td>
  </tr>
<?php endforeach; ?>
</tbody></table></div>
<h2>Users</h2>
<div class="table-wrap"><table><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Plan</th><th class="no-print">School email</th></tr></thead><tbody>
<?php foreach ($users as $person): ?>
  <tr>
    <td><?= e($person['name']) ?><?php if ($person['organisation_name']): ?><br><span class="muted"><?= e($person['organisation_name']) ?></span><?php endif; ?></td>
    <td><?= e($person['email']) ?></td>
    <td><?= e($person['role']) ?></td>
    <td><?= e(planFeeLabel($person['plan_code'] ?? null)) ?></td>
    <td class="no-print">
      <?php if ((int) $person['is_school_email'] === 1 && (int) $person['school_email_verified'] === 0): ?>
        <form method="post" action="admin_action.php">
          <input type="hidden" name="action" value="verify_school">
          <input type="hidden" name="user_id" value="<?= (int) $person['user_id'] ?>">
          <button type="submit">Verify school email</button>
        </form>
      <?php elseif ((int) $person['school_email_verified'] === 1): ?>
        Verified
      <?php else: ?>
        —
      <?php endif; ?>
    </td>
  </tr>
<?php endforeach; ?>
</tbody></table></div>
<?php require __DIR__ . '/includes/footer.php';
