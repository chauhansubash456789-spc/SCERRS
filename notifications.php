<?php
require_once __DIR__ . '/config/config.php';

$user = require_role('citizen', 'authority', 'admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (($_POST['action'] ?? '') === 'mark_all') {
        db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([$user['id']]);
        flash('success', 'All notifications marked as read.');
        redirect('notifications.php');
    }
}

$stmt = db()->prepare(
    'SELECT n.*, i.ref_no FROM notifications n LEFT JOIN incidents i ON i.id = n.incident_id
     WHERE n.user_id = ? ORDER BY n.created_at DESC LIMIT 100'
);
$stmt->execute([$user['id']]);
$notifications = $stmt->fetchAll();

// Open a notification -> mark it read
if (!empty($_GET['read'])) {
    db()->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?')
        ->execute([(int)$_GET['read'], $user['id']]);
    redirect('notifications.php');
}

$page_title = 'Notifications';
require __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-lg-8">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h3 class="fw-bold mb-0"><i class="bi bi-bell"></i> Notifications</h3>
      <form method="post"><?= csrf_field() ?>
        <input type="hidden" name="action" value="mark_all">
        <button class="btn btn-sm btn-outline-secondary">Mark all read</button>
      </form>
    </div>

    <?php if (!$notifications): ?>
      <div class="card border-0 shadow-sm"><div class="card-body text-center text-muted py-5">
        <i class="bi bi-inbox fs-1 d-block mb-2"></i> No notifications yet.
      </div></div>
    <?php else: ?>
      <div class="card border-0 shadow-sm">
        <div class="list-group list-group-flush">
          <?php foreach ($notifications as $n): ?>
            <a href="<?= $n['incident_id']
                        ? url(($user['role'] === 'citizen' ? 'citizen/report_view.php?id=' : 'authority/incident_view.php?id=') . (int)$n['incident_id'] . ($n['is_read'] ? '' : '&from=n'))
                        : 'javascript:void(0)' ?>"
               class="list-group-item list-group-item-action d-flex gap-3 py-3 <?= !$n['is_read'] ? 'bg-danger-subtle' : '' ?>">
              <i class="bi <?= !$n['is_read'] ? 'bi-envelope-fill text-danger' : 'bi-envelope-open' ?> fs-5"></i>
              <div class="flex-grow-1">
                <div><?= e($n['message']) ?></div>
                <small class="text-muted"><?= e(time_ago($n['created_at'])) ?></small>
              </div>
              <?php if (!$n['is_read']): ?>
                <span class="align-self-center small text-danger">new</span>
              <?php endif; ?>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>

