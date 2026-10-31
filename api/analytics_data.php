<?php
require_once __DIR__ . '/../config/config.php';

$pdo = db();

$deptId = (int)($_GET['dept'] ?? 0);
$isAdmin = $_GET['admin'] ?? false;
$where = $isAdmin ? '' : 'WHERE c.department_id = ' . $deptId;

// incidents by type
$byCategory = $pdo->query(
    "SELECT c.name, c.icon, COUNT(i.id) cnt
     FROM incidents i JOIN categories c ON c.id=i.category_id $where
     GROUP BY c.id ORDER BY cnt DESC"
)->fetchAll(PDO::FETCH_ASSOC);

// by severity
$bySeverity = $pdo->query(
    "SELECT i.severity, COUNT(i.id) cnt
     FROM incidents i JOIN categories c ON c.id=i.category_id $where
     GROUP BY i.severity"
)->fetchAll(PDO::FETCH_ASSOC);

// current pipeline (status counts)
$totals = $pdo->query(
    "SELECT COUNT(*) total,
            SUM(i.status='pending') pending,
            SUM(i.status IN ('acknowledged','in_progress')) active,
            SUM(i.status='resolved') resolved,
            SUM(i.status='rejected') rejected
     FROM incidents i JOIN categories c ON c.id=i.category_id $where"
)->fetch(PDO::FETCH_ASSOC);

// trend: last 14 days
$trendRows = $pdo->query(
    "SELECT DATE(i.created_at) d, COUNT(i.id) cnt
     FROM incidents i JOIN categories c ON c.id=i.category_id $where" .
     ($where ? ' AND' : ' WHERE') . " i.created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
     GROUP BY DATE(i.created_at)"
)->fetchAll(PDO::FETCH_ASSOC);

// Fill missing days
$trend = [];
for ($k = 13; $k >= 0; $k--) {
    $day = date('Y-m-d', strtotime("-$k days"));
    $trend[$day] = 0;
}
foreach ($trendRows as $r) {
    if (isset($trend[$r['d']])) $trend[$r['d']] = (int)$r['cnt'];
}

// Build response
$response = [
    'incidentsByType' => array_map(function($d) {
        return [
            'name' => $d['name'],
            'icon' => $d['icon'],
            'count' => (int)$d['cnt']
        ];
    }, $byCategory),
    'bySeverity' => array_map(function($d) {
        return [
            'severity' => $d['severity'],
            'count' => (int)$d['cnt']
        ];
    }, $bySeverity),
    'currentPipeline' => [
        'pending' => (int)$totals['pending'],
        'active' => (int)$totals['active'],
        'resolved' => (int)$totals['resolved'],
        'rejected' => (int)$totals['rejected']
    ],
    'reportsLast14Days' => array_map(function($k, $v) use ($trend) {
        return [
            'day' => date('n/j', strtotime("-$k days")),
            'count' => $v
        ];
    }, range(0, 13), array_values($trend))
];

header('Content-Type: application/json');
echo json_encode($response, JSON_UNESCAPED_UNICODE);