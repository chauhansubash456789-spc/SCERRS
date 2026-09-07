<?php
require_once __DIR__ . '/../config/config.php';

// Explicitly enable chart loading
$load_charts = true;

$user = require_role('authority', 'admin');
$isAdmin = $user['role'] === 'admin';
$deptId = (int)$user['department_id'];

/* ---- Aggregate stats (department-scoped unless admin) ---- */
$where = $isAdmin ? '' : 'WHERE c.department_id = ' . $deptId;

$totals = db()->query(
    "SELECT COUNT(*) total,
            SUM(i.status='pending') pending,
            SUM(i.status IN ('acknowledged','in_progress')) active,
            SUM(i.status='resolved') resolved,
            SUM(i.status='rejected') rejected
     FROM incidents i JOIN categories c ON c.id=i.category_id $where"
)->fetch();

$byCategory = db()->query(
    "SELECT c.name, c.icon, COUNT(i.id) cnt
     FROM incidents i JOIN categories c ON c.id=i.category_id $where
     GROUP BY c.id ORDER BY cnt DESC"
)->fetchAll();

$bySeverity = db()->query(
    "SELECT i.severity, COUNT(i.id) cnt
     FROM incidents i JOIN categories c ON c.id=i.category_id $where
     GROUP BY i.severity"
)->fetchAll();

$trendRows = db()->query(
    "SELECT DATE(i.created_at) d, COUNT(i.id) cnt
     FROM incidents i JOIN categories c ON c.id=i.category_id $where" .
     ($where ? ' AND' : ' WHERE') . " i.created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
     GROUP BY DATE(i.created_at)"
)->fetchAll();
// Fill missing days
$trend = [];
for ($k = 13; $k >= 0; $k--) {
    $day = date('Y-m-d', strtotime("-$k days"));
    $trend[$day] = 0;
}
foreach ($trendRows as $r) {
    if (isset($trend[$r['d']])) $trend[$r['d']] = (int)$r['cnt'];
}

$avgRow = db()->query(
    "SELECT AVG(TIMESTAMPDIFF(HOUR, i.created_at,
        (SELECT MAX(h.changed_at) FROM status_histories h WHERE h.incident_id=i.id AND h.status='resolved'))) avg_hrs
     FROM incidents i JOIN categories c ON c.id=i.category_id $where" .
     ($where ? ' AND' : ' WHERE') . " i.status='resolved'"
)->fetch();

$avgFeedback = db()->query(
    "SELECT AVG(f.rating) rating FROM feedback f JOIN incidents i ON i.id=f.incident_id JOIN categories c ON c.id=i.category_id $where"
)->fetch();

$page_title = 'Analytics';
require __DIR__ . '/../includes/header.php';
?>

<h3 class="fw-bold mb-4"><i class="bi bi-graph-up"></i> Analytics <?= $isAdmin ? '(all agencies)' : '— ' . e($user['department_name']) ?></h3>

<div class="row g-3 mb-4">
  <?php
  $cards = [
      ['Total incidents', (int)$totals['total'], 'secondary'],
      ['Pending', (int)$totals['pending'], 'warning'],
      ['Active', (int)$totals['active'], 'primary'],
      ['Resolved', (int)$totals['resolved'], 'success'],
      ['Avg resolution', $avgRow['avg_hrs'] !== null ? round((float)$avgRow['avg_hrs'], 1) . ' h' : '—', 'info'],
      ['Avg citizen rating', $avgFeedback['rating'] !== null ? round((float)$avgFeedback['rating'], 1) . ' / 5' : '—', 'orange'],
  ];
  foreach ($cards as [$label, $value, $color]): ?>
    <div class="col-6 col-md-4 col-lg-2">
      <div class="card border-0 shadow-sm text-center h-100">
        <div class="card-body py-4">
          <div class="fs-3 fw-bold lh-1"><?= is_string($value) ? e($value) : $value ?></div>
          <div class="text-muted small mt-1"><?= e($label) ?></div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="row g-4">
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm" id="cardChartCat">
      <div class="card-body">
        <h6 class="fw-semibold">Incidents by type</h6>
        <canvas id="chartCat" height="230" style="min-height: 230px; max-height: 280px; width: 100%;"></canvas>
        <div class="empty-state d-none mt-3">
          <small>No incident data available</small>
        </div>
        <div class="error-state d-none mt-3" id="errorCat">
          <small>Error loading chart data. <button class="btn btn-link btn-sm text-secondary retry-btn" data-chart="cat">Retry</button></small>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm" id="cardChartSev">
      <div class="card-body">
        <h6 class="fw-semibold">By severity</h6>
        <canvas id="chartSev" height="230" style="min-height: 230px; max-height: 280px; width: 100%;"></canvas>
        <div class="empty-state d-none mt-3" id="emptySev">
          <small>No severity data available</small>
        </div>
        <div class="error-state d-none mt-3" id="errorSev">
          <small>Error loading chart data. <button class="btn btn-link btn-sm text-secondary retry-btn" data-chart="sev">Retry</button></small>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-3">
    <div class="card border-0 shadow-sm" id="cardChartStatus">
      <div class="card-body">
        <h6 class="fw-semibold">Current pipeline</h6>
        <canvas id="chartStatus" height="230" style="min-height: 230px; max-height: 280px; width: 100%;"></canvas>
        <div class="empty-state d-none mt-3" id="emptyStatus">
          <small>No pipeline data available</small>
        </div>
        <div class="error-state d-none mt-3" id="errorStatus">
          <small>Error loading chart data. <button class="btn btn-link btn-sm text-secondary retry-btn" data-chart="status">Retry</button></small>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-12">
    <div class="card border-0 shadow-sm" id="cardChartTrend">
      <div class="card-body">
        <h6 class="fw-semibold">Reports — last 14 days</h6>
        <canvas id="chartTrend" height="80" style="min-height: 80px; max-height: 100px; width: 100%;"></canvas>
        <div class="empty-state d-none mt-3" id="emptyTrend">
          <small>No trend data available</small>
        </div>
        <div class="error-state d-none mt-3" id="errorTrend">
          <small>Error loading chart data. <button class="btn btn-link btn-sm text-secondary retry-btn" data-chart="trend">Retry</button></small>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Chart.js initialization - using data from PHP variables
document.addEventListener('DOMContentLoaded', function() {
    // Check if Chart.js is available
    if (typeof Chart === 'undefined') {
        console.error('Chart.js not loaded');
        // Show empty states if Chart.js not available
        document.querySelectorAll('.empty-state').forEach(el => el.classList.remove('d-none'));
        return;
    }

    // Bar chart - Incidents by type
    const ctxCat = document.getElementById('chartCat').getContext('2d');
    window.chartCat = new Chart(ctxCat, {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_map(function($d) { return $d['icon'] . ' ' . $d['name']; }, $byCategory)) ?>,
            datasets: [{ data: <?= json_encode(array_map(function($d) { return (int)$d['cnt']; }, $byCategory)) ?>, backgroundColor: '#dc3545' }]
        },
        options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
    });

    // Doughnut chart - By severity
    const ctxSev = document.getElementById('chartSev').getContext('2d');
    const sevOrder = { low: '#198754', medium: '#ffc107', high: '#fd7e14', critical: '#dc3545' };
    window.chartSev = new Chart(ctxSev, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_map(function($d) { return $d['severity']; }, $bySeverity)) ?>,
            datasets: [{ data: <?= json_encode(array_map(function($d) { return (int)$d['cnt']; }, $bySeverity)) ?>, backgroundColor: <?= json_encode(array_map(function($d) use ($sevOrder) { return $sevOrder[$d] || '#333'; }, array_column($bySeverity, 'severity'))) ?> ]
        }
    });

    // Pie chart - Current pipeline
    const ctxStatus = document.getElementById('chartStatus').getContext('2d');
    const pipelineData = <?= json_encode(['p' => (int)$totals['pending'], 'a' => (int)$totals['active'], 'r' => (int)$totals['resolved'], 'x' => (int)$totals['rejected']]) ?>;
    window.chartStatus = new Chart(ctxStatus, {
        type: 'pie',
        data: {
            labels: ['Pending', 'Active', 'Resolved', 'Rejected'],
            datasets: [{ data: [pipelineData.p, pipelineData.a, pipelineData.r, pipelineData.x], backgroundColor: ['#6c757d', '#0d6efd', '#198754', '#dc3545'] }]
        }
    });

    // Line chart - Reports last 14 days
    const ctxTrend = document.getElementById('chartTrend').getContext('2d');
    const trendLabels = Object.keys($trend).map(function(k) { return k.slice(5); });
    const trendData = Object.values($trend);
    window.chartTrend = new Chart(ctxTrend, {
        type: 'line',
        data: {
            labels: trendLabels,
            datasets: [{ label: 'Reports', data: trendData, borderColor: '#dc3545', backgroundColor: 'rgba(220,53,69,.15)', fill: true, tension: .35 }]
        },
        options: { scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }, plugins: { legend: { display: false } } }
    });

    // Show empty states if data arrays are empty
    const catLabels = window.chartCat && window.chartCat.data && window.chartCat.data.labels;
    if (!catLabels || catLabels.length === 0) {
        document.querySelector('#cardChartCat .empty-state').classList.remove('d-none');
    }
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>