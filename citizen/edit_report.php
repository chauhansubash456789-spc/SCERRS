<?php
require_once __DIR__ . '/../config/config.php';

$user = require_role('citizen');

$id = (int)($_GET['id'] ?? 0);
if ($id === 0) {
    flash('danger', 'Invalid report ID.');
    redirect('citizen/dashboard.php');
}

// Fetch incident data - same query as report_view.php, but we need to check ownership
$stmt = db()->prepare(
    'SELECT i.*, c.name AS category_name, c.icon, d.name AS department_name
     FROM incidents i
     JOIN categories c ON c.id = i.category_id
     JOIN departments d ON d.id = c.department_id
     WHERE i.id = ? AND i.user_id = ?'
);
$stmt->execute([$id, $user['id']]);
$incident = $stmt->fetch();
if (!$incident) {
    flash('danger', 'Report not found or you do not have permission to edit it.');
    redirect('citizen/dashboard.php');
}

// Status editing permissions - only allow editing when status is 'pending'
$editableStatuses = ['pending'];
if (!in_array($incident['status'] ?? '', $editableStatuses)) {
    $statusLabel = status_label($incident['status']);
    flash('danger', sprintf(
        'This report cannot be edited because its status is %s. Only reports with Pending status can be edited.',
        $statusLabel
    ));
    redirect('citizen/report_view.php?id=' . $id);
}

// Fetch associated media (photos)
$mediaStmt = db()->prepare('SELECT * FROM incident_media WHERE incident_id = ?');
$mediaStmt->execute([$id]);
$media = $mediaStmt->fetchAll();

// Fetch category options for the form
$categories = db()->query('SELECT * FROM categories ORDER BY name')->fetchAll();
?>

<?php $page_title = 'Edit Report - ' . e($incident['ref_no']); ?>
<?php require __DIR__ . '/../includes/header.php'; ?>

<div class="row g-4">
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-semibold">
        <h5 class="mb-0"><i class="bi bi-pencil-square me-2"></i> Edit Report</h5>
      </div>
      <form id="editReportForm" method="POST" action="<?= url('api/update_incident.php') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="_method" value="PUT">
        <input type="hidden" name="incident_id" value="<?= (int)$incident['id'] ?>">
        <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">

        <div class="card-body">
          <p class="text-muted small mb-3">Only reports with <strong>Pending</strong> status can be edited. Reports that are <strong>Resolved</strong>, <strong>Rejected</strong>, or <strong>Under Active Dispatch</strong> are locked.</p>

          <div class="row g-3">
            <!-- Title Field -->
            <div class="col-12">
              <label class="form-label fw-semibold">Title <span class="text-danger small">*</span></label>
              <input type="text" class="form-control" name="title" value="<?= e($incident['title']) ?>" maxlength="150" required>
              <small class="text-muted">Maximum 150 characters</small>
            </div>

            <!-- Description Field -->
            <div class="col-12">
              <label class="form-label fw-semibold">Description <span class="text-danger small">*</span></label>
              <textarea class="form-control" name="description" rows="4" required><?= e($incident['description']) ?></textarea>
              <small class="text-muted">Describe the emergency in detail</small>
            </div>

            <!-- Severity Field -->
            <div class="col-12">
              <label class="form-label fw-semibold">Severity</label>
              <div class="d-flex flex-wrap gap-3">
                <?php foreach (['low', 'medium', 'high', 'critical'] as $sev): ?>
                  <label class="form-check-label d-flex align-items-center gap-1">
                    <input type="radio" name="severity" class="form-check-input mt-0" value="<?= $sev ?>" <?= $incident['severity'] === $sev ? 'checked' : '' ?>>
                    <?= severity_badge($sev) ?> <?= ucfirst($sev) ?>
                  </label>
                <?php endforeach; ?>
              </div>
              <small class="text-muted">Select severity level of the incident</small>
            </div>

            <!-- Address Field (locked - auto-detected) -->
            <div class="col-12">
              <label class="form-label fw-semibold">Address <span class="text-muted">(Locked)</span></label>
              <input type="text" class="form-control bg-light" value="<?= e($incident['address'] ?? '') ?>" readonly>
              <small class="text-muted">Auto-detected GPS location. Not editable.</small>
            </div>

            <!-- GPS Coordinates (locked) -->
            <div class="col-12">
              <label class="form-label fw-semibold">GPS Coordinates <span class="text-muted">(Locked)</span></label>
              <div class="alert alert-light border py-2 mb-1">
                <i class="bi bi-shield-lock me-2"></i>
                Latitude: <?= e($incident['latitude']) ?>, Longitude: <?= e($incident['longitude']) ?>
              </div>
              <small class="text-muted">Auto-detected at time of report. Not editable.</small>
            </div>

            <!-- Reference Number (locked) -->
            <div class="col-12">
              <label class="form-label fw-semibold">Reference Number <span class="text-muted">(Locked)</span></label>
              <input type="text" class="form-control bg-light" value="<?= e($incident['ref_no']) ?>" readonly>
              <small class="text-muted">Unique report reference. Not editable.</small>
            </div>
          </div>

          <hr class="my-4">

          <!-- Form Actions -->
          <div class="d-flex gap-2">
            <a href="<?= url('citizen/report_view.php?id=' . $incident['id']) ?>" class="btn btn-secondary">
              <i class="bi bi-x me-1"></i> Cancel
            </a>
            <button type="submit" class="btn btn-danger">
              <i class="bi bi-save me-1"></i> Save Changes
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-info-circle"></i> Report Details</div>
      <div class="card-body small">
        <p><strong>Reference:</strong> <?= e($incident['ref_no']) ?></p>
        <p><strong>Reported:</strong> <?= e(time_ago($incident['created_at'])) ?></p>
        <p><strong>Status:</strong> <?= status_badge($incident['status']) ?></p>
        <p><strong>Category:</strong> <?= e($incident['category_name']) ?></p>
        <?php if (count($media) > 0): ?>
          <p><strong>Photos:</strong> <?= count($media) ?> uploaded</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
document.getElementById('editReportForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const btn = this.querySelector('button[type="submit"]');
    const originalText = btn.innerHTML;

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-grow spinner-grow-sm me-2" role="status" aria-hidden="true"></span>Saving...';

    try {
        const form = new FormData(this);

        const response = await fetch('<?= url('api/update_incident.php') ?>', {
            method: 'PUT',
            body: form
        });

        const result = await response.json();

        if (result.success) {
            const toastContainer = document.createElement('div');
            toastContainer.className = 'toast align-items-center text-bg-success border-0 position-fixed top-0 end-0 m-3';
            toastContainer.setAttribute('role', 'alert');
            toastContainer.setAttribute('aria-live', 'assertive');
            toastContainer.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="bi bi-check-circle me-2"></i> Report updated successfully!
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            `;
            document.body.appendChild(toastContainer);
            const toast = new bootstrap.Toast(toastContainer);
            toast.show();

            setTimeout(() => {
                window.location.href = '<?= url('citizen/report_view.php?id=' . $incident['id']) ?>';
            }, 1200);
        } else {
            alert('Error: ' + (result.message ?? 'Failed to update report'));
        }
    } catch (err) {
        console.error('Update error:', err);
        alert('Failed to connect to server. Please try again.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>