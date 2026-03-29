<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

requireRole('admin');

$totals = [
    'users' => (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'jobs' => (int) $pdo->query('SELECT COUNT(*) FROM jobs')->fetchColumn(),
    'messages' => (int) $pdo->query('SELECT COUNT(*) FROM messages')->fetchColumn(),
    'reports_open' => (int) $pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'open'")->fetchColumn(),
];

$reports = $pdo->query('SELECT r.id, u1.name AS reporter, u2.name AS reported, r.reason, r.status, r.created_at FROM reports r JOIN users u1 ON u1.id = r.reporter_id JOIN users u2 ON u2.id = r.reported_user_id ORDER BY r.created_at DESC LIMIT 20')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        html,
        body {
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        html::-webkit-scrollbar,
        body::-webkit-scrollbar {
            width: 0;
            height: 0;
            background: transparent;
            display: none;
        }
    </style>
</head>
<body class="bg-[#05070b] text-white min-h-screen">
    <header class="bg-neutral-900 text-white flex justify-between items-center px-8 h-20 w-full z-50 fixed top-0">
        <div class="flex items-center gap-12"><a href="dashboard.php" class="text-2xl font-heading italic hover:text-white/90">TalentSync</a><span class="text-sm text-white/70">Admin</span></div>
        <a href="logout.php" class="text-neutral-400 hover:text-white">Logout</a>
    </header>

    <div class="flex pt-20 min-h-screen">
        <aside class="fixed left-0 top-20 bottom-0 w-72 bg-[#090b0f] border-r border-white/10 p-6 overflow-y-auto">
            <h2 class="text-xl font-heading italic">Admin Controls</h2>
            <nav class="mt-6 space-y-2 text-sm">
                <a class="block px-4 py-3 rounded-xl bg-white/10 text-white" href="admin_dashboard.php">Overview</a>
                <a class="block px-4 py-3 rounded-xl text-white/70 hover:bg-white/10" href="notifications.php">Notifications</a>
                <a class="block px-4 py-3 rounded-xl text-white/70 hover:bg-white/10" href="dashboard.php">App Router</a>
            </nav>
        </aside>

        <main class="ml-72 flex-1 p-8">
        <div class="max-w-6xl mx-auto space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-4xl font-heading italic">Admin Dashboard</h1>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="liquid-glass rounded-2xl p-4"><p class="text-white/60 text-xs">Users</p><p class="text-3xl font-heading italic"><?php echo $totals['users']; ?></p></div>
            <div class="liquid-glass rounded-2xl p-4"><p class="text-white/60 text-xs">Jobs</p><p class="text-3xl font-heading italic"><?php echo $totals['jobs']; ?></p></div>
            <div class="liquid-glass rounded-2xl p-4"><p class="text-white/60 text-xs">Messages</p><p class="text-3xl font-heading italic"><?php echo $totals['messages']; ?></p></div>
            <div class="liquid-glass rounded-2xl p-4"><p class="text-white/60 text-xs">Open Reports</p><p class="text-3xl font-heading italic"><?php echo $totals['reports_open']; ?></p></div>
        </div>

        <section class="liquid-glass rounded-3xl p-6">
            <h2 class="text-2xl font-heading italic mb-4">Recent Reports</h2>
            <div class="overflow-auto">
                <table class="data-table">
                    <thead><tr><th class="py-2">Reporter</th><th class="py-2">Reported</th><th class="py-2">Reason</th><th class="py-2">Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($reports as $r): ?>
                        <tr><td class="py-2"><?php echo htmlspecialchars($r['reporter'], ENT_QUOTES, 'UTF-8'); ?></td><td class="py-2"><?php echo htmlspecialchars($r['reported'], ENT_QUOTES, 'UTF-8'); ?></td><td class="py-2"><?php echo htmlspecialchars($r['reason'], ENT_QUOTES, 'UTF-8'); ?></td><td class="py-2"><?php echo htmlspecialchars($r['status'], ENT_QUOTES, 'UTF-8'); ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
    </main>
    </div>
</body>
</html>
