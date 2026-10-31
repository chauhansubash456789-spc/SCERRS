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
        $icon  = trim((string)($_POST['icon'] ?? '')) ?: '⚠️';
        $deptId = (int)($_POST['department_id'] ?? 0);
        if ($name !== '' && $deptId) {
            if (preg_match('/[0-9]/', $name)) {
                $errors[] = 'Category name must not contain numeric values.';
            } else {
                try {
                    db()->prepare('INSERT INTO categories (name, department_id, icon) VALUES (?, ?, ?)')
                        ->execute([$name, $deptId, $icon]);
                    flash('success', 'Category added.');
                } catch (PDOException) {
                    flash('danger', 'A category with that name already exists.');
                }
            }
        } else {
            flash('warning', 'Name and department are required.');
        }
        if (!$errors) {
            redirect('admin/categories.php');
        }
    }

    if ($action === 'toggle') {
        db()->prepare('UPDATE categories SET active = NOT active WHERE id = ?')
            ->execute([(int)($_POST['category_id'] ?? 0)]);
        redirect('admin/categories.php');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['category_id'] ?? 0);
        $used = db()->prepare('SELECT COUNT(*) FROM incidents WHERE category_id = ?');
        $used->execute([$id]);
        if ((int)$used->fetchColumn() === 0) {
            db()->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
            flash('success', 'Category deleted.');
        } else {
            flash('warning', 'Category has incidents attached — deactivate it instead.');
        }
        redirect('admin/categories.php');
    }
}

$categories = db()->query(
    'SELECT c.*, d.name AS department_name,
            (SELECT COUNT(*) FROM incidents i WHERE i.category_id = c.id) AS incident_count
     FROM categories c JOIN departments d ON d.id = c.department_id
     ORDER BY c.active DESC, c.name'
)->fetchAll();

$page_title = 'Incident Categories';
require __DIR__ . '/../includes/header.php';
?>

<div class="row g-4">
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-plus-circle"></i> Add category</div>
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
          <div class="input-group mb-2">
            <span class="input-group-text">Icon</span>
            <input type="text" name="icon" class="form-control" maxlength="10" placeholder="🔥" style="max-width:90px">
            <input type="text" name="name" class="form-control" placeholder="Category name *" required>
          </div>
          <select name="department_id" class="form-select mb-3" required>
            <option value="">Handled by… *</option>
            <?php foreach ($departments as $d): ?>
              <option value="<?= (int)$d['id'] ?>"><?= e($d['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-danger w-100">Add Category</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-tags"></i> Categories (<?= count($categories) ?>)</div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light"><tr><th></th><th>Category</th><th>Department</th><th>Reports</th><th>Status</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($categories as $c): ?>
              <tr>
                <td class="fs-4"><?= e($c['icon']) ?></td>
                <td class="fw-semibold"><?= e($c['name']) ?></td>
                <td class="small"><?= e($c['department_name']) ?></td>
                <td><?= (int)$c['incident_count'] ?></td>
                <td><?= (int)$c['active'] ? '<span class="badge text-bg-success">Active</span>' : '<span class="badge text-bg-secondary">Inactive</span>' ?></td>
                <td class="text-end text-nowrap">
                  <form method="post" class="d-inline"><?= csrf_field() ?>
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="category_id" value="<?= (int)$c['id'] ?>">
                    <button class="btn btn-sm btn-outline-primary"><?= (int)$c['active'] ? 'Deactivate' : 'Activate' ?></button>
                  </form>
                  <?php if (!(int)$c['incident_count']): ?>
                    <form method="post" class="d-inline ms-1"><?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="category_id" value="<?= (int)$c['id'] ?>">
                      <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
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

<?php require __DIR__ . '/../includes/footer.php'; ?>
