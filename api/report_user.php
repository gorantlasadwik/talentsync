<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

requireLogin();

$reportedUserId = (int) ($_POST['reported_user_id'] ?? 0);
$reason = trim($_POST['reason'] ?? '');

if ($reportedUserId <= 0 || $reason === '') {
    http_response_code(422);
    echo 'Invalid payload';
    exit;
}

$stmt = $pdo->prepare('INSERT INTO reports (reporter_id, reported_user_id, reason) VALUES (?, ?, ?)');
$stmt->execute([currentUserId(), $reportedUserId, $reason]);

echo 'Report submitted';
