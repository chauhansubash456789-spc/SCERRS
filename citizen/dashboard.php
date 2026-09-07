<?php
require_once __DIR__ . '/../config/config.php';

$user = require_role('citizen');

// Stats
$stats = db()->prepare(
    'SELECT
        COUNT(*) AS total,
        SUM(status = "pending")      AS pending,
        SUM(status IN ("acknowledged","in_progress")) AS active,
        SUM(status = "resolved")     AS resolved
     FROM incidents WHERE user_id = ?'
);
$stats->execute([$user['id']]);
$stats = $stats->fetch();

// My reports
$stmt = db()->prepare(
    'SELECT i.*, c.name AS category_name, c.icon,
            (SELECT COUNT(*) FROM incident_media m WHERE m.incident_id = i.id) AS media_count
     FROM incidents i JOIN categories c ON c.id = i.category_id
     WHERE i.user_id = ?
     ORDER BY i.created_at DESC
     LIMIT 50'
);
$stmt->execute([$user['id']]);
$incidents = $stmt->fetchAll();

$page_title = 'My Reports';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <div>
    <h3 class="fw-bold mb-0">Namaste, <?= e($user['name']) ?> 👋</h3>
    <p class="text-muted mb-0">Track your emergency reports and their live status.</p>
  </div>
  <a href="<?= url('citizen/report_new.php') ?>" class="btn btn-danger btn-lg fw-semibold">
    <i class="bi bi-exclamation-triangle-fill"></i> Report New Emergency
  </a>
</div>

<div class="row g-3 mb-4">
  <?php
  $cards = [
      ['Total Reports',   $stats['total'] ?? 0, 'bi-journal-text', 'secondary'],
      ['Pending',         (int)($stats['pending'] ?? 0), 'bi-hourglass-split', 'warning'],
      ['Being Handled',   (int)($stats['active'] ?? 0), 'bi-activity', 'primary'],
      ['Resolved',        (int)($stats['resolved'] ?? 0), 'bi-check-circle', 'success'],
  ];
  foreach ($cards as [$label, $value, $icon, $color]): ?>
    <div class="col-6 col-lg-3">
      <div class="card border-0 shadow-sm stat-card">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="stat-icon text-bg-<?= $color ?> rounded-3"><i class="bi <?= $icon ?>"></i></div>
          <div>
            <div class="fs-3 fw-bold lh-1"><?= (int)$value ?></div>
            <div class="text-muted small"><?= e($label) ?></div>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-header bg-white fw-semibold">My Reports</div>
  <?php if (!$incidents): ?>
    <div class="card-body text-center text-muted py-5">
      <i class="bi bi-inbox fs-1 d-block mb-2"></i>
      You have not reported any emergencies yet.
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Ref #</th><th>Incident</th><th>Type</th><th>Severity</th><th>Status</th><th>Reported</th><th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($incidents as $i): ?>
            <tr>
              <td class="fw-semibold small"><?= e($i['ref_no']) ?></td>
              <td>
                <a class="text-decoration-none fw-semibold" href="<?= url('citizen/report_view.php?id=' . $i['id']) ?>">
                  <?= e($i['title']) ?>
                </a>
                <div class="text-muted small"><i class="bi bi-camera"></i> <?= (int)$i['media_count'] ?> photo(s)</div>
              </td>
              <td><?= e($i['icon']) ?> <?= e($i['category_name']) ?></td>
              <td><?= severity_badge($i['severity']) ?></td>
              <td><?= status_badge($i['status']) ?></td>
              <td class="small text-muted" title="<?= e($i['created_at']) ?>"><?= e(time_ago($i['created_at'])) ?></td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-danger" href="<?= url('citizen/report_view.php?id=' . $i['id']) ?>">Details</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
