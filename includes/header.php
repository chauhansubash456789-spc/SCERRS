<?php
require_once __DIR__ . '/../config/config.php';

$page_title  = $page_title ?? APP_NAME;
$load_map    = $load_map    ?? false;
$load_charts = $load_charts ?? false;
$fluid       = $fluid       ?? false;
$user        = current_user();
$unread      = 0;
if ($user) {
    $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$user['id']]);
    $unread = (int)$stmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<script>
/* Apply saved theme before first paint to avoid flashing */
(function () {
  var t = localStorage.getItem('scerrs-theme');
  if (!t) t = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  document.documentElement.setAttribute('data-theme', t);
})();
</script>
<title><?= e($page_title) ?> &middot; <?= e(APP_NAME) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<?php if ($load_map): ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<?php endif; ?>
<link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
<?= $extra_head ?? '' ?>
</head>
<body>

<nav class="navbar navbar-expand-lg site-navbar sticky-top">
  <div class="container">
    <a class="navbar-brand fw-bold" href="<?= url('index.php') ?>">
      <i class="bi bi-broadcast-pin"></i> SCERRS
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav me-auto">
        <li class="nav-item">
          <a class="nav-link <?= (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'index.php') ? 'active' : '' ?>" href="<?= url('index.php') ?>">Home</a>
        </li>
        <?php if (!$user): ?>
          <!-- Guest navigation -->
        <?php elseif ($user['role'] === 'citizen'): ?>
          <li class="nav-item"><a class="nav-link <?= (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'dashboard.php') ? 'active' : '' ?>" href="<?= url('citizen/dashboard.php') ?>">My Reports</a></li>
          <li class="nav-item"><a class="btn btn-light btn-sm text-danger fw-semibold ms-lg-2 mt-1" href="<?= url('citizen/report_new.php') ?>"><i class="bi bi-exclamation-triangle"></i> Report Emergency</a></li>
        <?php elseif ($user['role'] === 'authority'): ?>
          <li class="nav-item"><a class="nav-link <?= (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'dashboard.php') ? 'active' : '' ?>" href="<?= url('authority/dashboard.php') ?>">Incidents</a></li>
          <li class="nav-item"><a class="nav-link <?= (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'analytics.php') ? 'active' : '' ?>" href="<?= url('authority/analytics.php') ?>">Analytics</a></li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link <?= (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'dashboard.php') ? 'active' : '' ?>" href="<?= url('authority/dashboard.php') ?>">All Incidents</a></li>
          <li class="nav-item"><a class="nav-link <?= (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'analytics.php') ? 'active' : '' ?>" href="<?= url('authority/analytics.php') ?>">Analytics</a></li>
          <li class="nav-item"><a class="nav-link <?= (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'users.php') ? 'active' : '' ?>" href="<?= url('admin/users.php') ?>">Users</a></li>
          <li class="nav-item"><a class="nav-link <?= (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'categories.php') ? 'active' : '' ?>" href="<?= url('admin/categories.php') ?>">Categories</a></li>
          <li class="nav-item"><a class="nav-link <?= (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'teams.php') ? 'active' : '' ?>" href="<?= url('admin/teams.php') ?>">Teams</a></li>
        <?php endif; ?>
      </ul>
      <ul class="navbar-nav ms-auto align-items-lg-center">
        <li class="nav-item me-lg-1">
          <button class="btn btn-sm border-0" id="themeToggle" type="button" title="Toggle light/dark theme" aria-label="Toggle theme">
            <i class="bi bi-moon-stars"></i>
          </button>
        </li>
        <?php if ($user): ?>
          <li class="nav-item me-lg-2">
            <a class="nav-link position-relative" href="<?= url('notifications.php') ?>" title="Notifications">
              <i class="bi bi-bell fs-5"></i>
              <?php if ($unread > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-warning text-dark"><?= $unread ?></span>
              <?php endif; ?>
            </a>
          </li>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
              <i class="bi bi-person-circle"></i> <?= e($user['name']) ?>
              <?php if ($user['role'] === 'authority'): ?>
                <small class="opacity-50">(<?= e($user['department_name']) ?>)</small>
              <?php endif; ?>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><h6 class="dropdown-header"><?= e(ucfirst($user['role'])) ?> account</h6></li>
              <li><a class="dropdown-item" href="<?= url('index.php') ?>"><i class="bi bi-house-door me-2"></i> Home</a></li>
              <li><a class="dropdown-item" href="<?= url(role_home($user)) ?>"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item text-danger" href="<?= url('auth/logout.php') ?>"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
            </ul>
          </li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link" href="<?= url('auth/login.php') ?>">Login</a></li>
          <li class="nav-item"><a class="btn btn-danger btn-sm ms-lg-2" href="<?= url('auth/register.php') ?>">Register</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<!-- Emergency Hotlines Marquee -->
<div class="emergency-marquee-container">
  <div class="marquee-label">
    <span class="pulse-icon">🚨</span> Emergency Hotlines:
  </div>

  <div class="marquee-wrapper">
    <div class="marquee-content">
      <!-- First Set of Hotline Badges -->
      <span class="hotline-item police"><span class="agency-name">Nepal Police:</span> <a href="tel:100">100</a></span>
      <span class="hotline-item fire"><span class="agency-name">Fire Brigade:</span> <a href="tel:101">101</a></span>
      <span class="hotline-item ambulance"><span class="agency-name">Ambulance Service:</span> <a href="tel:102">102</a></span>
      <span class="hotline-item traffic"><span class="agency-name">Traffic Police:</span> <a href="tel:103">103</a></span>
      <span class="hotline-item disaster"><span class="agency-name">NEOC Disaster Helpline:</span> <a href="tel:1155">1155</a></span>
      <span class="hotline-item child"><span class="agency-name">Child Helpline:</span> <a href="tel:1098">1098</a></span>

      <!-- Duplicate Set for Seamless Continuous Scrolling -->
      <span class="hotline-item police"><span class="agency-name">Nepal Police:</span> <a href="tel:100">100</a></span>
      <span class="hotline-item fire"><span class="agency-name">Fire Brigade:</span> <a href="tel:101">101</a></span>
      <span class="hotline-item ambulance"><span class="agency-name">Ambulance Service:</span> <a href="tel:102">102</a></span>
      <span class="hotline-item traffic"><span class="agency-name">Traffic Police:</span> <a href="tel:103">103</a></span>
      <span class="hotline-item disaster"><span class="agency-name">NEOC Disaster Helpline:</span> <a href="tel:1155">1155</a></span>
      <span class="hotline-item child"><span class="agency-name">Child Helpline:</span> <a href="tel:1098">1098</a></span>
    </div>
  </div>
</div>

<main class="<?= $fluid ? 'container-fluid px-0' : 'container py-4' ?>">
<?php foreach (get_flashes() as $f): ?>
  <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show" role="alert">
    <?= $f['message'] // already escaped by producers where needed ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endforeach; ?>
