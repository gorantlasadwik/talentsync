<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$selfId = currentUserId();
$receiverId = (int) ($_GET['receiver_id'] ?? 0);

try {
    $pdo->exec('ALTER TABLE messages ADD COLUMN is_read TINYINT(1) NOT NULL DEFAULT 0');
} catch (Throwable $e) {
    // Ignore if column already exists.
}

if ($receiverId <= 0) {
    exit;
}

$markReadStmt = $pdo->prepare('UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0');
$markReadStmt->execute([$receiverId, $selfId]);

try {
    $pdo->exec('ALTER TABLE messages ADD COLUMN attachment_path VARCHAR(255) DEFAULT NULL');
} catch (Throwable $e) {
    // Ignore if column already exists.
}

try {
    $pdo->exec('ALTER TABLE messages ADD COLUMN attachment_name VARCHAR(255) DEFAULT NULL');
} catch (Throwable $e) {
    // Ignore if column already exists.
}

try {
    $pdo->exec('ALTER TABLE messages ADD COLUMN attachment_mime VARCHAR(120) DEFAULT NULL');
} catch (Throwable $e) {
    // Ignore if column already exists.
}

try {
    $pdo->exec('ALTER TABLE messages ADD COLUMN attachment_size INT DEFAULT NULL');
} catch (Throwable $e) {
    // Ignore if column already exists.
}

$stmt = $pdo->prepare(
    'SELECT sender_id, receiver_id, message, created_at,
            attachment_path, attachment_name, attachment_mime, attachment_size
     FROM messages
     WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
     ORDER BY created_at ASC
     LIMIT 200'
);
$stmt->execute([$selfId, $receiverId, $receiverId, $selfId]);
$messages = $stmt->fetchAll();

foreach ($messages as $m) {
    $mine = (int) $m['sender_id'] === $selfId;
    $messageText = trim((string) ($m['message'] ?? ''));
    $attachmentPath = trim((string) ($m['attachment_path'] ?? ''));
    $attachmentName = trim((string) ($m['attachment_name'] ?? ''));
    $attachmentMime = strtolower(trim((string) ($m['attachment_mime'] ?? '')));
    $attachmentSize = (int) ($m['attachment_size'] ?? 0);

    $hasAttachment = $attachmentPath !== '';

    $sizeLabel = '';
    if ($attachmentSize > 0) {
        $sizeLabel = $attachmentSize >= 1024 * 1024
            ? number_format($attachmentSize / (1024 * 1024), 2) . ' MB'
            : number_format($attachmentSize / 1024, 1) . ' KB';
    }

    $isImage = str_starts_with($attachmentMime, 'image/');
    $isVideo = str_starts_with($attachmentMime, 'video/');
    $isAudio = str_starts_with($attachmentMime, 'audio/');

    echo '<div class="' . ($mine ? 'text-right' : 'text-left') . '">';
    echo '<div class="msg-bubble inline-block px-3 py-2 rounded-2xl ' . ($mine ? 'bg-white text-black' : 'bg-white/10 text-white') . '">';

    if ($messageText !== '') {
        echo '<p class="whitespace-pre-line break-words">' . e($messageText) . '</p>';
    }

    if ($hasAttachment) {
        $safePath = e($attachmentPath);
        $safeName = e($attachmentName !== '' ? $attachmentName : 'attachment');
        echo '<div class="mt-2">';
        if ($isImage) {
            echo '<a href="' . $safePath . '" target="_blank" rel="noopener" class="block">';
            echo '<img src="' . $safePath . '" alt="attachment" class="max-w-[220px] max-h-[220px] rounded-xl border border-white/15 object-cover" loading="lazy">';
            echo '</a>';
        } elseif ($isVideo) {
            echo '<video controls class="max-w-[240px] max-h-[220px] rounded-xl border border-white/15">';
            echo '<source src="' . $safePath . '" type="' . e($attachmentMime) . '">';
            echo '</video>';
        } elseif ($isAudio) {
            echo '<audio controls class="max-w-[240px]">';
            echo '<source src="' . $safePath . '" type="' . e($attachmentMime) . '">';
            echo '</audio>';
        }

        echo '<a href="' . $safePath . '" target="_blank" rel="noopener" class="inline-flex items-center gap-2 mt-2 text-xs ' . ($mine ? 'text-black/70' : 'text-white/80') . '">';
        echo '<span class="material-symbols-outlined" style="font-size:16px;">attach_file</span>';
        echo '<span class="max-w-[180px] truncate">' . $safeName . '</span>';
        if ($sizeLabel !== '') {
            echo '<span>(' . e($sizeLabel) . ')</span>';
        }
        echo '</a>';
        echo '</div>';
    }

    echo '</div>';
    echo '</div>';
}
