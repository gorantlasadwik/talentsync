<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

function renderSeekerTopbar(string $activeTab = 'jobs'): void
{
    if (($_SESSION['role'] ?? '') !== 'seeker') {
        return;
    }

    global $pdo;

    $name = trim((string) ($_SESSION['name'] ?? 'User'));
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    $imagePath = null;
    $gender = null;
    $age = null;
    $hasUnreadClientMessages = false;

    try {
        $pdo->exec('ALTER TABLE messages ADD COLUMN is_read TINYINT(1) NOT NULL DEFAULT 0');
    } catch (Throwable $e) {
        // Ignore if the column already exists.
    }

    if ($userId > 0) {
        try {
            $stmt = $pdo->prepare('SELECT image_path, gender, age FROM freelancers WHERE user_id = ? LIMIT 1');
            $stmt->execute([$userId]);
            $row = $stmt->fetch();
            $imagePath = $row['image_path'] ?? null;
            $gender = isset($row['gender']) ? (string) $row['gender'] : null;
            $age = isset($row['age']) && $row['age'] !== null ? (int) $row['age'] : null;
        } catch (Throwable $e) {
            $imagePath = null;
            $gender = null;
            $age = null;
        }

        try {
            $unreadStmt = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM messages m
                 JOIN users u ON u.id = m.sender_id
                 WHERE m.receiver_id = ? AND m.is_read = 0 AND u.role = ?'
            );
            $unreadStmt->execute([$userId, 'provider']);
            $hasUnreadClientMessages = ((int) $unreadStmt->fetchColumn()) > 0;
        } catch (Throwable $e) {
            $hasUnreadClientMessages = false;
        }
    }

    $avatar = avatarUrl($name, is_string($imagePath) ? $imagePath : null, $gender, $age);

    $jobsClass = $activeTab === 'jobs' ? 'text-white font-semibold border-b-2 border-white pb-1' : 'text-neutral-400 hover:text-white';
    $messagesClass = $activeTab === 'messages' ? 'text-white font-semibold border-b-2 border-white pb-1' : 'text-neutral-400 hover:text-white';
    $hiringClass = $activeTab === 'hiring' ? 'text-white font-semibold border-b-2 border-white pb-1' : 'text-neutral-400 hover:text-white';

    echo '<header class="bg-neutral-900 text-white flex justify-between items-center px-8 h-20 w-full z-50 fixed top-0">';
    echo '  <div class="flex items-center gap-12">';
    echo '      <a href="dashboard.php" class="text-2xl font-heading italic hover:text-white/90">TalentSync</a>';
    echo '      <div class="hidden md:flex items-center gap-8 text-sm font-medium">';
    echo '          <a class="' . $jobsClass . '" href="seeker_dashboard.php">Find Jobs</a>';
    echo '          <a class="' . $messagesClass . '" href="chat.php">Messages</a>';
    echo '          <a class="' . $hiringClass . '" href="aggregator.php">Hiring</a>';
    echo '      </div>';
    echo '  </div>';
    echo '  <div class="flex-1 max-w-2xl px-8">';
    echo '      <div class="relative flex items-center">';
    echo '          <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">';
    echo '              <span class="material-symbols-outlined text-white/50" style="font-size:20px;">search</span>';
    echo '          </div>';
    echo '          <input class="w-full bg-white/10 border border-white/10 rounded-full py-2.5 pl-11 pr-4 text-sm placeholder-white/40 text-white" placeholder="Job title, keywords, or company" type="text" />';
    echo '      </div>';
    echo '  </div>';
    echo '  <div class="flex items-center gap-6">';
    echo '      <a href="notifications.php" class="relative hover:bg-neutral-800 rounded-lg transition-all p-2">';
    echo '          <span class="material-symbols-outlined text-neutral-400">notifications</span>';
    if ($hasUnreadClientMessages) {
        echo '      <span class="absolute top-1 right-1 h-2.5 w-2.5 rounded-full bg-red-500 ring-2 ring-neutral-900"></span>';
    }
    echo '      </a>';
    echo '      <a href="chat.php" class="hover:bg-neutral-800 rounded-lg transition-all p-2"><span class="material-symbols-outlined text-neutral-400">chat_bubble</span></a>';
    echo '      <a href="profile.php" title="Edit Profile" class="block">';
    echo '          <img src="' . htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8') . '" alt="Profile" class="h-12 w-12 rounded-full border-2 border-white/30 object-cover" />';
    echo '      </a>';
    echo '      <a href="logout.php" class="text-neutral-400 hover:text-white" title="Logout"><span class="material-symbols-outlined">logout</span></a>';
    echo '  </div>';
    echo '</header>';
}
