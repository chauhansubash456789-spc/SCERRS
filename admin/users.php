<?php
require_once __DIR__ . '/../config/config.php';

$user = require_role('admin');
$errors = [];

$departments = db()->query('SELECT * FROM departments ORDER BY name')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'toggle') {
        $id = (int)($_POST['user_id'] ?? 0);
        if ($id !== (int)$user['id']) { // never deactivate yourself
            db()->prepare('UPDATE users SET active = NOT active WHERE id = ?')->execute([$id]);
            flash('success', 'User status updated.');
        } else {
            flash('warning', 'You cannot deactivate your own account.');
        }
        redirect('admin/users.php');
    }

    if ($action === 'create') {
        $name     = trim((string)($_POST['name'] ?? ''));
        $email    = strtolower(trim((string)($_POST['email'] ?? '')));
        $phone    = trim((string)($_POST['phone'] ?? ''));
        $role     = (string)($_POST['role'] ?? 'citizen');
        $deptId   = (int)($_POST['department_id'] ?? 0);
        $password = (string)($_POST['password'] ?? '');

        if ($name === '') {
            $errors[] = 'Name is required.';
        } elseif (preg_match('/[0-9]/', $name)) {
            $errors[] = 'Name must not contain numeric values.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Valid email is required.';
        }
        if (!in_array($role, ['citizen', 'authority', 'admin'], true)) {
            $errors[] = 'Invalid role.';
        }
        if ($role === 'authority' && !$deptId) {
            $errors[] = 'Authority users need a department.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }

        $dup = db()->prepare('SELECT id FROM users WHERE email = ?');
        $dup->execute([$email]);
        if ($dup->fetch()) $errors[] = 'Email already registered.';

        if (!$errors) {
            db()->prepare('INSERT INTO users (name, email, phone, password, role, department_id) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([
                    $name, $email, $phone !== '' ? $phone : null,
                    password_hash($password, PASSWORD_DEFAULT), $role,
                    $role === 'authority' ? $deptId : null,
                ]);
            flash('success', '<strong>' . e($name) . '</strong> created successfully.');
            redirect('admin/users.php');
        }
    }
}

$users = db()->query(
    'SELECT u.*, d.name AS department_name,
            (SELECT COUNT(*) FROM incidents i WHERE i.user_id = u.id) AS reports
     FROM users u LEFT JOIN departments d ON d.id = u.department_id
     ORDER BY FIELD(u.role, "admin", "authority", "citizen"), u.created_at DESC'
)->fetchAll();

$page_title = 'Users';
require __DIR__ . '/../includes/header.php';
?>

<div class="row g-4">
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-person-plus"></i> Add user</div>
      <div class="card-body">
        <?php foreach ($errors as $err): ?>
          <div class="alert alert-danger py-2"><?= e($err) ?></div>
        <?php endforeach; ?>
        <form method="post" novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="create">
          <div class="mb-2"><input type="text" name="name" class="form-control" placeholder="Full name *" pattern="^[^0-9]+$" title="Name must not contain numeric values" required></div>
          <div class="mb-2"><input type="email" name="email" class="form-control" placeholder="Email *" required></div>
          <div class="mb-2"><input type="tel" name="phone" class="form-control" placeholder="Phone"></div>
          <div class="mb-2 row g-2">
            <div class="col-7">
              <select name="role" class="form-select" id="role-select">
                <option value="citizen">Citizen</option>
                <option value="authority">Authority</option>
                <option value="admin">Admin</option>
              </select>
            </div>
            <div class="col-5">
              <select name="department_id" class="form-select d-none" id="dept-select">
                <option value="">Department…</option>
                <?php foreach ($departments as $d): ?>
                  <option value="<?= (int)$d['id'] ?>"><?= e($d['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="mb-3"><input type="password" name="password" class="form-control" placeholder="Password * (min 8 chars)" minlength="8" required></div>
          <button type="submit" class="btn btn-danger w-100">Create User</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-people"></i> All users (<?= count($users) ?>)</div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr><th>Name</th><th>Role</th><th>Department</th><th>Reports</th><th>Status</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($users as $u): ?>
              <tr>
                <td>
                  <strong><?= e($u['name']) ?></strong>
                  <div class="text-muted small"><?= e($u['email']) ?><?= $u['phone'] ? ' · ' . e($u['phone']) : '' ?></div>
                  <?php if ($u['role'] === 'authority' && $u['designation']): ?>
                    <div class="text-muted small"><i class="bi bi-shield-check"></i> <?= e($u['designation']) ?> &middot; ID <?= e($u['officer_id']) ?></div>
                  <?php endif; ?>
                </td>
                <td>
                  <?php
                  $badge = ['admin' => 'dark', 'authority' => 'danger', 'citizen' => 'secondary'][$u['role']];
                  echo '<span class="badge text-bg-' . $badge . '">' . e(ucfirst($u['role'])) . '</span>';
                  ?>
                </td>
                <td class="small"><?= e($u['department_name'] ?: '—') ?></td>
                <td><?= (int)$u['reports'] ?></td>
                <td><?= (int)$u['active']
                      ? '<span class="badge text-bg-success">Active</span>'
                      : '<span class="badge text-bg-secondary">Disabled</span>' ?></td>
                <td class="text-end">
                  <?php if ((int)$u['id'] !== (int)$user['id']): ?>
                    <form method="post" class="d-inline">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="toggle">
                      <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                      <button class="btn btn-sm btn-outline-<?= (int)$u['active'] ? 'danger' : 'success' ?>">
                        <?= (int)$u['active'] ? 'Disable' : 'Enable' ?>
                      </button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
document.getElementById('role-select').addEventListener('change', function () {
  document.getElementById('dept-select').classList.toggle('d-none', this.value !== 'authority');
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
