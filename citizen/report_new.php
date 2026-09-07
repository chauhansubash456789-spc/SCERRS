<?php
require_once __DIR__ . '/../config/config.php';

$user = require_role('citizen');
$errors = [];

$categories = db()->query(
    'SELECT c.*, d.name AS department_name FROM categories c JOIN departments d ON d.id = c.department_id WHERE c.active = 1 ORDER BY c.name'
)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $category_id = (int)($_POST['category_id'] ?? 0);
    $title       = trim((string)($_POST['title'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $latitude    = $_POST['latitude'] ?? '';
    $longitude   = $_POST['longitude'] ?? '';
    $address     = trim((string)($_POST['address'] ?? ''));
    $severity    = (string)($_POST['severity'] ?? 'medium');

    // Validation
    if (!$category_id || !in_array($category_id, array_column($categories, 'id'))) $errors[] = 'Please choose an incident type.';
    if ($title === '' || mb_strlen($title) > 150)                                  $errors[] = 'A short title (max 150 chars) is required.';
    if ($description === '')                                                       $errors[] = 'Please describe what is happening.';
    if (!is_numeric($latitude) || !is_numeric($longitude))                         $errors[] = 'GPS location is required. Allow location access or tap the map.';
    if (!in_array($severity, ['low', 'medium', 'high', 'critical'], true))         $errors[] = 'Invalid severity selected.';

    // Photo uploads
    $files    = $_FILES['photos'] ?? null;
    $saved    = [];
    $allowed  = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];

    if ($files && !empty($files['name'][0])) {
        $count = count($files['name']);
        if ($count > 4) {
            $errors[] = 'You can attach up to 4 photos.';
        }
        for ($k = 0; $k < min($count, 4); $k++) {
            if ($files['error'][$k] === UPLOAD_ERR_NO_FILE) continue;
            if ($files['error'][$k] !== UPLOAD_ERR_OK) { $errors[] = 'Photo #' . ($k + 1) . ' failed to upload.'; continue; }
            if ($files['size'][$k] > UPLOAD_MAX_BYTES) { $errors[] = 'Photo #' . ($k + 1) . ' exceeds 5 MB.'; continue; }
            $ext = strtolower(pathinfo($files['name'][$k], PATHINFO_EXTENSION));
            if (!isset($allowed[$ext]) || mime_content_type($files['tmp_name'][$k]) !== $allowed[$ext]) {
                $errors[] = 'Photo #' . ($k + 1) . ' must be a JPG, PNG or WEBP image.';
                continue;
            }
            $saved[] = ['tmp' => $files['tmp_name'][$k], 'ext' => $ext];
        }
    }

    if (!$errors) {
        $pdo = db();
        try {
            $pdo->beginTransaction();

            $refNo = next_ref_no($pdo);
            $stmt = $pdo->prepare(
                'INSERT INTO incidents (ref_no, user_id, category_id, title, description, latitude, longitude, address, severity)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $refNo, $user['id'], $category_id, $title, $description,
                number_format((float)$latitude, 7, '.', ''),
                number_format((float)$longitude, 7, '.', ''),
                $address !== '' ? $address : null,
                $severity,
            ]);
            $incidentId = (int)$pdo->lastInsertId();

            // Initial audit entry
            $pdo->prepare('INSERT INTO status_histories (incident_id, status, changed_by, note) VALUES (?, "pending", ?, "Incident reported by citizen.")')
                ->execute([$incidentId, $user['id']]);

            // Save photos
            if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0775, true);
            $insMedia = $pdo->prepare('INSERT INTO incident_media (incident_id, file_path) VALUES (?, ?)');
            foreach ($saved as $s) {
                $filename = 'inc_' . $incidentId . '_' . bin2hex(random_bytes(8)) . '.' . $s['ext'];
                if (move_uploaded_file($s['tmp'], UPLOAD_DIR . DIRECTORY_SEPARATOR . $filename)) {
                    $insMedia->execute([$incidentId, $filename]);
                }
            }

            // Notify the responsible department
            $cat = $categories[array_search($category_id, array_column($categories, 'id'))];
            notify_department((int)$cat['department_id'], $incidentId, 'New ' . $cat['name'] . ' report ' . $refNo . ': ' . $title);

            $pdo->commit();
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $ex;
        }

        flash('success', '<strong>Report submitted!</strong> Your reference number is <strong>' . e($refNo) . '</strong>. The responsible agency has been notified.');
        redirect('citizen/report_view.php?id=' . $incidentId);
    }
}

$page_title = 'Report Emergency';
$load_map   = true;
require __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-lg-9 col-xl-8">
    <div class="card border-0 shadow-sm">
      <div class="card-body p-4">
        <h3 class="fw-bold text-danger"><i class="bi bi-exclamation-triangle-fill"></i> Report an Emergency</h3>
        <p class="text-muted small">All fields marked * are required. Your GPS position helps responders reach you faster.</p>

        <?php foreach ($errors as $err): ?>
          <div class="alert alert-danger py-2"><?= e($err) ?></div>
        <?php endforeach; ?>

        <form method="post" enctype="multipart/form-data" id="report-form" novalidate>
          <?= csrf_field() ?>

          <div class="mb-3">
            <label class="form-label fw-semibold">Incident type *</label>
            <div class="row g-2" id="category-picker">
              <?php foreach ($categories as $c): ?>
                <div class="col-6 col-md-4">
                  <input class="btn-check" type="radio" name="category_id" value="<?= (int)$c['id'] ?>" id="cat<?= (int)$c['id'] ?>"
                    <?= isset($_POST['category_id']) && (int)$_POST['category_id'] === (int)$c['id'] ? 'checked' : '' ?> required>
                  <label class="btn btn-outline-danger w-100 h-100 py-2" for="cat<?= (int)$c['id'] ?>">
                    <div style="font-size:1.4rem"><?= e($c['icon']) ?></div>
                    <small><?= e($c['name']) ?></small>
                    <div class="text-muted" style="font-size:.7rem">→ <?= e($c['department_name']) ?></div>
                  </label>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">Title *</label>
            <input type="text" name="title" class="form-control" maxlength="150"
                   value="<?= e($_POST['title'] ?? '') ?>" placeholder="e.g., Fire in two-storey building" required>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">What is happening? *</label>
            <textarea name="description" rows="4" class="form-control" required
              placeholder="Describe the situation, number of people affected, landmarks..."><?= e($_POST['description'] ?? '') ?></textarea>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label fw-semibold d-block">Severity *</label>
              <?php foreach (['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'critical' => 'Critical'] as $val => $lbl): ?>
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="radio" name="severity" value="<?= $val ?>" id="sev_<?= $val ?>"
                    <?= ($_POST['severity'] ?? 'medium') === $val ? 'checked' : '' ?>>
                  <label class="form-check-label" for="sev_<?= $val ?>"><?= $lbl ?></label>
                </div>
              <?php endforeach; ?>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label fw-semibold">Photos (up to 4, max 5 MB each)</label>
              <input type="file" name="photos[]" class="form-control" accept="image/jpeg,image/png,image/webp" multiple>
            </div>
          </div>

          <div class="border rounded-3 p-3 mb-3 bg-light">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span class="fw-semibold"><i class="bi bi-geo-alt-fill text-danger"></i> Location *</span>
              <button type="button" class="btn btn-sm btn-outline-primary" id="locate-btn">
                <i class="bi bi-crosshair"></i> Detect my location
              </button>
            </div>
            <div id="map" class="rounded-3"></div>
            <div class="row g-2 mt-2">
              <div class="col-6 col-md-3">
                <input type="text" name="latitude" id="lat" class="form-control form-control-sm" placeholder="Latitude" readonly value="<?= e($_POST['latitude'] ?? '') ?>" required>
              </div>
              <div class="col-6 col-md-3">
                <input type="text" name="longitude" id="lng" class="form-control form-control-sm" placeholder="Longitude" readonly value="<?= e($_POST['longitude'] ?? '') ?>" required>
              </div>
              <div class="col-md-6">
                <input type="text" name="address" class="form-control form-control-sm" placeholder="Landmark / address (optional)" value="<?= e($_POST['address'] ?? '') ?>">
              </div>
            </div>
            <div id="gps-status" class="small text-muted mt-1">Tap the map to place the pin manually if GPS is unavailable.</div>
          </div>

          <button type="submit" class="btn btn-danger btn-lg w-100 fw-semibold">
            <i class="bi bi-send-fill"></i> Submit Emergency Report
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const KTM = [27.7172, 85.3240];
const map = L.map('map').setView(KTM, 12);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 19, attribution: '&copy; OpenStreetMap'}).addTo(map);

// Recalculate container size once the page layout has settled so tiles
// render correctly even if the container was hidden/resized on load.
setTimeout(() => { map.invalidateSize(); }, 200);

let marker = null;
function setPoint(lat, lng, label) {
  lat = (+lat).toFixed(7); lng = (+lng).toFixed(7);
  document.getElementById('lat').value = lat;
  document.getElementById('lng').value = lng;
  if (marker) marker.setLatLng([lat, lng]);
  else marker = L.marker([lat, lng], {draggable: true}).addTo(map);
  marker.on('dragend', () => {
    const p = marker.getLatLng();
    document.getElementById('lat').value = p.lat.toFixed(7);
    document.getElementById('lng').value = p.lng.toFixed(7);
  });
  map.setView([lat, lng], 16);
  // Fix broken/misaligned tiles after panning to a new location
  setTimeout(() => { map.invalidateSize(); }, 200);
  const el = document.getElementById('gps-status');
  el.textContent = label || ('Pinned at ' + lat + ', ' + lng);
  el.className = 'small text-success mt-1';
}

map.on('click', ev => setPoint(ev.latlng.lat, ev.latlng.lng));

document.getElementById('locate-btn').addEventListener('click', function () {
  if (!navigator.geolocation) { alert('Geolocation is not supported by this browser.'); return; }
  this.disabled = true; this.innerHTML = 'Locating…';
  navigator.geolocation.getCurrentPosition(
    pos => {
      setPoint(pos.coords.latitude, pos.coords.longitude,
        'GPS locked with ±' + Math.round(pos.coords.accuracy) + ' m accuracy.');
      this.disabled = false; this.innerHTML = '<i class="bi bi-crosshair"></i> Re-detect location';
    },
    err => {
      alert('Could not get your GPS location. Please tap the map to pin it manually.');
      this.disabled = false; this.innerHTML = '<i class="bi bi-crosshair"></i> Detect my location';
    },
    {enableHighAccuracy: true, timeout: 10000}
  );
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
