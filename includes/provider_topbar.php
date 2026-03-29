<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/auth.php';

function renderProviderTopbar(string $activeTab = 'hub', bool $showSearch = false, string $searchPlaceholder = 'Search freelancers, skills, city'): void
{
    if (($_SESSION['role'] ?? '') !== 'provider') {
        return;
    }

    $providerId = currentUserId();

    $hubClass = $activeTab === 'hub' ? 'text-white font-semibold border-b-2 border-white pb-1' : 'text-neutral-400 hover:text-white';
    $messagesClass = $activeTab === 'messages' ? 'text-white font-semibold border-b-2 border-white pb-1' : 'text-neutral-400 hover:text-white';
    $pipelineClass = $activeTab === 'pipeline' ? 'text-white font-semibold border-b-2 border-white pb-1' : 'text-neutral-400 hover:text-white';
    $hiringClass = $activeTab === 'hiring' ? 'text-white font-semibold border-b-2 border-white pb-1' : 'text-neutral-400 hover:text-white';

    echo '<header class="bg-neutral-900 text-white flex justify-between items-center px-8 h-20 w-full z-50 fixed top-0 border-b border-white/10">';
    echo '  <div class="flex items-center gap-12">';
    echo '      <a href="provider_dashboard.php" class="text-2xl font-heading italic hover:text-white/90">TalentSync</a>';
    echo '      <div class="hidden md:flex items-center gap-8 text-sm font-medium">';
    echo '          <a class="' . $hubClass . '" href="provider_dashboard.php">Provider Hub</a>';
    echo '          <a class="' . $messagesClass . '" href="chat.php">Messages</a>';
    echo '          <a class="' . $pipelineClass . '" href="company_pipeline.php?provider_id=' . (int) $providerId . '">Pipeline</a>';
    echo '          <a class="' . $hiringClass . '" href="hiring_board.php">Hiring Board</a>';
    echo '      </div>';
    echo '  </div>';

    if ($showSearch) {
        echo '  <div class="flex-1 max-w-xl px-8 hidden lg:block">';
        echo '      <div class="relative flex items-center">';
        echo '          <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">';
        echo '              <span class="material-symbols-outlined text-white/50" style="font-size:20px;">search</span>';
        echo '          </div>';
        echo '          <input id="headerSearch" class="w-full bg-white/10 border border-white/10 rounded-full py-2.5 pl-11 pr-4 text-sm placeholder-white/40 text-white" placeholder="' . htmlspecialchars($searchPlaceholder, ENT_QUOTES, 'UTF-8') . '" type="text" />';
        echo '      </div>';
        echo '  </div>';
    } else {
        echo '  <div class="flex-1"></div>';
    }

    echo '  <div class="flex items-center gap-5">';
    echo '      <a href="notifications.php" class="hover:bg-neutral-800 rounded-lg transition-all p-2"><span class="material-symbols-outlined text-neutral-400">notifications</span></a>';
    echo '      <a href="provider_location.php" class="hover:bg-neutral-800 rounded-lg transition-all p-2" title="Company Profile"><span class="material-symbols-outlined text-neutral-400">business_center</span></a>';
    echo '      <a href="logout.php" class="text-neutral-400 hover:text-white" title="Logout"><span class="material-symbols-outlined">logout</span></a>';
    echo '  </div>';
    echo '</header>';
}
