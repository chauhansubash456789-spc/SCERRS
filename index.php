<?php
require_once __DIR__ . '/config/config.php';

$categories = db()->query('SELECT name, icon FROM categories WHERE active = 1 LIMIT 6')->fetchAll();

$user = current_user();

// Quick statistics if logged in
$citizenActiveReports = 0;
if ($user && $user['role'] === 'citizen') {
    $stmt = db()->prepare('SELECT COUNT(*) FROM incidents WHERE user_id = ? AND status IN ("pending", "acknowledged", "in_progress")');
    $stmt->execute([$user['id']]);
    $citizenActiveReports = (int)$stmt->fetchColumn();
}

$deptPendingReports = 0;
if ($user && $user['role'] === 'authority' && !empty($user['department_id'])) {
    $stmt = db()->prepare('SELECT COUNT(*) FROM incidents i JOIN categories c ON c.id = i.category_id WHERE c.department_id = ? AND i.status IN ("pending", "acknowledged", "in_progress")');
    $stmt->execute([$user['department_id']]);
    $deptPendingReports = (int)$stmt->fetchColumn();
}

$adminTotalPending = 0;
if ($user && $user['role'] === 'admin') {
    $adminTotalPending = (int)db()->query('SELECT COUNT(*) FROM incidents WHERE status = "pending"')->fetchColumn();
}

$fluid = true; // full-width hero layout
require __DIR__ . '/includes/header.php';
?>

<section class="hero text-white mb-0">
  <div id="heroCarousel" class="carousel slide carousel-fade hero-carousel" data-bs-ride="carousel"
    data-bs-interval="6000">
    <div class="carousel-indicators">
      <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="0" class="active" aria-current="true"
        aria-label="Slide 1"></button>
      <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="1" aria-label="Slide 2"></button>
      <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="2" aria-label="Slide 3"></button>
      <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="3" aria-label="Slide 4"></button>
      <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="4" aria-label="Slide 5"></button>
    </div>
    <div class="carousel-inner">
      <div class="carousel-item active"
        style="background-image:url('https://images.unsplash.com/photo-1741110175416-46fff473c8ec?fm=jpg&q=60&w=1800&auto=format&fit=crop')">
      </div>
      <div class="carousel-item"
        style="background-image:url('https://images.unsplash.com/photo-1768472085495-027620da71c9?fm=jpg&q=60&w=1800&auto=format&fit=crop')">
      </div>
      <div class="carousel-item"
        style="background-image:url('https://images.unsplash.com/photo-1702658509997-86458a6493e2?fm=jpg&q=60&w=1800&auto=format&fit=crop')">
      </div>
      <div class="carousel-item"
        style="background-image:url('https://images.unsplash.com/photo-1672559987522-897ef4612604?fm=jpg&q=60&w=1800&auto=format&fit=crop')">
      </div>
      <div class="carousel-item"
        style="background-image:url('https://images.unsplash.com/photo-1657069342814-ef2dcfb25f6f?fm=jpg&q=60&w=1800&auto=format&fit=crop')">
      </div>
    </div>
    <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
      <span class="carousel-control-prev-icon" aria-hidden="true"></span>
      <span class="visually-hidden">Previous</span>
    </button>
    <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
      <span class="carousel-control-next-icon" aria-hidden="true"></span>
      <span class="visually-hidden">Next</span>
    </button>
  </div>

  <div class="hero-overlay"></div>

  <div class="container p-5 hero-content">
    <div class="row align-items-center g-4">
      <div class="col-lg-7">
        <?php if (!$user): ?>
          <span class="badge text-bg-light text-danger fw-semibold mb-2">Tribhuvan University &middot; BCA Project</span>
        <?php elseif ($user['role'] === 'citizen'): ?>
          <span class="badge text-bg-warning text-dark fw-semibold mb-2">
            <i class="bi bi-person-check-fill me-1"></i> Citizen Portal &middot; <?= e($user['name']) ?>
          </span>
        <?php elseif ($user['role'] === 'authority'): ?>
          <span class="badge text-bg-info text-dark fw-semibold mb-2">
            <i class="bi bi-shield-check me-1"></i> <?= e($user['department_name'] ?? 'Authority Responder') ?> &middot; <?= e($user['name']) ?>
          </span>
        <?php else: /* admin */ ?>
          <span class="badge text-bg-danger fw-semibold mb-2">
            <i class="bi bi-shield-lock-fill me-1"></i> System Administrator &middot; <?= e($user['name']) ?>
          </span>
        <?php endif; ?>

        <h1 class="display-5 fw-bold"><?= e(APP_NAME) ?></h1>
        <p class="lead mb-1"><strong><?= e(APP_TAGLINE) ?></strong></p>
        <p class="mb-4 opacity-75">
          Report emergencies instantly with auto-GPS location and photo evidence.
          Nepal Police, Fire Brigade, Ambulance, Municipality and Disaster agencies respond
          from one unified dashboard &mdash; cutting response time and saving lives.
        </p>

        <?php if (!$user): ?>
          <a href="<?= url('auth/register.php') ?>" class="btn btn-sos btn-lg fw-semibold me-2">
            <i class="bi bi-megaphone"></i> Report an Emergency
          </a>
          <a href="<?= url('auth/login.php') ?>" class="btn btn-outline-light btn-lg">
            <i class="bi bi-box-arrow-in-right"></i> Authority Login
          </a>
        <?php elseif ($user['role'] === 'citizen'): ?>
          <a href="<?= url('citizen/report_new.php') ?>" class="btn btn-sos btn-lg fw-semibold me-2">
            <i class="bi bi-megaphone"></i> Report an Emergency
          </a>
          <a href="<?= url('citizen/dashboard.php') ?>" class="btn btn-outline-light btn-lg">
            <i class="bi bi-speedometer2"></i> My Dashboard <?= $citizenActiveReports > 0 ? "($citizenActiveReports active)" : '' ?>
          </a>
        <?php elseif ($user['role'] === 'authority'): ?>
          <a href="<?= url('authority/dashboard.php') ?>" class="btn btn-sos btn-lg fw-semibold me-2">
            <i class="bi bi-speedometer2"></i> Incident Dashboard <?= $deptPendingReports > 0 ? "($deptPendingReports active)" : '' ?>
          </a>
          <a href="<?= url('authority/analytics.php') ?>" class="btn btn-outline-light btn-lg">
            <i class="bi bi-graph-up"></i> Live Analytics
          </a>
        <?php else: /* admin */ ?>
          <a href="<?= url('authority/dashboard.php') ?>" class="btn btn-sos btn-lg fw-semibold me-2">
            <i class="bi bi-shield-lock"></i> All Incidents <?= $adminTotalPending > 0 ? "($adminTotalPending pending)" : '' ?>
          </a>
          <a href="<?= url('admin/users.php') ?>" class="btn btn-outline-light btn-lg">
            <i class="bi bi-people"></i> Manage Users
          </a>
        <?php endif; ?>
      </div>
      <div class="col-lg-5">
        <div class="row g-3 text-center">
          <?php foreach (['🚑 Faster dispatch', '📍 GPS-tagged reports', '📸 Photo evidence', '📊 Live analytics'] as $feat): ?>
            <div class="col-6">
              <div class="bg-white bg-opacity-10 rounded-3 p-3 small fw-semibold"><?= e($feat) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<?php if ($user): ?>
<section class="border-bottom py-3" style="background: var(--surface);">
  <div class="container">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
      <div class="d-flex align-items-center gap-3">
        <div class="rounded-circle d-flex align-items-center justify-content-center bg-danger bg-opacity-10 text-danger" style="width: 44px; height: 44px; flex-shrink: 0;">
          <?php if ($user['role'] === 'citizen'): ?>
            <i class="bi bi-person fs-4"></i>
          <?php elseif ($user['role'] === 'authority'): ?>
            <i class="bi bi-shield-check fs-4 text-primary"></i>
          <?php else: ?>
            <i class="bi bi-shield-lock fs-4 text-danger"></i>
          <?php endif; ?>
        </div>
        <div>
          <div class="fw-bold mb-0">
            Welcome, <?= e($user['name']) ?>
            <span class="badge text-bg-<?= $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'authority' ? 'primary' : 'secondary') ?> ms-1">
              <?= e(ucfirst($user['role'])) ?><?= $user['role'] === 'authority' && !empty($user['department_name']) ? ' &middot; ' . e($user['department_name']) : '' ?>
            </span>
          </div>
          <small class="text-muted">
            <?php if ($user['role'] === 'citizen'): ?>
              Emergency reporting portal active. You can report incidents in real time.
            <?php elseif ($user['role'] === 'authority'): ?>
              Authority agency responder terminal ready.
            <?php else: ?>
              System administrator console active.
            <?php endif; ?>
          </small>
        </div>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <?php if ($user['role'] === 'citizen'): ?>
          <a href="<?= url('citizen/report_new.php') ?>" class="btn btn-danger btn-sm fw-semibold">
            <i class="bi bi-exclamation-triangle"></i> New Report
          </a>
          <a href="<?= url('citizen/dashboard.php') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-speedometer2"></i> My Dashboard
          </a>
        <?php elseif ($user['role'] === 'authority'): ?>
          <a href="<?= url('authority/dashboard.php') ?>" class="btn btn-danger btn-sm fw-semibold">
            <i class="bi bi-speedometer2"></i> Incidents
          </a>
          <a href="<?= url('authority/analytics.php') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-graph-up"></i> Analytics
          </a>
        <?php else: ?>
          <a href="<?= url('authority/dashboard.php') ?>" class="btn btn-danger btn-sm fw-semibold">
            <i class="bi bi-shield-lock"></i> Incidents
          </a>
          <a href="<?= url('authority/analytics.php') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-graph-up"></i> Analytics
          </a>
          <a href="<?= url('admin/users.php') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-people"></i> Users
          </a>
          <a href="<?= url('admin/categories.php') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-tags"></i> Categories
          </a>
          <a href="<?= url('admin/teams.php') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-diagram-3"></i> Teams
          </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<div class="container py-5">
  <div class="row g-4" id="how-it-works">
    <div class="col-md-8">
      <h4 class="fw-bold mb-3">How it works</h4>
      <div class="row g-3">
        <?php
        $steps = [
          ['bi-geo-alt', '1. Pin your location', 'Your device GPS is captured automatically when you report.'],
          ['bi-camera', '2. Add evidence', 'Attach a photo of the incident for faster verification.'],
          ['bi-send', '3. Categorize & send', 'The right agency receives the alert instantly.'],
          ['bi-clock-history', '4. Track in real time', 'Watch status move from pending to resolved.'],
        ];
        foreach ($steps as [$icon, $title, $text]): ?>
          <div class="col-sm-6">
            <div class="card h-100 border-0 shadow-sm">
              <div class="card-body">
                <h5><i class="bi <?= $icon ?> text-danger"></i> <?= e($title) ?></h5>
                <p class="text-muted mb-0 small"><?= e($text) ?></p>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="col-md-4">
      <h4 class="fw-bold mb-3">Incident types</h4>
      <div class="card border-0 shadow-sm">
        <ul class="list-group list-group-flush">
          <?php foreach ($categories as $c): ?>
            <li class="list-group-item d-flex align-items-center gap-2">
              <span class="fs-5"><?= e($c['icon']) ?></span> <?= e($c['name']) ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <div class="alert alert-danger mt-3 small mb-0">
        <strong>In a life-threatening emergency?</strong> Always also call
        Police <strong>100</strong>, Ambulance <strong>102</strong>, or Fire <strong>101</strong>.
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>