<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
$sql = 'SELECT u.id, u.name, f.skill, f.experience, f.rating FROM freelancers f JOIN users u ON u.id = f.user_id';
$params = [];

if ($q !== '') {
    $sql .= ' WHERE u.name LIKE ? OR f.skill LIKE ? OR f.experience LIKE ?';
    $like = '%' . $q . '%';
    $params = [$like, $like, $like];
}

$sql .= ' ORDER BY f.rating DESC, u.name ASC LIMIT 50';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

echo json_encode(['freelancers' => $stmt->fetchAll()]);
