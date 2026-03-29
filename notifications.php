<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/seeker_topbar.php';

requireLogin();

$userId = currentUserId();

try {
    $pdo->exec('ALTER TABLE messages ADD COLUMN is_read TINYINT(1) NOT NULL DEFAULT 0');
} catch (Throwable $e) {
    // Ignore if the column already exists.
}

$stmt = $pdo->prepare('SELECT title, body, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50');
$stmt->execute([$userId]);
$notifications = $stmt->fetchAll();

$messageStmt = $pdo->prepare(
    'SELECT m.sender_id, u.name AS sender_name, m.message, m.created_at
     FROM messages m
     JOIN users u ON u.id = m.sender_id
     WHERE m.receiver_id = ? AND m.is_read = 0 AND u.role = ?
     ORDER BY m.created_at DESC
     LIMIT 50'
);
$messageStmt->execute([$userId, 'provider']);
$messageNotifications = $messageStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body class="bg-[#05070b] text-white min-h-screen">
    <?php if (($_SESSION['role'] ?? '') === 'seeker') { renderSeekerTopbar('messages'); } else { ?>
    <header class="bg-neutral-900 text-white flex justify-between items-center px-8 h-20 w-full z-50 fixed top-0">
        <div class="flex items-center gap-10"><a href="dashboard.php" class="text-2xl font-heading italic hover:text-white/90">TalentSync</a><span class="text-sm text-white/70">Notifications</span></div>
        <a href="dashboard.php" class="text-white/70 hover:text-white">Dashboard</a>
    </header>
    <?php } ?>
    <div class="flex pt-20 min-h-screen">
    <aside class="fixed left-0 top-20 bottom-0 w-72 bg-[#090b0f] border-r border-white/10 p-6 overflow-y-auto">
        <h2 class="text-xl font-heading italic">Inbox</h2>
        <nav class="mt-6 space-y-2 text-sm">
            <a class="block px-4 py-3 rounded-xl bg-white/10 text-white" href="notifications.php">All Notifications</a>
            <a class="block px-4 py-3 rounded-xl text-white/70 hover:bg-white/10" href="chat.php">Messages</a>
            <a class="block px-4 py-3 rounded-xl text-white/70 hover:bg-white/10" href="seeker_dashboard.php">Seeker Dashboard</a>
        </nav>
    </aside>
    <main class="ml-72 flex-1 p-8">
    <div class="page-container max-w-3xl mx-auto liquid-glass rounded-3xl p-6">
        <div class="flex items-center justify-between">
            <h1 class="text-4xl font-heading italic">Notifications</h1>
        </div>
        <div class="mt-4 space-y-3">
            <?php if (!$messageNotifications && !$notifications): ?>
                <p class="muted">No notifications yet.</p>
            <?php endif; ?>

            <?php foreach ($messageNotifications as $mn): ?>
                <article class="liquid-glass rounded-2xl p-4 border border-red-400/30 bg-red-500/5">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="font-medium">New message from <?php echo htmlspecialchars((string) $mn['sender_name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                        <span class="text-[11px] px-2 py-1 rounded-full bg-red-500/20 text-red-300">Unread</span>
                    </div>
                    <p class="muted mt-1"><?php echo htmlspecialchars((string) $mn['message'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <a class="inline-block mt-3 text-sm text-white underline" href="chat.php?user_id=<?php echo (int) $mn['sender_id']; ?>">Open conversation</a>
                </article>
            <?php endforeach; ?>

            <?php foreach ($notifications as $n): ?>
                <article class="liquid-glass rounded-2xl p-4 <?php echo $n['is_read'] ? '' : 'border border-white/20'; ?>">
                    <h3 class="font-medium"><?php echo htmlspecialchars($n['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                    <p class="muted mt-1"><?php echo htmlspecialchars((string) ($n['body'] ?: ''), ENT_QUOTES, 'UTF-8'); ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
    </main>
    </div>
</body>
</html>
