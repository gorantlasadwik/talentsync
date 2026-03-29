<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

requireRole('seeker');

$freelancerId = (int) ($_POST['freelancer_id'] ?? 0);
if ($freelancerId <= 0) {
    http_response_code(422);
    echo 'Invalid freelancer id';
    exit;
}

$stmt = $pdo->prepare('INSERT IGNORE INTO bookmarks (seeker_id, freelancer_id) VALUES (?, ?)');
$stmt->execute([currentUserId(), $freelancerId]);

echo 'Bookmarked';
