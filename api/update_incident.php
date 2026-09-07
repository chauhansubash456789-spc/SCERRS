<?php
// update_incident.php - API endpoint for updating citizen reports
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');

// 1. Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(400);
    $resp = new stdClass();
    $resp->success = false;
    $resp->message = 'Invalid request method.';
    echo json_encode($resp);
    exit;
}

// 2. Verify CSRF token
if (!isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    $resp = new stdClass();
    $resp->success = false;
    $resp->message = 'Invalid CSRF token.';
    echo json_encode($resp);
    exit;
}

// 3. Get incident ID and user ID from request
$incidentId = (int)($_POST['incident_id'] ?? 0);
$userId = (int)($_POST['user_id'] ?? 0);

if ($incidentId <= 0 || $userId <= 0) {
    http_response_code(400);
    $resp = new stdClass();
    $resp->success = false;
    $resp->message = 'Missing required parameters.';
    echo json_encode($resp);
    exit;
}

// 3. Fetch incident and verify ownership
$pdo = db();
$stmt = $pdo->prepare('SELECT * FROM incidents WHERE id = ? AND user_id = ?');
$stmt->execute([$incidentId, $userId]);
$incident = $stmt->fetch();

if (!$incident) {
    http_response_code(403);
    $resp = new stdClass();
    $resp->success = false;
    $resp->message = 'Incident not found or you do not have permission to edit it.';
    echo json_encode($resp);
    exit;
}

// 4. Check status allows editing (only 'pending' status)
$allowedStatuses = ['pending'];
if (!in_array($incident['status'], $allowedStatuses)) {
    http_response_code(403);
    $resp = new stdClass();
    $resp->success = false;
    $resp->message = 'This report cannot be edited because its status is ' . status_label($incident['status']) . '. Only reports with Pending status can be edited.';
    echo json_encode($resp);
    exit;
}

// 5. Prepare update data from form inputs
$updateData = [];
$errors = [];

// Title
if (isset($_POST['title']) && trim($_POST['title']) !== '') {
    $title = trim($_POST['title']);
    if (strlen($title) > 150) {
        $errors['title'] = 'Title must be at most 150 characters.';
    } else {
        $updateData['title'] = $title;
    }
}

// Description
if (isset($_POST['description']) && trim($_POST['description']) !== '') {
    $desc = trim($_POST['description']);
    if (strlen($desc) > 10000) {
        $errors['description'] = 'Description is too long (max 10,000 characters).';
    } else {
        $updateData['description'] = $desc;
    }
}

// Severity
if (isset($_POST['severity']) && in_array($_POST['severity'], ['low', 'medium', 'high', 'critical'], true)) {
    $updateData['severity'] = $_POST['severity'];
}

// 6. If no valid fields to update, return error
if (empty($updateData) && empty($errors)) {
    $resp = new stdClass();
    $resp->success = false;
    $resp->message = 'No valid fields provided for update.';
    echo json_encode($resp);
    exit;
}

// 7. Update the incident
try {
    // Build dynamic UPDATE query
    $setClauses = [];
    $params = [];
    $paramTypes = '';

    if (isset($updateData['title'])) {
        $setClauses[] = 'title = ?';
        $params[] = $updateData['title'];
        $paramTypes .= 's';
    }
    if (isset($updateData['description'])) {
        $setClauses[] = 'description = ?';
        $params[] = $updateData['description'];
        $paramTypes .= 's';
    }
    if (isset($updateData['severity'])) {
        $setClauses[] = 'severity = ?';
        $params[] = $updateData['severity'];
        $paramTypes .= 's';
    }

    // Always update updated_at timestamp
    $setClauses[] = 'updated_at = CURRENT_TIMESTAMP';

    $setClause = implode(', ', $setClauses);
    $sql = 'UPDATE incidents SET ' . $setClause . ' WHERE id = ? AND user_id = ?';
    $params[] = $incidentId;
    $params[] = $userId;
    $paramTypes .= 'ii';

    $updateStmt = $pdo->prepare($sql);
    $updateStmt->execute($params);

    // 8. Handle new photo uploads (optional)
    $newMediaCount = 0;
    if (isset($_FILES['new_photos']) && is_array($_FILES['new_photos']['name']) && count($_FILES['new_photos']['name']) > 0) {
        $uploadDir = __DIR__ . '/../../uploads/incidents/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $maxTotalPhotos = 4;
        $currentMediaCount = count($incident['media'] ?? []);
        $remainingSlots = $maxTotalPhotos - $currentMediaCount;

        if ($remainingSlots > 0) {
            $uploaded = 0;
            foreach ($_FILES['new_photos']['name'] as $key => $name) {
                if ($uploaded >= $remainingSlots) break;
                if ($_FILES['new_photos']['error'][$key] !== UPLOAD_ERR_OK) continue;
                if (!is_uploaded_file($_FILES['new_photos']['tmp_name'][$key])) continue;

                $fileName = basename($name);
                $filePath = $uploadDir . $fileName;
                $fileType = mime_content_type($_FILES['new_photos']['tmp_name'][$key] ?? '');

                // Validate: image type and max 5MB
                if (strpos($fileType, 'image/') === 0 && $_FILES['new_photos']['size'][$key] < 5 * 1024 * 1024) {
                    if (move_uploaded_file($_FILES['new_photos']['tmp_name'][$key], $filePath)) {
                        $mediaStmt = $pdo->prepare('INSERT INTO incident_media (incident_id, file_path, created_at) VALUES (?, ?, CURRENT_TIMESTAMP)');
                        $mediaStmt->execute([$incidentId, $filePath]);
                        $uploaded++;
                    }
                }
            }
            $newMediaCount = $uploaded;
        }
    }

    // 9. Return success response
    $resp = new stdClass();
    $resp->success = true;
    $resp->message = 'Report updated successfully.';
    $resp->updatedFields = array_keys($updateData);
    $resp->newPhotosUploaded = $newMediaCount;

    // Include validation errors if any
    if (!empty($errors)) {
        $resp->validationErrors = $errors;
    }

    http_response_code(200);
    echo json_encode($resp, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    $resp = new stdClass();
    $resp->success = false;
    $resp->message = 'Database error: ' . $e->getMessage();
    echo json_encode($resp);
}