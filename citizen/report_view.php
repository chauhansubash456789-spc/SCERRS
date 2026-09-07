<?php
require_once __DIR__ . '/../config/config.php';

$user = require_role('citizen');

$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare(
    'SELECT i.*, c.name AS category_name, c.icon, d.name AS department_name
     FROM incidents i
     JOIN categories c ON c.id = i.category_id
     JOIN departments d ON d.id = c.department_id
     WHERE i.id = ? AND i.user_id = ?'
);
$stmt->execute([$id, $user['id']]);
$incident = $stmt->fetch();
if (!$incident) {
    flash('danger', 'Report not found.');
    redirect('citizen/dashboard.php');
}

$media   = db()->prepare('SELECT * FROM incident_media WHERE incident_id = ?');
$media->execute([$id]);
$media   = $media->fetchAll();

$history = db()->prepare(
    'SELECT h.*, u.name AS changed_by_name FROM status_histories h JOIN users u ON u.id = h.changed_by
     WHERE h.incident_id = ? ORDER BY h.changed_at ASC'
);
$history->execute([$id]);
$history = $history->fetchAll();

$teams = db()->prepare(
    'SELECT a.*, t.name AS team_name, t.contact, u.name AS assigned_by_name
     FROM assignments a JOIN response_teams t ON t.id = a.team_id JOIN users u ON u.id = a.assigned_by
     WHERE a.incident_id = ? ORDER BY a.assigned_at DESC'
);
$teams->execute([$id]);
$teams = $teams->fetchAll();

$fbStmt = db()->prepare('SELECT * FROM feedback WHERE incident_id = ? AND user_id = ?');
$fbStmt->execute([$id, $user['id']]);
$feedback = $fbStmt->fetch();

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($incident['status'] ?? '') === 'resolved') {
    verify_csrf();
    $rating  = (int)($_POST['rating'] ?? 0);
    $comment = trim((string)($_POST['comment'] ?? ''));

    if (!$feedback && $rating >= 1 && $rating <= 5) {
        db()->prepare('INSERT INTO feedback (incident_id, user_id, rating, comment) VALUES (?, ?, ?, ?)')
            ->execute([$id, $user['id'], $rating, $comment !== '' ? $comment : null]);
        // Thank authority department via notification to its users is skipped; notify admins.
        flash('success', 'Thank you for your feedback!');
        redirect('citizen/report_view.php?id=' . $id);
    }
    flash('warning', 'Feedback could not be saved.');
    redirect('citizen/report_view.php?id=' . $id);
}

$page_title = e($incident['ref_no']);
$load_map   = true;
require __DIR__ . '/../includes/header.php';
?>

<div class="row g-4">
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
          <div>
            <h3 class="fw-bold mb-1"><?= e($incident['icon']) ?> <?= e($incident['title']) ?></h3>
            <span class="text-muted small">Reference: <strong><?= e($incident['ref_no']) ?></strong> &middot;
              Reported <?= e(time_ago($incident['created_at'])) ?></span>
          </div>
          <div><?= severity_badge($incident['severity']) ?> <?= status_badge($incident['status']) ?></div>
        </div>
        <!-- Edit Report Button -->
<?php if ($incident['status'] === 'pending'): ?>
  <div class="d-flex gap-2 mt-2">
    <a href="<?= url('citizen/edit_report.php?id=' . $incident['id']) ?>" class="btn btn-outline-primary flex-grow">
      <i class="bi bi-pencil me-2"></i> Edit Report
    </a>
  </div>
<?php endif; ?>
<hr>
        <p class="mb-2" style="white-space:pre-wrap"><?= nl2br(e($incident['description'])) ?></p>
        <ul class="list-unstyled small text-muted mb-0">
          <li><i class="bi bi-tags"></i> Type: <?= e($incident['category_name']) ?> &rarr; handled by <strong><?= e($incident['department_name']) ?></strong></li>
          <?php if ($incident['address']): ?>
            <li><i class="bi bi-signpost-split"></i> <?= e($incident['address']) ?></li>
          <?php endif; ?>
          <li><i class="bi bi-geo"></i> GPS: <?= e($incident['latitude']) ?>, <?= e($incident['longitude']) ?></li>
        </ul>
      </div>
    </div>

    <?php if ($media): ?>
      <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-camera"></i> Photo evidence</div>
        <div class="card-body">
          <div class="row g-3">
            <?php foreach ($media as $m): ?>
              <div class="col-6 col-md-4">
                <a href="<?= url('uploads/incidents/' . rawurlencode($m['file_path'])) ?>" target="_blank">
                  <img src="<?= url('uploads/incidents/' . rawurlencode($m['file_path'])) ?>" class="img-fluid rounded shadow-sm w-100" alt="Incident photo">
                </a>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-clock-history"></i> Status timeline</div>
      <div class="card-body">
        <div class="timeline">
          <?php foreach ($history as $h): ?>
            <div class="timeline-item">
              <div class="timeline-dot bg-<?= in_array($h['status'], ['resolved'], true) ? 'success' : ($h['status'] === 'in_progress' ? 'primary' : 'secondary') ?>"></div>
              <div>
                <div class="fw-semibold"><?= e(status_label($h['status'])) ?>
                  <small class="text-muted fw-normal">&middot; <?= e(time_ago($h['changed_at'])) ?></small></div>
                <?php if ($h['note']): ?><div class="text-muted small"><?= e($h['note']) ?></div><?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>

          <?php foreach ($teams as $t): ?>
            <div class="timeline-item">
              <div class="timeline-dot bg-danger"></div>
              <div>
                <div class="fw-semibold"><i class="bi bi-people-fill"></i> Team dispatched: <?= e($t['team_name']) ?></div>
                <div class="text-muted small">Assigned by <?= e($t['assigned_by_name']) ?><?= $t['note'] ? ' — ' . e($t['note']) : '' ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-map"></i> Location</div>
      <div id="map" style="height:280px"></div>
    </div>

    <?php if ($incident['status'] === 'resolved'): ?>
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-star"></i> Rate this response</div>
        <div class="card-body">
          <?php if ($feedback): ?>
            <div class="mb-0">
              <span class="text-warning fs-5"><?= str_repeat('★', (int)$feedback['rating']) . str_repeat('☆', 5 - (int)$feedback['rating']) ?></span>
              <p class="text-muted small mb-0 mt-1"><?= e($feedback['comment'] ?: 'No comment provided.') ?></p>
            </div>
          <?php else: ?>
            <form method="post">
              <?= csrf_field() ?>
              <div class="mb-3">
                <label class="form-label fw-semibold">How satisfied are you with the response?</label>
                <select name="rating" class="form-select" required>
                  <option value="">Choose rating…</option>
                  <?php foreach ([5 => '★★★★★ Excellent', 4 => '★★★★ Good', 3 => '★★★ Average', 2 => '★★ Poor', 1 => '★ Very poor'] as $v => $lbl): ?>
                    <option value="<?= $v ?>"><?= $lbl ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="mb-3">
                <textarea name="comment" rows="3" class="form-control" placeholder="Optional comment about the response…"></textarea>
              </div>
              <button type="submit" class="btn btn-success w-100">Submit Feedback</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const map = L.map('map').setView([<?= (float)$incident['latitude'] ?>, <?= (float)$incident['longitude'] ?>], 16);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 19, attribution: '&copy; OpenStreetMap'}).addTo(map);
L.circleMarker([<?= (float)$incident['latitude'] ?>, <?= (float)$incident['longitude'] ?>], {radius: 10, color: '#dc3545', fillOpacity: .8}).addTo(map)
  .bindPopup('<?= e(addslashes($incident['title'])) ?>');
setTimeout(() => { map.invalidateSize(); }, 200);
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
