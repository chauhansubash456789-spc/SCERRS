<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

$user = current_user();
if (!$user || !in_array($user['role'], ['authority', 'admin'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$sql = 'SELECT i.id, i.ref_no, i.title, i.latitude, i.longitude, i.severity, i.status, i.created_at,
               c.name AS category_name, c.icon
        FROM incidents i JOIN categories c ON c.id = i.category_id
        WHERE 1 = 1';
$params = [];

// Authority users only see incidents their department handles; admins see all.
if ($user['role'] === 'authority') {
    $sql .= ' AND c.department_id = ?';
    $params[] = (int)$user['department_id'];
}

if (!empty($_GET['status']) && in_array($_GET['status'], STATUSES, true)) {
    $sql .= ' AND i.status = ?';
    $params[] = $_GET['status'];
}

$stmt = db()->prepare($sql);
$stmt->execute($params);
$incidents = $stmt->fetchAll();

echo json_encode(array_map(function ($i) {
    return [
        'id'       => (int)$i['id'],
        'ref_no'   => $i['ref_no'],
        'title'    => $i['title'],
        'category' => $i['category_name'],
        'icon'     => $i['icon'],
        'lat'      => (float)$i['latitude'],
        'lng'      => (float)$i['longitude'],
        'severity' => $i['severity'],
        'status'   => $i['status'],
        'time'     => time_ago($i['created_at']),
        'url'      => url('authority/incident_view.php?id=' . $i['id']),
    ];
}, $incidents), JSON_UNESCAPED_UNICODE);
