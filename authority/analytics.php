<?php
require_once __DIR__ . '/../config/config.php';

// Explicitly enable chart loading
$load_charts = true;

$user = require_role('authority', 'admin');
$isAdmin = $user['role'] === 'admin';
$deptId = (int)($user['department_id'] ?? 0);

/* ---- Aggregate stats (department-scoped unless admin) ---- */
$where = $isAdmin ? '' : 'WHERE c.department_id = ' . $deptId;

$totals = db()->query(
    "SELECT COUNT(*) total,
            SUM(i.status='pending') pending,
            SUM(i.status IN ('acknowledged','in_progress')) active,
            SUM(i.status='resolved') resolved,
            SUM(i.status='rejected') rejected
     FROM incidents i JOIN categories c ON c.id=i.category_id $where"
)->fetch() ?: [];

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
)->fetch() ?: [];

$avgFeedback = db()->query(
    "SELECT AVG(f.rating) rating FROM feedback f JOIN incidents i ON i.id=f.incident_id JOIN categories c ON c.id=i.category_id $where"
)->fetch() ?: [];

// Prepare data for Chart.js
$catLabels = [];
$catData   = [];
foreach ($byCategory as $c) {
    $catLabels[] = ($c['icon'] ? $c['icon'] . ' ' : '') . $c['name'];
    $catData[]   = (int)$c['cnt'];
}

$sevColorMap = [
    'low'      => '#198754',
    'medium'   => '#ffc107',
    'high'     => '#fd7e14',
    'critical' => '#dc3545'
];
$sevLabels = [];
$sevData   = [];
$sevColors = [];
foreach ($bySeverity as $s) {
    $sevKey = strtolower((string)$s['severity']);
    $sevLabels[] = ucfirst($sevKey);
    $sevData[]   = (int)$s['cnt'];
    $sevColors[] = $sevColorMap[$sevKey] ?? '#6c757d';
}

$statusLabels = ['Pending', 'Active', 'Resolved', 'Rejected'];
$statusData   = [
    (int)($totals['pending'] ?? 0),
    (int)($totals['active'] ?? 0),
    (int)($totals['resolved'] ?? 0),
    (int)($totals['rejected'] ?? 0)
];
$statusColors = ['#ffc107', '#0d6efd', '#198754', '#dc3545'];

$trendLabels = [];
$trendValues = [];
foreach ($trend as $dateKey => $count) {
    $trendLabels[] = date('M j', strtotime($dateKey));
    $trendValues[] = (int)$count;
}

$page_title = 'Analytics';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
  <div>
    <h3 class="fw-bold mb-1"><i class="bi bi-graph-up text-danger me-2"></i>Analytics Overview</h3>
    <p class="text-muted small mb-0"><?= $isAdmin ? 'Unified aggregate data across all authority agencies' : 'Agency dashboard &mdash; ' . e($user['department_name'] ?? 'Assigned Agency') ?></p>
  </div>
  <a href="<?= url('authority/dashboard.php') ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i> Back to Incidents
  </a>
</div>

<div class="row g-3 mb-4">
  <?php
  $cards = [
      ['Total incidents', (int)($totals['total'] ?? 0), 'secondary', 'bi-folder'],
      ['Pending', (int)($totals['pending'] ?? 0), 'warning', 'bi-clock-history'],
      ['Active', (int)($totals['active'] ?? 0), 'primary', 'bi-lightning-charge'],
      ['Resolved', (int)($totals['resolved'] ?? 0), 'success', 'bi-check-circle'],
      ['Avg resolution', !empty($avgRow['avg_hrs']) ? round((float)$avgRow['avg_hrs'], 1) . ' hrs' : '—', 'info', 'bi-stopwatch'],
      ['Avg citizen rating', !empty($avgFeedback['rating']) ? round((float)$avgFeedback['rating'], 1) . ' / 5' : '—', 'orange', 'bi-star'],
  ];
  foreach ($cards as [$label, $value, $color, $icon]): ?>
    <div class="col-6 col-md-4 col-lg-2">
      <div class="card border-0 shadow-sm text-center h-100">
        <div class="card-body py-3 px-2">
          <div class="fs-4 fw-bold lh-1 mb-1"><?= is_string($value) ? e($value) : $value ?></div>
          <div class="text-muted small"><i class="bi <?= $icon ?> me-1"></i><?= e($label) ?></div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="row g-4">
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm h-100" id="cardChartCat">
      <div class="card-body">
        <h6 class="fw-semibold mb-3"><i class="bi bi-tags text-danger me-1"></i> Incidents by type</h6>
        <div style="position: relative; height: 260px;">
          <canvas id="chartCat"></canvas>
        </div>
        <div class="empty-state d-none text-center py-4" id="emptyCat">
          <i class="bi bi-inbox fs-2 text-muted"></i>
          <p class="text-muted small mt-2 mb-0">No category incident data available</p>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm h-100" id="cardChartSev">
      <div class="card-body">
        <h6 class="fw-semibold mb-3"><i class="bi bi-exclamation-triangle text-warning me-1"></i> By severity</h6>
        <div style="position: relative; height: 260px;">
          <canvas id="chartSev"></canvas>
        </div>
        <div class="empty-state d-none text-center py-4" id="emptySev">
          <i class="bi bi-shield-check fs-2 text-muted"></i>
          <p class="text-muted small mt-2 mb-0">No severity incident data available</p>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-3">
    <div class="card border-0 shadow-sm h-100" id="cardChartStatus">
      <div class="card-body">
        <h6 class="fw-semibold mb-3"><i class="bi bi-pie-chart text-primary me-1"></i> Current pipeline</h6>
        <div style="position: relative; height: 260px;">
          <canvas id="chartStatus"></canvas>
        </div>
        <div class="empty-state d-none text-center py-4" id="emptyStatus">
          <i class="bi bi-clipboard-data fs-2 text-muted"></i>
          <p class="text-muted small mt-2 mb-0">No pipeline data available</p>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-12">
    <div class="card border-0 shadow-sm" id="cardChartTrend">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <h6 class="fw-semibold mb-0"><i class="bi bi-activity text-danger me-1"></i> Incident reports &mdash; Last 14 days</h6>
          <span class="badge text-bg-light border small text-muted">Daily Incident Frequency</span>
        </div>
        <div style="position: relative; height: 180px;">
          <canvas id="chartTrend"></canvas>
        </div>
        <div class="empty-state d-none text-center py-4" id="emptyTrend">
          <p class="text-muted small mb-0">No reports filed in the last 14 days</p>
        </div>
      </div>
    </div>
  </div>
</div>

<?php
ob_start();
?>
<script>
(function() {
  function initCharts() {
    if (typeof Chart === 'undefined') {
      console.warn('Chart.js not available yet, retrying...');
      setTimeout(initCharts, 150);
      return;
    }

    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    const textColor = isDark ? '#94A3B8' : '#64748B';
    const gridColor = isDark ? 'rgba(255, 255, 255, 0.08)' : 'rgba(0, 0, 0, 0.06)';

    Chart.defaults.color = textColor;
    Chart.defaults.font.family = 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif';

    // 1. Incidents by Category
    const catCanvas = document.getElementById('chartCat');
    const catData = <?= json_encode($catData) ?>;
    const catLabels = <?= json_encode($catLabels) ?>;

    if (catData.length === 0 || catData.every(v => v === 0)) {
      document.getElementById('emptyCat')?.classList.remove('d-none');
      if (catCanvas) catCanvas.style.display = 'none';
    } else if (catCanvas) {
      new Chart(catCanvas, {
        type: 'bar',
        data: {
          labels: catLabels,
          datasets: [{
            label: 'Incidents',
            data: catData,
            backgroundColor: 'rgba(230, 57, 70, 0.85)',
            hoverBackgroundColor: '#c1121f',
            borderRadius: 6
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false }
          },
          scales: {
            x: {
              grid: { display: false },
              ticks: { color: textColor }
            },
            y: {
              beginAtZero: true,
              grid: { color: gridColor },
              ticks: { precision: 0, color: textColor }
            }
          }
        }
      });
    }

    // 2. Incidents by Severity
    const sevCanvas = document.getElementById('chartSev');
    const sevData = <?= json_encode($sevData) ?>;
    const sevLabels = <?= json_encode($sevLabels) ?>;
    const sevColors = <?= json_encode($sevColors) ?>;

    if (sevData.length === 0 || sevData.every(v => v === 0)) {
      document.getElementById('emptySev')?.classList.remove('d-none');
      if (sevCanvas) sevCanvas.style.display = 'none';
    } else if (sevCanvas) {
      new Chart(sevCanvas, {
        type: 'doughnut',
        data: {
          labels: sevLabels,
          datasets: [{
            data: sevData,
            backgroundColor: sevColors,
            hoverOffset: 6,
            borderWidth: 2,
            borderColor: isDark ? '#1E293B' : '#ffffff'
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: 'bottom',
              labels: { color: textColor, padding: 15, boxWidth: 12 }
            }
          },
          cutout: '65%'
        }
      });
    }

    // 3. Current Pipeline Status
    const statusCanvas = document.getElementById('chartStatus');
    const statusData = <?= json_encode($statusData) ?>;
    const statusLabels = <?= json_encode($statusLabels) ?>;
    const statusColors = <?= json_encode($statusColors) ?>;

    if (statusData.every(v => v === 0)) {
      document.getElementById('emptyStatus')?.classList.remove('d-none');
      if (statusCanvas) statusCanvas.style.display = 'none';
    } else if (statusCanvas) {
      new Chart(statusCanvas, {
        type: 'pie',
        data: {
          labels: statusLabels,
          datasets: [{
            data: statusData,
            backgroundColor: statusColors,
            hoverOffset: 6,
            borderWidth: 2,
            borderColor: isDark ? '#1E293B' : '#ffffff'
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: 'bottom',
              labels: { color: textColor, padding: 12, boxWidth: 12 }
            }
          }
        }
      });
    }

    // 4. Trend Last 14 Days
    const trendCanvas = document.getElementById('chartTrend');
    const trendLabels = <?= json_encode($trendLabels) ?>;
    const trendValues = <?= json_encode($trendValues) ?>;

    if (trendCanvas) {
      new Chart(trendCanvas, {
        type: 'line',
        data: {
          labels: trendLabels,
          datasets: [{
            label: 'Reports Filed',
            data: trendValues,
            borderColor: '#e63946',
            backgroundColor: 'rgba(230, 57, 70, 0.12)',
            fill: true,
            tension: 0.35,
            pointRadius: 4,
            pointHoverRadius: 6,
            pointBackgroundColor: '#e63946',
            borderWidth: 2.5
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false }
          },
          scales: {
            x: {
              grid: { display: false },
              ticks: { color: textColor }
            },
            y: {
              beginAtZero: true,
              grid: { color: gridColor },
              ticks: { precision: 0, color: textColor }
            }
          }
        }
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCharts);
  } else {
    initCharts();
  }
})();
</script>
<?php
$extra_scripts = ob_get_clean();
require __DIR__ . '/../includes/footer.php';
?>