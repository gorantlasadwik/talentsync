<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$senderId = currentUserId();
$receiverId = (int) ($_POST['receiver_id'] ?? 0);
$message = trim($_POST['message'] ?? '');

header('Content-Type: application/json; charset=UTF-8');

function jsonError(string $msg, int $code = 422): void
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $pdo->exec('ALTER TABLE messages ADD COLUMN is_read TINYINT(1) NOT NULL DEFAULT 0');
} catch (Throwable $e) {
    // Ignore if column already exists.
}

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

$attachmentPath = null;
$attachmentName = null;
$attachmentMime = null;
$attachmentSize = null;

if (isset($_FILES['attachment']) && is_array($_FILES['attachment'])) {
    $upload = $_FILES['attachment'];

    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if (($upload['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            jsonError('Upload failed. Try again.');
        }

        $tmpPath = (string) ($upload['tmp_name'] ?? '');
        $origName = trim((string) ($upload['name'] ?? 'file'));
        $size = (int) ($upload['size'] ?? 0);

        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            jsonError('Invalid uploaded file.');
        }

        if ($size <= 0) {
            jsonError('Uploaded file is empty.');
        }

        if ($size > (20 * 1024 * 1024)) {
            jsonError('File is too large. Max 20MB.');
        }

        $ext = strtolower((string) pathinfo($origName, PATHINFO_EXTENSION));
        $blockedExtensions = ['php', 'phtml', 'phar', 'exe', 'bat', 'cmd', 'com', 'dll', 'msi', 'sh', 'ps1'];
        if ($ext !== '' && in_array($ext, $blockedExtensions, true)) {
            jsonError('This file type is not allowed.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detectedMime = (string) ($finfo->file($tmpPath) ?: 'application/octet-stream');

        $uploadDirAbs = __DIR__ . '/../uploads/chat';
        if (!is_dir($uploadDirAbs) && !mkdir($uploadDirAbs, 0775, true) && !is_dir($uploadDirAbs)) {
            jsonError('Failed to prepare upload directory.', 500);
        }

        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $origName) ?: 'file';
        $unique = date('YmdHis') . '_' . bin2hex(random_bytes(6));
        $storedName = $unique . '_' . $safeName;
        $targetAbs = $uploadDirAbs . '/' . $storedName;

        if (!move_uploaded_file($tmpPath, $targetAbs)) {
            jsonError('Could not store uploaded file.', 500);
        }

        $attachmentPath = 'uploads/chat/' . $storedName;
        $attachmentName = mb_substr($origName, 0, 250);
        $attachmentMime = mb_substr($detectedMime, 0, 115);
        $attachmentSize = $size;
    }
}

if ($receiverId <= 0) {
    jsonError('Invalid receiver.');
}

if ($message === '' && $attachmentPath === null) {
    jsonError('Type a message or attach a file.');
}

$userMetaStmt = $pdo->prepare('SELECT id, name, email FROM users WHERE id IN (?, ?)');
$userMetaStmt->execute([$senderId, $receiverId]);
$userRows = $userMetaStmt->fetchAll();
$senderMeta = null;
$receiverMeta = null;
foreach ($userRows as $row) {
    if ((int) $row['id'] === $senderId) {
        $senderMeta = $row;
    }
    if ((int) $row['id'] === $receiverId) {
        $receiverMeta = $row;
    }
}

if (!$receiverMeta) {
    jsonError('Receiver does not exist.');
}

$receiverName = strtolower(trim((string) ($receiverMeta['name'] ?? '')));
$receiverEmail = strtolower(trim((string) ($receiverMeta['email'] ?? '')));
$isMockReceiver = str_starts_with($receiverName, 'mock ') || str_contains($receiverEmail, 'mock');

$firstChatStmt = $pdo->prepare(
    'SELECT COUNT(*)
     FROM messages
     WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)'
);
$firstChatStmt->execute([$senderId, $receiverId, $receiverId, $senderId]);
$isFirstConversationMessage = ((int) $firstChatStmt->fetchColumn()) === 0;

$stmt = $pdo->prepare(
    'INSERT INTO messages (
        sender_id, receiver_id, message, is_read,
        attachment_path, attachment_name, attachment_mime, attachment_size
    ) VALUES (?, ?, ?, 0, ?, ?, ?, ?)'
);
$stmt->execute([
    $senderId,
    $receiverId,
    $message,
    $attachmentPath,
    $attachmentName,
    $attachmentMime,
    $attachmentSize,
]);

if ($isFirstConversationMessage && !$isMockReceiver) {
    try {
        $senderName = trim((string) ($senderMeta['name'] ?? 'Someone'));
        $receiverEmailAddress = trim((string) ($receiverMeta['email'] ?? ''));
        $receiverDisplayName = trim((string) ($receiverMeta['name'] ?? 'there'));
        $preview = $message !== ''
            ? mb_substr(preg_replace('/\s+/', ' ', $message) ?? '', 0, 140)
            : 'Attachment sent';

        $chatUrl = appUrl('chat.php?user_id=' . $senderId);
        $alertBody = "Hi {$receiverDisplayName},\n\n"
            . "{$senderName} is interested to chat with you on TalentSync PRO.\n\n"
            . "Message preview: {$preview}\n\n"
            . "Open chat: {$chatUrl}\n\n"
            . "Thanks,\nTalentSync PRO Team";

        $safeReceiverDisplayName = htmlspecialchars($receiverDisplayName, ENT_QUOTES, 'UTF-8');
        $safeSenderName = htmlspecialchars($senderName, ENT_QUOTES, 'UTF-8');
        $safePreview = htmlspecialchars($preview, ENT_QUOTES, 'UTF-8');
        $alertHtml = buildBrandedMailHtml(
            'New Chat Interest',
            $senderName . ' wants to connect with you on TalentSync PRO.',
            '<p style="margin:0 0 12px 0;">Hi ' . $safeReceiverDisplayName . ',</p>'
            . '<p style="margin:0 0 12px 0;"><strong>' . $safeSenderName . '</strong> is interested to chat with you.</p>'
            . '<p style="margin:0;"><strong>Message preview:</strong> ' . $safePreview . '</p>',
            'Open Chat',
            $chatUrl,
            'You are receiving this because this is the first message in this conversation.'
        );

        sendAppMail($receiverEmailAddress, 'New chat interest on TalentSync PRO', $alertBody, $alertHtml);
    } catch (Throwable $e) {
        // Email alert is best-effort only.
    }
}

// Optional mock chat bot behavior for accounts marked as mock via name/email.
try {
    if ($isMockReceiver) {
        $text = strtolower(trim($message));
        $reply = 'Thanks for reaching out. I am available. Share your project scope and timeline.';
        if (preg_match('/^(hi|hello|hey)\b/', $text)) {
            $reply = 'Hi! Great to connect. How can I help with your project?';
        } elseif (str_contains($text, 'budget')) {
            $reply = 'Sure, I can work with shared revenue or fixed milestones. Please share your budget range.';
        } elseif (str_contains($text, 'timeline') || str_contains($text, 'deadline')) {
            $reply = 'I can start immediately. Typical first milestone delivery is within 2-4 days.';
        }

        $autoReplyStmt = $pdo->prepare(
            'INSERT INTO messages (
                sender_id, receiver_id, message, is_read,
                attachment_path, attachment_name, attachment_mime, attachment_size
            ) VALUES (?, ?, ?, 0, NULL, NULL, NULL, NULL)'
        );
        $autoReplyStmt->execute([$receiverId, $senderId, $reply]);
    }
} catch (Throwable $e) {
    // Auto-reply is best-effort and should not break normal sending.
}

echo json_encode(['ok' => true], JSON_UNESCAPED_SLASHES);
