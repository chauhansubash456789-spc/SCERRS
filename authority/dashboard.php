<?php
require_once __DIR__ . '/../config/config.php';

$user = require_role('authority', 'admin');
$isAdmin = $user['role'] === 'admin';

/* ---- Filters (GET) ---- */
$fStatus    = $_GET['status']    ?? '';
$fSeverity  = $_GET['severity']  ?? '';
$fCategory  = $_GET['category_id'] ?? '';
$fSearch    = trim((string)($_GET['q'] ?? ''));

$where  = [];
$params = [];

if (!$isAdmin) {
    $where[] = 'c.department_id = ?';
    $params[] = (int)$user['department_id'];
}
if ($fStatus !== '' && in_array($fStatus, STATUSES, true)) {
    $where[] = 'i.status = ?';   $params[] = $fStatus;
}
if ($fSeverity !== '' && in_array($fSeverity, ['low', 'medium', 'high', 'critical'], true)) {
    $where[] = 'i.severity = ?'; $params[] = $fSeverity;
}
if ($fCategory !== '') {
    $where[] = 'i.category_id = ?'; $params[] = (int)$fCategory;
}
if ($fSearch !== '') {
    $where[] = '(i.title LIKE ? OR i.ref_no LIKE ? OR i.address LIKE ?)';
    $like = '%' . $fSearch . '%';
    array_push($params, $like, $like, $like);
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

/* ---- KPI cards ---- */
$kpiSql = "SELECT COUNT(*) AS total,
       SUM(i.status = 'pending')      AS pending,
       SUM(i.status IN ('acknowledged','in_progress')) AS active,
       SUM(i.status = 'resolved')     AS resolved,
       SUM(i.severity IN ('high','critical'))          AS urgent
     FROM incidents i JOIN categories c ON c.id = i.category_id $whereSql";
$stmtKpi = db()->prepare($kpiSql);
$stmtKpi->execute($params);
$kpi = $stmtKpi->fetch();

/* ---- Incident list ---- */
$listStmt = db()->prepare(
    "SELECT i.*, c.name AS category_name, c.icon, u.name AS reporter_name,
            (SELECT COUNT(*) FROM incident_media m WHERE m.incident_id = i.id) AS media_count
     FROM incidents i
     JOIN categories c ON c.id = i.category_id
     JOIN users u ON u.id = i.user_id
     $whereSql
     ORDER BY FIELD(i.severity,'critical','high','medium','low'), i.created_at DESC
     LIMIT 100"
);
$listStmt->execute($params);
$incidents = $listStmt->fetchAll();

/* ---- Category options for the filter dropdown ---- */
$catSql  = !$isAdmin
    ? 'SELECT id, name, icon FROM categories WHERE department_id = ? AND active = 1 ORDER BY name'
    : 'SELECT id, name, icon FROM categories WHERE active = 1 ORDER BY name';
$catStmt = db()->prepare($catSql);
$catStmt->execute(!$isAdmin ? [(int)$user['department_id']] : []);
$categories = $catStmt->fetchAll();

$page_title = $isAdmin ? 'All Incidents' : 'Incidents — ' . e($user['department_name']);
$load_map   = true;
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <div>
    <h3 class="fw-bold mb-0"><i class="bi bi-radar text-danger"></i> <?= $isAdmin ? 'All Incidents' : e($user['department_name']) ?> Operations</h3>
    <p class="text-muted mb-0">Live map and reports routed to your agency.</p>
  </div>
</div>

<div class="row g-3 mb-4">
  <?php
  $cards = [
      ['Total',        $kpi['total'] ?? 0,    'bi-stack',           'secondary'],
      ['Pending',      $kpi['pending'] ?? 0,  'bi-hourglass-split', 'warning'],
      ['Active',       $kpi['active'] ?? 0,   'bi-activity',        'primary'],
      ['Resolved',     $kpi['resolved'] ?? 0, 'bi-check-circle',    'success'],
      ['Urgent (High/Critical)', $kpi['urgent'] ?? 0, 'bi-exclamation-octagon', 'danger'],
  ];
  foreach ($cards as [$label, $value, $icon, $color]): ?>
    <div class="col-6 col-md-4 col-lg">
      <div class="card border-0 shadow-sm stat-card h-100">
        <div class="card-body d-flex align-items-center gap-3 py-3">
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

<div class="card border-0 shadow-sm mb-4">
  <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span class="fw-semibold"><i class="bi bi-map"></i> Live incident map</span>
    <button class="btn btn-sm btn-outline-secondary" onclick="loadMarkers()"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
  </div>
  <div class="card-body p-0"><div id="map" style="height:420px"></div></div>
</div>

<div class="card border-0 shadow-sm mb-4">
  <div class="card-header bg-white">
    <form method="get" class="row g-2 align-items-end">
      <div class="col-md-3">
        <input type="text" name="q" value="<?= e($fSearch) ?>" class="form-control form-control-sm" placeholder="Search ref #, title, address…">
      </div>
      <div class="col-md-2">
        <select name="status" class="form-select form-select-sm">
          <option value="">All statuses</option>
          <?php foreach (STATUSES as $s): ?>
            <option value="<?= $s ?>" <?= $fStatus === $s ? 'selected' : '' ?>><?= e(status_label($s)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <select name="severity" class="form-select form-select-sm">
          <option value="">All severities</option>
          <?php foreach (['critical', 'high', 'medium', 'low'] as $s): ?>
            <option value="<?= $s ?>" <?= $fSeverity === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <select name="category_id" class="form-select form-select-sm">
          <option value="">All types</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= $fCategory === (string)$c['id'] ? 'selected' : '' ?>><?= e($c['icon'] . ' ' . $c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2 d-flex gap-2">
        <button class="btn btn-sm btn-danger flex-fill">Filter</button>
        <a href="<?= url('authority/dashboard.php') ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
      </div>
    </form>
  </div>

  <?php if (!$incidents): ?>
    <div class="card-body text-center text-muted py-5">
      <i class="bi bi-inbox fs-1 d-block mb-2"></i> No incidents match the current filters.
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr><th>Ref #</th><th>Incident</th><th>Type</th><th>Severity</th><th>Status</th><th>Reported by</th><th>When</th></tr>
        </thead>
        <tbody>
          <?php foreach ($incidents as $i): ?>
            <tr data-lat="<?= e($i['latitude']) ?>" data-lng="<?= e($i['longitude']) ?>" style="cursor:pointer"
                onmouseover="highlightIncident(<?= e($i['latitude']) ?>, <?= e($i['longitude']) ?>)">
              <td class="small fw-semibold"><?= e($i['ref_no']) ?></td>
              <td>
                <a class="text-decoration-none fw-semibold" href="<?= url('authority/incident_view.php?id=' . $i['id']) ?>">
                  <?= e($i['title']) ?>
                </a>
                <div class="text-muted small"><?= e($i['address']) ?></div>
              </td>
              <td><?= e($i['icon']) ?> <?= e($i['category_name']) ?></td>
              <td><?= severity_badge($i['severity']) ?></td>
              <td><?= status_badge($i['status']) ?></td>
              <td class="small"><?= e($i['reporter_name']) ?><br>
                <span class="text-muted"><i class="bi bi-camera"></i> <?= (int)$i['media_count'] ?> photo(s)</span></td>
              <td class="small text-muted"><?= e(time_ago($i['created_at'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const map = L.map('map').setView([27.7172, 85.3240], 11);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 19, attribution: '&copy; OpenStreetMap'}).addTo(map);

// Recalculate container size once the page layout has settled
setTimeout(() => { map.invalidateSize(); }, 200);

const sevColors = {critical:'#dc3545', high:'#fd7e14', medium:'#ffc107', low:'#198754'};
let markers = [];

function popupHtml(d) {
  return '<strong>' + d.ref_no + '</strong><br>' + d.icon + ' ' + d.category +
         '<br>' + d.title + '<br><em>' + d.time + '</em><br>' +
         '<a href="' + d.url + '">Open & manage →</a>';
}

function loadMarkers() {
  markers.forEach(m => map.removeLayer(m)); markers = [];
  const params = new URLSearchParams(window.location.search);
  fetch('<?= url('api/map_data.php') ?>?' + params)
    .then(r => r.json())
    .then(data => {
      if (!Array.isArray(data)) return;
      data.forEach(d => {
        const m = L.circleMarker([d.lat, d.lng], {
          radius: d.severity === 'critical' ? 11 : 8,
          color: sevColors[d.severity] || '#333',
          fillOpacity: .75, weight: 2
        }).addTo(map).bindPopup(popupHtml(d));
        markers.push(m);
      });
      if (markers.length) map.fitBounds(L.featureGroup(markers).getBounds().pad(.15));
    });
}

function highlightIncident(lat, lng) {
  map.panTo([lat, lng], {animate: true});
  setTimeout(() => { map.invalidateSize(); }, 200);
}

loadMarkers();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
