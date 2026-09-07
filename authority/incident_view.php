<?php
require_once __DIR__ . '/../config/config.php';

$user    = require_role('authority', 'admin');
$isAdmin = $user['role'] === 'admin';

$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare(
    'SELECT i.*, c.name AS category_name, c.icon, c.department_id, d.name AS department_name,
            u.name AS reporter_name, u.phone AS reporter_phone, u.email AS reporter_email
     FROM incidents i
     JOIN categories c ON c.id = i.category_id
     JOIN departments d ON d.id = c.department_id
     JOIN users u ON u.id = i.user_id
     WHERE i.id = ?'
);
$stmt->execute([$id]);
$incident = $stmt->fetch();

if (!$incident || (!$isAdmin && (int)$incident['department_id'] !== (int)$user['department_id'])) {
    flash('danger', 'Incident not found or outside your agency.');
    redirect('authority/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'update_status') {
        $status = (string)($_POST['status'] ?? '');
        $note   = trim((string)($_POST['note'] ?? ''));
        if (in_array($status, STATUSES, true) && $status !== $incident['status']) {
            db()->prepare('UPDATE incidents SET status = ? WHERE id = ?')->execute([$status, $id]);
            db()->prepare('INSERT INTO status_histories (incident_id, status, changed_by, note) VALUES (?, ?, ?, ?)')
                ->execute([$id, $status, $user['id'], $note !== '' ? $note : ('Status set to ' . status_label($status) . ' by ' . $user['department_name'] . '.')]);
            notify_user((int)$incident['user_id'], $id,
                'Your report ' . $incident['ref_no'] . ' is now "' . status_label($status) . '" — updated by ' . $user['department_name'] . '.');
            flash('success', 'Status updated and citizen notified.');
        } else {
            flash('warning', 'Please choose a different status.');
        }
        redirect('authority/incident_view.php?id=' . $id);
    }

    if ($action === 'assign_team') {
        $teamId = (int)($_POST['team_id'] ?? 0);
        $note   = trim((string)($_POST['note'] ?? ''));
        $chk = db()->prepare('SELECT id FROM response_teams WHERE id = ? AND department_id = ? AND active = 1');
        $chk->execute([$teamId, (int)$incident['department_id']]);
        if ($chk->fetch()) {
            db()->prepare('INSERT INTO assignments (incident_id, team_id, assigned_by, note) VALUES (?, ?, ?, ?)')
                ->execute([$id, $teamId, $user['id'], $note !== '' ? $note : null]);

            // Auto-advance status: pending -> acknowledged
            if ($incident['status'] === 'pending') {
                db()->prepare('UPDATE incidents SET status = "acknowledged" WHERE id = ?')->execute([$id]);
                db()->prepare('INSERT INTO status_histories (incident_id, status, changed_by, note) VALUES (?, "acknowledged", ?, "Team assigned by control room.")')
                    ->execute([$id, $user['id']]);
            }
            notify_user((int)$incident['user_id'], $id, 'Response team dispatched for your report ' . $incident['ref_no'] . '.');
            flash('success', 'Team assigned successfully.');
        } else {
            flash('danger', 'Invalid team selection.');
        }
        redirect('authority/incident_view.php?id=' . $id);
    }
}

$media = db()->prepare('SELECT * FROM incident_media WHERE incident_id = ?');
$media->execute([$id]);
$media = $media->fetchAll();

$history = db()->prepare(
    'SELECT h.*, u.name AS changed_by_name FROM status_histories h JOIN users u ON u.id = h.changed_by
     WHERE h.incident_id = ? ORDER BY h.changed_at ASC'
);
$history->execute([$id]);
$history = $history->fetchAll();

$teamsStmt = db()->prepare('SELECT * FROM response_teams WHERE department_id = ? AND active = 1 ORDER BY name');
$teamsStmt->execute([(int)$incident['department_id']]);
$teams = $teamsStmt->fetchAll();

$assignments = db()->prepare(
    'SELECT a.*, t.name AS team_name, t.contact, u.name AS assigned_by_name
     FROM assignments a JOIN response_teams t ON t.id = a.team_id JOIN users u ON u.id = a.assigned_by
     WHERE a.incident_id = ? ORDER BY a.assigned_at DESC'
);
$assignments->execute([$id]);
$assignments = $assignments->fetchAll();

$page_title = e($incident['ref_no']);
$load_map   = true;
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-start mb-3 gap-2">
  <div>
    <a class="btn btn-sm btn-outline-secondary mb-2" href="<?= url('authority/dashboard.php') ?>"><i class="bi bi-arrow-left"></i> Back</a>
    <h3 class="fw-bold mb-0"><?= e($incident['icon']) ?> <?= e($incident['title']) ?></h3>
    <span class="text-muted small"><strong><?= e($incident['ref_no']) ?></strong> &middot; <?= e($incident['category_name']) ?> &middot; <?= e($incident['department_name']) ?></span>
  </div>
  <div><?= severity_badge($incident['severity']) ?> <?= status_badge($incident['status']) ?></div>
</div>

<div class="row g-4">
  <div class="col-lg-7">

    <div class="card border-0 shadow-sm mb-4">
      <div class="card-body">
        <p style="white-space:pre-wrap"><?= nl2br(e($incident['description'])) ?></p>
        <hr>
        <div class="row small">
          <div class="col-md-6">
            <div><i class="bi bi-person"></i> Reporter: <strong><?= e($incident['reporter_name']) ?></strong></div>
            <div><i class="bi bi-telephone"></i>
              <?php if ($incident['reporter_phone']): ?>
                <a href="tel:<?= e($incident['reporter_phone']) ?>"><?= e($incident['reporter_phone']) ?></a>
              <?php else: ?>—<?php endif; ?>
            </div>
            <div><i class="bi bi-envelope"></i> <?= e($incident['reporter_email']) ?></div>
          </div>
          <div class="col-md-6">
            <div><i class="bi bi-signpost-split"></i> <?= e($incident['address'] ?: 'No landmark given') ?></div>
            <div><i class="bi bi-geo"></i> GPS: <?= e($incident['latitude']) ?>, <?= e($incident['longitude']) ?></div>
            <div><i class="bi bi-clock"></i> Reported: <?= e($incident['created_at']) ?></div>
          </div>
        </div>
      </div>
    </div>

    <?php if ($media): ?>
      <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-camera"></i> Photo evidence</div>
        <div class="card-body"><div class="row g-3">
          <?php foreach ($media as $m): ?>
            <div class="col-6 col-md-4">
              <a href="<?= url('uploads/incidents/' . rawurlencode($m['file_path'])) ?>" target="_blank">
                <img src="<?= url('uploads/incidents/' . rawurlencode($m['file_path'])) ?>" class="img-fluid rounded w-100" alt="Incident photo">
              </a>
            </div>
          <?php endforeach; ?>
        </div></div>
      </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-people"></i> Dispatched teams</div>
      <?php if (!$assignments): ?>
        <div class="card-body text-muted text-center py-4">No teams dispatched yet.</div>
      <?php else: ?>
        <ul class="list-group list-group-flush">
          <?php foreach ($assignments as $a): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-1">
              <div>
                <strong><i class="bi bi-truck"></i> <?= e($a['team_name']) ?></strong>
                <?php if ($a['contact']): ?><span class="text-muted small">(☎ <?= e($a['contact']) ?>)</span><?php endif; ?>
                <div class="text-muted small">By <?= e($a['assigned_by_name']) ?> &middot; <?= e(time_ago($a['assigned_at'])) ?>
                  <?= $a['note'] ? '<br>' . e($a['note']) : '' ?></div>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-clock-history"></i> Audit trail</div>
      <div class="card-body">
        <div class="timeline">
          <?php foreach ($history as $h): ?>
            <div class="timeline-item">
              <div class="timeline-dot bg-secondary"></div>
              <div>
                <div class="fw-semibold"><?= e(status_label($h['status'])) ?>
                  <small class="text-muted fw-normal">&middot; <?= e($h['changed_at']) ?></small></div>
                <div class="text-muted small"><?= e($h['changed_by_name']) ?><?= $h['note'] ? ' — ' . e($h['note']) : '' ?></div>
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
      <div id="map" style="height:260px"></div>
    </div>

    <div class="card border-0 shadow-sm mb-4 border-start border-danger border-4">
      <div class="card-body">
        <h6 class="fw-bold"><i class="bi bi-truck"></i> Dispatch response team</h6>
        <?php if (!$teams): ?>
          <p class="text-muted small mb-0">No active teams configured for this department.</p>
        <?php else: ?>
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="assign_team">
            <select name="team_id" class="form-select mb-2" required>
              <option value="">Select team…</option>
              <?php foreach ($teams as $t): ?>
                <option value="<?= (int)$t['id'] ?>"><?= e($t['name']) ?><?= $t['contact'] ? ' (☎ ' . e($t['contact']) . ')' : '' ?></option>
              <?php endforeach; ?>
            </select>
            <input type="text" name="note" class="form-control mb-2" placeholder="Dispatch note (optional)" maxlength="255">
            <button type="submit" class="btn btn-danger w-100 fw-semibold">Dispatch Team</button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <h6 class="fw-bold"><i class="bi bi-arrow-repeat"></i> Update status</h6>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="update_status">
          <select name="status" class="form-select mb-2" required>
            <option value="">Change to…</option>
            <?php foreach (STATUSES as $s): if ($s === $incident['status']) continue; ?>
              <option value="<?= $s ?>"><?= e(status_label($s)) ?></option>
            <?php endforeach; ?>
          </select>
          <input type="text" name="note" class="form-control mb-2" placeholder="Note for the audit trail / citizen (optional)" maxlength="255">
          <button type="submit" class="btn btn-primary w-100 fw-semibold">Update Status</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const map = L.map('map').setView([<?= (float)$incident['latitude'] ?>, <?= (float)$incident['longitude'] ?>], 16);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 19, attribution: '&copy; OpenStreetMap'}).addTo(map);
L.circleMarker([<?= (float)$incident['latitude'] ?>, <?= (float)$incident['longitude'] ?>], {radius: 10, color: '#dc3545', fillOpacity: .8}).addTo(map);
setTimeout(() => { map.invalidateSize(); }, 200);
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
