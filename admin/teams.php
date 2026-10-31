<?php
require_once __DIR__ . '/../config/config.php';

$user = require_role('admin');
$errors = [];
$departments = db()->query('SELECT * FROM departments ORDER BY name')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'create') {
        $name  = trim((string)($_POST['name'] ?? ''));
        $deptId = (int)($_POST['department_id'] ?? 0);
        $contact = trim((string)($_POST['contact'] ?? ''));
        if ($name !== '' && $deptId) {
            if (preg_match('/[0-9]/', $name)) {
                $errors[] = 'Team name must not contain numeric values.';
            } else {
                db()->prepare('INSERT INTO response_teams (name, department_id, contact) VALUES (?, ?, ?)')
                    ->execute([$name, $deptId, $contact !== '' ? $contact : null]);
                flash('success', 'Team added.');
            }
        } else {
            flash('warning', 'Team name and department are required.');
        }
        if (!$errors) {
            redirect('admin/teams.php');
        }
    }

    if ($action === 'toggle') {
        db()->prepare('UPDATE response_teams SET active = NOT active WHERE id = ?')
            ->execute([(int)($_POST['team_id'] ?? 0)]);
        redirect('admin/teams.php');
    }
}

$teams = db()->query(
    'SELECT t.*, d.name AS department_name,
            (SELECT COUNT(*) FROM assignments a JOIN incidents i ON i.id = a.incident_id
              WHERE a.team_id = t.id AND i.status IN ("acknowledged","in_progress")) AS active_tasks
     FROM response_teams t JOIN departments d ON d.id = t.department_id
     ORDER BY d.name, t.name'
)->fetchAll();

$page_title = 'Response Teams';
require __DIR__ . '/../includes/header.php';
?>

<div class="row g-4">
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-plus-circle"></i> Add team</div>
      <div class="card-body">
        <?php if ($errors): ?>
          <div class="alert alert-danger">
            <strong>Please fix the following:</strong>
            <ul>
              <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="create">
          <input type="text" name="name" class="form-control mb-2" placeholder="Team name *" required>
          <select name="department_id" class="form-select mb-2" required>
            <option value="">Department… *</option>
            <?php foreach ($departments as $d): ?>
              <option value="<?= (int)$d['id'] ?>"><?= e($d['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <input type="text" name="contact" class="form-control mb-3" placeholder="Contact number (e.g., 100)">
          <button type="submit" class="btn btn-danger w-100">Add Team</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-truck"></i> Response teams (<?= count($teams) ?>)</div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light"><tr><th>Team</th><th>Department</th><th>Contact</th><th>Active tasks</th><th>Status</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($teams as $t): ?>
              <tr>
                <td class="fw-semibold"><?= e($t['name']) ?></td>
                <td class="small"><?= e($t['department_name']) ?></td>
                <td class="small"><?= e($t['contact'] ?: '—') ?></td>
                <td><?= (int)$t['active_tasks'] ?></td>
                <td><?= (int)$t['active'] ? '<span class="badge text-bg-success">Active</span>' : '<span class="badge text-bg-secondary">Inactive</span>' ?></td>
                <td class="text-end">
                  <form method="post"><?= csrf_field() ?>
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="team_id" value="<?= (int)$t['id'] ?>">
                    <button class="btn btn-sm btn-outline-<?= (int)$t['active'] ? 'danger' : 'success' ?>">
                      <?= (int)$t['active'] ? 'Deactivate' : 'Activate' ?>
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
