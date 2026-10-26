<?php
require __DIR__ . '/includes/bootstrap.php';
requireRole('admin');
$statement = $pdo->query("SELECT e.*, u.name organiser_name FROM events e JOIN users u ON u.user_id = e.organiser_id WHERE e.status = 'pending' ORDER BY e.created_at ASC");
$pending = $statement->fetchAll();
$statement = $pdo->query("SELECT e.*, u.name organiser_name FROM events e JOIN users u ON u.user_id = e.organiser_id WHERE e.status IN ('approved','rejected','cancelled','removed') ORDER BY e.updated_at DESC LIMIT 30");
$all = $statement->fetchAll();
$users = $pdo->query('SELECT u.user_id, u.name, u.email, u.role, u.is_active, u.is_school_email, u.school_email_verified, p.organisation_name, p.plan_code FROM users u LEFT JOIN creator_plans p ON p.user_id = u.user_id ORDER BY u.name, u.email')->fetchAll();
$staffAccounts = array_values(array_filter($users, fn(array $person): bool => $person['role'] === 'staff' && (int) $person['is_active'] === 1));
$creatorAccounts = array_values(array_filter($users, fn(array $person): bool => $person['role'] === 'creator' && (int) $person['is_active'] === 1));
$assignments = $pdo->query("SELECT a.staff_user_id, a.creator_user_id, s.name AS staff_name, c.name AS creator_name FROM staff_assignments a JOIN users s ON s.user_id = a.staff_user_id JOIN users c ON c.user_id = a.creator_user_id ORDER BY s.name, c.name")->fetchAll();
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
        <form method="post" action="admin_action.php"><?= csrfField() ?><input type="hidden" name="event_id" value="<?= (int) $event['event_id'] ?>"><input type="hidden" name="action" value="approve"><button>Approve</button></form>
        <form method="post" action="admin_action.php"><?= csrfField() ?><input type="hidden" name="event_id" value="<?= (int) $event['event_id'] ?>"><input type="hidden" name="action" value="reject"><button class="danger">Reject</button></form>
        <form method="post" action="admin_action.php"><?= csrfField() ?><input type="hidden" name="event_id" value="<?= (int) $event['event_id'] ?>"><input type="hidden" name="action" value="remove"><button class="danger">Remove</button></form>
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
    <td class="no-print"><form method="post" action="admin_action.php"><?= csrfField() ?><input type="hidden" name="event_id" value="<?= (int) $event['event_id'] ?>"><input type="hidden" name="action" value="remove"><button class="danger">Remove</button></form></td>
  </tr>
<?php endforeach; ?>
</tbody></table></div>
<h2 id="accounts">Accounts</h2>
<div class="table-wrap"><table><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Plan</th><th>Status</th><th class="no-print">Actions</th></tr></thead><tbody>
<?php foreach ($users as $person): ?>
  <tr>
    <td><?= e($person['name']) ?><?php if ($person['organisation_name']): ?><br><span class="muted"><?= e($person['organisation_name']) ?></span><?php endif; ?></td>
    <td><?= e($person['email']) ?></td>
    <td><?= e($person['role']) ?></td>
    <td><?= e(planFeeLabel($person['plan_code'] ?? null)) ?></td>
    <td><?= (int) $person['is_active'] === 1 ? 'Active' : 'Disabled' ?></td>
    <td class="no-print"><div class="actions">
      <?php if ((int) $person['is_school_email'] === 1 && (int) $person['school_email_verified'] === 0): ?>
        <form method="post" action="admin_action.php">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="verify_school">
          <input type="hidden" name="user_id" value="<?= (int) $person['user_id'] ?>">
          <button type="submit">Verify school email</button>
        </form>
      <?php elseif ((int) $person['school_email_verified'] === 1): ?>
        <span>School email verified</span>
      <?php endif; ?>
      <?php if ((int) $person['user_id'] !== (int) currentUser()['user_id']): ?>
        <form method="post" action="admin_action.php">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="toggle_account">
          <input type="hidden" name="user_id" value="<?= (int) $person['user_id'] ?>">
          <input type="hidden" name="active" value="<?= (int) $person['is_active'] === 1 ? '0' : '1' ?>">
          <button class="<?= (int) $person['is_active'] === 1 ? 'danger' : '' ?>" type="submit"><?= (int) $person['is_active'] === 1 ? 'Disable' : 'Reactivate' ?></button>
        </form>
      <?php endif; ?>
    </div></td>
  </tr>
<?php endforeach; ?>
</tbody></table></div>
<h2 id="assignments">Staff assignments</h2>
<?php if ($staffAccounts && $creatorAccounts): ?>
  <form method="post" action="admin_action.php" class="assignment-form">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="assign_staff">
    <label for="staff_user_id">Staff member</label>
    <select id="staff_user_id" name="staff_user_id" required>
      <?php foreach ($staffAccounts as $staff): ?><option value="<?= (int) $staff['user_id'] ?>"><?= e($staff['name']) ?></option><?php endforeach; ?>
    </select>
    <label for="creator_user_id">Creator</label>
    <select id="creator_user_id" name="creator_user_id" required>
      <?php foreach ($creatorAccounts as $creator): ?><option value="<?= (int) $creator['user_id'] ?>"><?= e($creator['name']) ?></option><?php endforeach; ?>
    </select>
    <div class="form-actions"><button type="submit">Assign Staff</button></div>
  </form>
<?php else: ?>
  <p class="notice">An active Staff account and an active Creator account are required before an assignment can be made.</p>
<?php endif; ?>
<?php if (!$assignments): ?>
  <p class="notice">No Staff assignments yet.</p>
<?php else: ?>
  <div class="table-wrap"><table><thead><tr><th>Staff</th><th>Creator</th><th class="no-print">Action</th></tr></thead><tbody>
  <?php foreach ($assignments as $assignment): ?>
    <tr><td><?= e($assignment['staff_name']) ?></td><td><?= e($assignment['creator_name']) ?></td><td class="no-print">
      <form method="post" action="admin_action.php">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="unassign_staff">
        <input type="hidden" name="staff_user_id" value="<?= (int) $assignment['staff_user_id'] ?>">
        <input type="hidden" name="creator_user_id" value="<?= (int) $assignment['creator_user_id'] ?>">
        <button class="danger" type="submit">Remove assignment</button>
      </form>
    </td></tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php';
