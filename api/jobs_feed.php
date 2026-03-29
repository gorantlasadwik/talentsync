<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

$skill = trim($_GET['skill'] ?? '');
$source = trim($_GET['source'] ?? '');
$location = trim($_GET['location'] ?? '');

$sql = 'SELECT id, title, source, location, budget FROM jobs WHERE 1=1';
$params = [];

if ($skill !== '') {
    $sql .= ' AND title LIKE ?';
    $params[] = '%' . $skill . '%';
}
if ($source !== '') {
    $sql .= ' AND source = ?';
    $params[] = $source;
}
if ($location !== '') {
    $sql .= ' AND location LIKE ?';
    $params[] = '%' . $location . '%';
}

$sql .= ' ORDER BY created_at DESC LIMIT 50';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

echo json_encode(['jobs' => $stmt->fetchAll()]);
