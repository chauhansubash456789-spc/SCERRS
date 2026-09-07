<?php
require_once __DIR__ . '/../config/config.php';

if (is_logged_in()) {
    redirect(role_home(current_user()));
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email    = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Please enter both email and password.';
    } else {
        $stmt = db()->prepare(
            'SELECT u.*, d.name AS department_name
             FROM users u LEFT JOIN departments d ON d.id = u.department_id
             WHERE u.email = ? LIMIT 1'
        );
        $stmt->execute([$email]);
        $found = $stmt->fetch();

        if (!$found || !password_verify($password, $found['password'])) {
            $error = 'Invalid email or password.';
        } elseif (!(int)$found['active']) {
            $error = 'Your account has been deactivated. Contact the administrator.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$found['id'];
            flash('success', 'Welcome back, <strong>' . e($found['name']) . '</strong>!');
            redirect(role_home($found));
        }
    }
}

$page_title = 'Login';
require __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center mt-4">
  <div class="col-md-5 col-lg-4">
    <div class="card shadow-sm border-0">
      <div class="card-body p-4">
        <div class="text-center mb-3">
          <i class="bi bi-broadcast-pin text-danger fs-1"></i>
          <h4 class="fw-bold mb-0">Sign in to <?= e(APP_NAME) ?></h4>
          <p class="text-muted small">Report emergencies. Respond faster.</p>
        </div>

        <?php if ($error): ?>
          <div class="alert alert-danger py-2"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" novalidate>
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label">Email address</label>
            <input type="email" name="email" class="form-control" value="<?= e($email) ?>" required autofocus>
          </div>
          <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required>
          </div>
          <button type="submit" class="btn btn-danger w-100 fw-semibold">Login</button>
        </form>

        <p class="text-center small mt-3 mb-0">
          New citizen? <a href="<?= url('auth/register.php') ?>">Create an account</a>
        </p>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
