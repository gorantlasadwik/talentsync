<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/seeker_topbar.php';
require_once __DIR__ . '/includes/provider_topbar.php';

requireLogin();

$viewerId = currentUserId();
$viewerRole = (string) ($_SESSION['role'] ?? '');
$requestedProviderId = isset($_GET['provider_id']) ? (int) $_GET['provider_id'] : 0;

$targetProviderId = $viewerRole === 'provider' ? $viewerId : 0;
$providerName = (string) ($_SESSION['name'] ?? 'Hiring Manager');

if ($requestedProviderId > 0) {
    $providerStmt = $pdo->prepare('SELECT id, name FROM users WHERE id = ? AND role = "provider" LIMIT 1');
    $providerStmt->execute([$requestedProviderId]);
    $providerRow = $providerStmt->fetch();
    if ($providerRow) {
        if ($viewerRole === 'provider' && $requestedProviderId !== $viewerId) {
            header('Location: hiring_board.php');
            exit;
        }
        $targetProviderId = (int) $providerRow['id'];
        $providerName = (string) $providerRow['name'];
    }
}

if ($targetProviderId <= 0) {
    header('Location: chat.php');
    exit;
}

$canEdit = $viewerRole === 'provider' && $targetProviderId === $viewerId;
$isSeekerViewer = $viewerRole === 'seeker';
$statusMessage = '';

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS provider_hiring_metrics (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL UNIQUE,
        applicants_total INT NOT NULL DEFAULT 158,
        interviewed_total INT NOT NULL DEFAULT 89,
        hired_total INT NOT NULL DEFAULT 24,
        time_to_hire_days INT NOT NULL DEFAULT 18,
        open_positions_total INT NOT NULL DEFAULT 7,
        ui_designer_pct INT NOT NULL DEFAULT 52,
        marketing_pct INT NOT NULL DEFAULT 28,
        graphic_design_pct INT NOT NULL DEFAULT 20,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )'
);

if ($canEdit && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_metrics'])) {
    $applicantsTotal = max(0, (int) ($_POST['applicants_total'] ?? 0));
    $interviewedTotal = max(0, (int) ($_POST['interviewed_total'] ?? 0));
    $hiredTotal = max(0, (int) ($_POST['hired_total'] ?? 0));
    $timeToHireDays = max(1, (int) ($_POST['time_to_hire_days'] ?? 18));
    $openPositionsTotal = max(0, (int) ($_POST['open_positions_total'] ?? 0));
    $uiDesignerPct = max(0, min(100, (int) ($_POST['ui_designer_pct'] ?? 52)));
    $marketingPct = max(0, min(100, (int) ($_POST['marketing_pct'] ?? 28)));
    $graphicDesignPct = max(0, min(100, (int) ($_POST['graphic_design_pct'] ?? 20)));

    $upsertMetrics = $pdo->prepare(
        'INSERT INTO provider_hiring_metrics (
            user_id, applicants_total, interviewed_total, hired_total, time_to_hire_days,
            open_positions_total, ui_designer_pct, marketing_pct, graphic_design_pct
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            applicants_total = VALUES(applicants_total),
            interviewed_total = VALUES(interviewed_total),
            hired_total = VALUES(hired_total),
            time_to_hire_days = VALUES(time_to_hire_days),
            open_positions_total = VALUES(open_positions_total),
            ui_designer_pct = VALUES(ui_designer_pct),
            marketing_pct = VALUES(marketing_pct),
            graphic_design_pct = VALUES(graphic_design_pct)'
    );
    $upsertMetrics->execute([
        $targetProviderId,
        $applicantsTotal,
        $interviewedTotal,
        $hiredTotal,
        $timeToHireDays,
        $openPositionsTotal,
        $uiDesignerPct,
        $marketingPct,
        $graphicDesignPct,
    ]);

    $statusMessage = 'Hiring board metrics updated.';
}

$metricsStmt = $pdo->prepare(
    'SELECT applicants_total, interviewed_total, hired_total, time_to_hire_days,
            open_positions_total, ui_designer_pct, marketing_pct, graphic_design_pct
     FROM provider_hiring_metrics WHERE user_id = ? LIMIT 1'
);
$metricsStmt->execute([$targetProviderId]);
$metrics = $metricsStmt->fetch() ?: [
    'applicants_total' => 158,
    'interviewed_total' => 89,
    'hired_total' => 24,
    'time_to_hire_days' => 18,
    'open_positions_total' => 7,
    'ui_designer_pct' => 52,
    'marketing_pct' => 28,
    'graphic_design_pct' => 20,
];

$totPct = max(1, (int) $metrics['ui_designer_pct'] + (int) $metrics['marketing_pct'] + (int) $metrics['graphic_design_pct']);
$uiPctNorm = (int) round(((int) $metrics['ui_designer_pct'] * 100) / $totPct);
$marketingPctNorm = (int) round(((int) $metrics['marketing_pct'] * 100) / $totPct);
$graphicPctNorm = max(0, 100 - $uiPctNorm - $marketingPctNorm);

$myJobsStmt = $pdo->prepare('SELECT id, title, description, source, location, created_at FROM jobs WHERE provider_id = ? ORDER BY created_at DESC LIMIT 3');
$myJobsStmt->execute([$targetProviderId]);
$myJobs = $myJobsStmt->fetchAll();

$locationStmt = $pdo->prepare('SELECT company_name, workplace_name, city FROM provider_locations WHERE user_id = ? LIMIT 1');
$locationStmt->execute([$targetProviderId]);
$location = $locationStmt->fetch() ?: ['company_name' => '', 'workplace_name' => '', 'city' => ''];

$companyLabel = trim((string) ($location['company_name'] ?? '')) ?: $providerName;
$cityLabel = trim((string) ($location['city'] ?? '')) ?: 'Not specified';
$pipelineUrl = 'company_pipeline.php?provider_id=' . $targetProviderId;

$applicantsTotal = (int) $metrics['applicants_total'];
$interviewedTotal = (int) $metrics['interviewed_total'];
$hiredTotal = (int) $metrics['hired_total'];
$timeToHireDays = (int) $metrics['time_to_hire_days'];

$applicantsGain = (string) max(1, (int) round(($applicantsTotal / max(1, $interviewedTotal + 20)) * 10));
$interviewGain = (string) max(1, (int) round(($interviewedTotal / max(1, $applicantsTotal + 15)) * 20));
$hiredGain = (string) max(1, (int) round(($hiredTotal / max(1, $interviewedTotal + 10)) * 50));

$monthApplicants = [
    max(1, (int) round($applicantsTotal * 0.52)),
    max(1, (int) round($applicantsTotal * 0.58)),
    max(1, (int) round($applicantsTotal * 0.61)),
    max(1, (int) round($applicantsTotal * 0.67)),
    max(1, (int) round($applicantsTotal * 0.72)),
    max(1, (int) round($applicantsTotal * 0.83)),
    $applicantsTotal,
];
$monthInterviews = [
    max(1, (int) round($interviewedTotal * 0.51)),
    max(1, (int) round($interviewedTotal * 0.57)),
    max(1, (int) round($interviewedTotal * 0.63)),
    max(1, (int) round($interviewedTotal * 0.68)),
    max(1, (int) round($interviewedTotal * 0.75)),
    max(1, (int) round($interviewedTotal * 0.86)),
    $interviewedTotal,
];

function chartPath(array $series, int $width, int $height): string
{
    $count = count($series);
    if ($count < 2) {
        return '';
    }

    $maxVal = max(1, ...$series);
    $stepX = $width / ($count - 1);
    $points = [];
    foreach ($series as $idx => $val) {
        $x = (int) round($idx * $stepX);
        $y = (int) round($height - (($val / $maxVal) * ($height - 14)) - 7);
        $points[] = $x . ',' . $y;
    }
    return 'M' . implode(' L', $points);
}

$appPath = chartPath($monthApplicants, 740, 210);
$intPath = chartPath($monthInterviews, 740, 210);

$radius = 80;
$circumference = 2 * pi() * $radius;
$arcUi = $circumference * ($uiPctNorm / 100);
$arcMarketing = $circumference * ($marketingPctNorm / 100);
$arcGraphic = $circumference * ($graphicPctNorm / 100);

$today = new DateTimeImmutable('now');
$currentMonthLabel = strtoupper($today->format('F Y'));
$currentDay = (int) $today->format('j');
$daysInMonth = (int) $today->format('t');
$firstOfMonth = $today->modify('first day of this month');
$firstWeekdayMon = (int) $firstOfMonth->format('N'); // 1 (Mon) ... 7 (Sun)

$calendarCells = [];
for ($i = 1; $i < $firstWeekdayMon; $i++) {
    $calendarCells[] = null;
}
for ($day = 1; $day <= $daysInMonth; $day++) {
    $calendarCells[] = $day;
}
$trailing = (7 - (count($calendarCells) % 7)) % 7;
for ($i = 0; $i < $trailing; $i++) {
    $calendarCells[] = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hiring Board</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@300;400;500;600;700&family=Instrument+Serif:ital@1&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        :root {
            --hb-bg: #05070b;
            --hb-surface: #0c1016;
            --hb-surface-soft: #111723;
            --hb-surface-card: #161f2c;
            --hb-border: rgba(184, 205, 232, 0.18);
            --hb-text: #f3f7fd;
            --hb-muted: rgba(222, 232, 247, 0.68);
            --hb-accent: #53d7c8;
            --hb-accent-soft: rgba(83, 215, 200, 0.18);
            --hb-warm: #f7a562;
            --hb-cool: #8cb6ff;
        }

        body {
            background:
                radial-gradient(850px 420px at 88% -10%, rgba(140, 182, 255, 0.18), transparent 58%),
                radial-gradient(640px 360px at 5% 0%, rgba(83, 215, 200, 0.12), transparent 62%),
                var(--hb-bg);
            color: var(--hb-text);
            font-family: 'Barlow', sans-serif;
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        html::-webkit-scrollbar,
        body::-webkit-scrollbar {
            width: 0;
            height: 0;
            background: transparent;
        }

        .brand-title {
            font-family: 'Instrument Serif', serif;
            font-style: italic;
        }

        .hide-scrollbar::-webkit-scrollbar {
            display: none;
        }

        .hide-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        .hb-card {
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.04), rgba(255, 255, 255, 0.015));
            border: 1px solid var(--hb-border);
            box-shadow: 0 22px 48px rgba(1, 7, 15, 0.45), inset 0 1px 0 rgba(255, 255, 255, 0.09);
            backdrop-filter: blur(8px);
        }

        .hb-kpi-bars span {
            display: block;
            width: 100%;
            border-radius: 3px 3px 0 0;
            background: linear-gradient(180deg, rgba(83, 215, 200, 0.96), rgba(83, 215, 200, 0.2));
        }

        .metric-chip {
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            padding: 0.2rem 0.55rem;
            letter-spacing: 0.01em;
        }

        .metric-chip-positive {
            background: var(--hb-accent-soft);
            color: #9af3e7;
        }

        .metric-chip-stable {
            background: rgba(140, 182, 255, 0.16);
            color: #c9dcff;
        }

        .nav-link-active {
            color: #ffffff;
            background: rgba(140, 182, 255, 0.2);
            border: 1px solid rgba(140, 182, 255, 0.35);
        }

        .calendar-day {
            border-radius: 10px;
            font-size: 11px;
            padding: 0.2rem 0;
            text-align: center;
            color: var(--hb-muted);
        }

        .calendar-day.active {
            color: #06131a;
            background: var(--hb-accent);
            font-weight: 700;
        }

        .task-row + .task-row {
            border-top: 1px solid rgba(255, 255, 255, 0.09);
        }
    </style>
</head>
<body class="min-h-screen">
    <?php if ($isSeekerViewer): ?>
        <?php renderSeekerTopbar('hiring'); ?>
    <?php else: ?>
        <?php renderProviderTopbar('hiring', false); ?>
    <?php endif; ?>

    <aside class="xl:fixed xl:left-0 xl:top-20 xl:h-[calc(100vh-5rem)] w-full xl:w-64 xl:border-r border-white/10 bg-[#070a10] px-5 py-6 flex xl:flex-col gap-3 xl:gap-0 z-40 overflow-x-auto">
        <nav class="flex-1 flex xl:flex-col gap-2 text-sm min-w-max xl:min-w-0">
            <a href="<?php echo $canEdit ? 'hiring_board.php' : ('hiring_board.php?provider_id=' . $targetProviderId); ?>" class="nav-link-active rounded-xl px-4 py-2.5">Dashboard</a>
            <a href="<?php echo $canEdit ? 'provider_dashboard.php' : 'chat.php'; ?>" class="rounded-xl px-4 py-2.5 text-white/70 hover:text-white hover:bg-white/10"><?php echo $canEdit ? 'Pipeline' : 'Back to Chat'; ?></a>
            <a href="<?php echo $canEdit ? 'post_job.php' : 'dashboard.php'; ?>" class="rounded-xl px-4 py-2.5 text-white/70 hover:text-white hover:bg-white/10"><?php echo $canEdit ? 'Post New Job' : 'Role Router'; ?></a>
            <a href="chat.php" class="rounded-xl px-4 py-2.5 text-white/70 hover:text-white hover:bg-white/10">Messages</a>
            <a href="<?php echo $canEdit ? 'provider_location.php' : 'profile.php'; ?>" class="rounded-xl px-4 py-2.5 text-white/70 hover:text-white hover:bg-white/10"><?php echo $canEdit ? 'Company Profile' : 'My Profile'; ?></a>
            <a href="logout.php" class="rounded-xl px-4 py-2.5 text-white/70 hover:text-white hover:bg-white/10 xl:mt-auto">Logout</a>
        </nav>

        <a href="<?php echo $canEdit ? 'post_job.php' : 'chat.php'; ?>" class="hidden xl:flex mt-8 w-full items-center justify-center gap-2 rounded-xl bg-[#53d7c8] text-[#082625] font-semibold py-3 hover:brightness-105 transition">
            <span class="material-symbols-outlined text-base">add</span>
            <?php echo $canEdit ? 'Post New Job' : 'Back to Chat'; ?>
        </a>
    </aside>

    <div class="xl:ml-64 xl:mr-80 pt-24 xl:pt-24 pb-8">
        <main class="px-4 md:px-6 lg:px-8 pt-6">
            <section class="mb-7">
                <h2 class="text-4xl font-semibold tracking-tight"><?php echo $canEdit ? ('Welcome back, ' . e($providerName) . '!') : (e($providerName) . ' Hiring Board'); ?></h2>
                <p class="text-white/70 mt-1 text-sm">Company: <?php echo e($companyLabel); ?> | Location: <?php echo e($cityLabel); ?></p>
                <?php if ($statusMessage): ?>
                    <p class="text-[#9af3e7] text-sm mt-2"><?php echo e($statusMessage); ?></p>
                <?php endif; ?>
                <?php if (!$canEdit): ?>
                    <p class="text-white/55 mt-2 text-sm">Read-only view of provider board metrics.</p>
                <?php endif; ?>
            </section>

            <section class="grid grid-cols-1 md:grid-cols-2 2xl:grid-cols-4 gap-4 mb-8">
                <article class="hb-card rounded-2xl p-5">
                    <p class="text-[11px] font-semibold tracking-widest text-white/60">APPLICANTS</p>
                    <h3 class="text-3xl font-semibold mt-1"><?php echo number_format($applicantsTotal); ?></h3>
                    <div class="flex items-center gap-2 mt-4">
                        <span class="metric-chip metric-chip-positive">+<?php echo e($applicantsGain); ?>%</span>
                        <div class="hb-kpi-bars h-7 flex-1 flex items-end gap-1">
                            <span style="height:35%"></span>
                            <span style="height:60%"></span>
                            <span style="height:48%"></span>
                            <span style="height:74%"></span>
                            <span style="height:100%"></span>
                        </div>
                    </div>
                </article>

                <article class="hb-card rounded-2xl p-5">
                    <p class="text-[11px] font-semibold tracking-widest text-white/60">INTERVIEWED</p>
                    <h3 class="text-3xl font-semibold mt-1"><?php echo number_format($interviewedTotal); ?></h3>
                    <div class="flex items-center gap-2 mt-4">
                        <span class="metric-chip metric-chip-positive">+<?php echo e($interviewGain); ?>%</span>
                        <div class="hb-kpi-bars h-7 flex-1 flex items-end gap-1">
                            <span style="height:30%"></span>
                            <span style="height:70%"></span>
                            <span style="height:42%"></span>
                            <span style="height:68%"></span>
                            <span style="height:88%"></span>
                        </div>
                    </div>
                </article>

                <article class="hb-card rounded-2xl p-5">
                    <p class="text-[11px] font-semibold tracking-widest text-white/60">HIRED</p>
                    <h3 class="text-3xl font-semibold mt-1"><?php echo number_format($hiredTotal); ?></h3>
                    <div class="flex items-center gap-2 mt-4">
                        <span class="metric-chip metric-chip-positive">+<?php echo e($hiredGain); ?>%</span>
                        <div class="hb-kpi-bars h-7 flex-1 flex items-end gap-1">
                            <span style="height:15%"></span>
                            <span style="height:38%"></span>
                            <span style="height:31%"></span>
                            <span style="height:70%"></span>
                            <span style="height:94%"></span>
                        </div>
                    </div>
                </article>

                <article class="hb-card rounded-2xl p-5">
                    <p class="text-[11px] font-semibold tracking-widest text-white/60">TIME TO HIRE</p>
                    <h3 class="text-3xl font-semibold mt-1"><?php echo e((string) $timeToHireDays); ?> days</h3>
                    <div class="flex items-center gap-2 mt-4">
                        <span class="metric-chip metric-chip-stable">Stable</span>
                        <div class="h-2 rounded-full bg-[#2a3d55] flex-1 overflow-hidden">
                            <div class="h-full rounded-full bg-gradient-to-r from-[#8cb6ff] to-[#53d7c8]" style="width:72%"></div>
                        </div>
                    </div>
                </article>
            </section>

            <section class="grid grid-cols-1 2xl:grid-cols-12 gap-5 mb-8">
                <article class="hb-card rounded-2xl p-6 2xl:col-span-8">
                    <div class="flex flex-wrap gap-3 items-center justify-between mb-5">
                        <div>
                            <h4 class="text-xl font-semibold">Hiring Overviews</h4>
                            <p class="text-white/60 text-sm">Applicants vs interviews activity (7-month trend)</p>
                        </div>
                        <div class="flex gap-4 text-xs">
                            <div class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full bg-[#53d7c8]"></span>Applicants</div>
                            <div class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full bg-[#f7a562]"></span>Interviews</div>
                        </div>
                    </div>
                    <svg class="w-full h-[240px]" viewBox="0 0 740 210" role="img" aria-label="Hiring activity chart">
                        <line x1="0" y1="45" x2="740" y2="45" stroke="rgba(255,255,255,0.1)" stroke-width="1"></line>
                        <line x1="0" y1="100" x2="740" y2="100" stroke="rgba(255,255,255,0.1)" stroke-width="1"></line>
                        <line x1="0" y1="155" x2="740" y2="155" stroke="rgba(255,255,255,0.1)" stroke-width="1"></line>
                        <path d="<?php echo e($appPath); ?>" fill="none" stroke="#53d7c8" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"></path>
                        <path d="<?php echo e($intPath); ?>" fill="none" stroke="#f7a562" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" stroke-dasharray="8 6"></path>
                    </svg>
                    <div class="grid grid-cols-7 text-[11px] text-white/55 px-1 mt-2">
                        <span>Jan</span><span>Feb</span><span>Mar</span><span>Apr</span><span>May</span><span>Jun</span><span>Jul</span>
                    </div>
                </article>

                <article class="hb-card rounded-2xl p-6 2xl:col-span-4 flex flex-col items-center">
                    <div class="w-full mb-5">
                        <h4 class="text-xl font-semibold">Open Positions</h4>
                        <p class="text-white/60 text-sm">Role distribution</p>
                    </div>

                    <div class="relative w-52 h-52">
                        <svg class="w-full h-full -rotate-90" viewBox="0 0 200 200">
                            <circle cx="100" cy="100" r="80" fill="none" stroke="rgba(255,255,255,0.12)" stroke-width="22"></circle>
                            <circle cx="100" cy="100" r="80" fill="none" stroke="#53d7c8" stroke-width="22" stroke-dasharray="<?php echo e((string) round($arcUi, 2)); ?> <?php echo e((string) round($circumference - $arcUi, 2)); ?>" stroke-linecap="butt"></circle>
                            <circle cx="100" cy="100" r="80" fill="none" stroke="#f7a562" stroke-width="22" stroke-dasharray="<?php echo e((string) round($arcMarketing, 2)); ?> <?php echo e((string) round($circumference - $arcMarketing, 2)); ?>" stroke-dashoffset="-<?php echo e((string) round($arcUi, 2)); ?>"></circle>
                            <circle cx="100" cy="100" r="80" fill="none" stroke="#8cb6ff" stroke-width="22" stroke-dasharray="<?php echo e((string) round($arcGraphic, 2)); ?> <?php echo e((string) round($circumference - $arcGraphic, 2)); ?>" stroke-dashoffset="-<?php echo e((string) round($arcUi + $arcMarketing, 2)); ?>"></circle>
                        </svg>
                        <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
                            <p class="text-3xl font-semibold"><?php echo e((string) ((int) $metrics['open_positions_total'])); ?></p>
                            <p class="text-[11px] text-white/55 tracking-wider uppercase">Open Roles</p>
                        </div>
                    </div>

                    <div class="w-full mt-6 space-y-2.5 text-sm">
                        <div class="flex items-center justify-between"><span class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-[#53d7c8]"></span>UI Designer</span><span class="text-white/65"><?php echo e((string) $uiPctNorm); ?>%</span></div>
                        <div class="flex items-center justify-between"><span class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-[#f7a562]"></span>Marketing</span><span class="text-white/65"><?php echo e((string) $marketingPctNorm); ?>%</span></div>
                        <div class="flex items-center justify-between"><span class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-[#8cb6ff]"></span>Graphic Design</span><span class="text-white/65"><?php echo e((string) $graphicPctNorm); ?>%</span></div>
                    </div>
                </article>
            </section>

            <section class="hb-card rounded-2xl p-6">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                    <h4 class="text-xl font-semibold">Daily Tasks List</h4>
                    <a href="<?php echo e($pipelineUrl); ?>" class="text-sm text-[#9af3e7] hover:underline">View pipeline</a>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm min-w-[680px]">
                        <thead class="text-white/55 text-xs uppercase tracking-wider">
                            <tr>
                                <th class="text-left py-3">Task Name</th>
                                <th class="text-left py-3">Team</th>
                                <th class="text-left py-3">Status</th>
                                <th class="text-right py-3">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($myJobs): ?>
                                <?php foreach ($myJobs as $index => $job): ?>
                                    <?php $detailsUrl = 'job_preview.php?' . http_build_query([
                                        'title' => (string) ($job['title'] ?? 'Untitled Role'),
                                        'company' => $companyLabel,
                                        'location' => (string) (($job['location'] ?? '') ?: $cityLabel),
                                        'source' => (string) (($job['source'] ?? '') ?: 'internal'),
                                        'description' => (string) (($job['description'] ?? '') ?: ''),
                                    ]); ?>
                                    <tr class="task-row">
                                        <td class="py-3.5 font-medium">Review candidates for <?php echo e((string) $job['title']); ?></td>
                                        <td class="py-3.5">
                                            <div class="flex -space-x-2">
                                                <span class="w-8 h-8 rounded-full bg-[#203247] border border-white/10 inline-flex items-center justify-center text-xs">HR</span>
                                                <span class="w-8 h-8 rounded-full bg-[#2a2f45] border border-white/10 inline-flex items-center justify-center text-xs">TA</span>
                                                <span class="w-8 h-8 rounded-full bg-[#334740] border border-white/10 inline-flex items-center justify-center text-xs">+<?php echo e((string) ($index + 1)); ?></span>
                                            </div>
                                        </td>
                                        <td class="py-3.5">
                                            <?php if ($index === 0): ?>
                                                <span class="metric-chip metric-chip-positive">In Progress</span>
                                            <?php elseif ($index === 1): ?>
                                                <span class="metric-chip" style="background: rgba(247,165,98,0.18); color:#ffd8ba;">Need Review</span>
                                            <?php else: ?>
                                                <span class="metric-chip metric-chip-stable">Done</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3.5 text-right">
                                            <a href="<?php echo e($detailsUrl); ?>" class="text-white/70 hover:text-white">Details</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr class="task-row">
                                    <td class="py-3.5 font-medium">Create your first job and start hiring workflow</td>
                                    <td class="py-3.5">Recruitment</td>
                                    <td class="py-3.5"><span class="metric-chip" style="background: rgba(140,182,255,0.16); color:#d4e4ff;">Pending</span></td>
                                    <td class="py-3.5 text-right"><a href="<?php echo $canEdit ? 'post_job.php' : e($pipelineUrl); ?>" class="text-white/70 hover:text-white"><?php echo $canEdit ? 'Post Job' : 'Pipeline'; ?></a></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>

        <aside class="px-4 md:px-6 lg:px-8 mt-6 xl:mt-0 xl:fixed xl:top-20 xl:h-[calc(100vh-5rem)] xl:pt-6 xl:right-0 xl:w-80 xl:pb-6 xl:border-l border-white/10 bg-[#090d14] xl:overflow-y-auto hide-scrollbar">
            <section class="hb-card rounded-2xl p-5 mb-5">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="text-lg font-semibold">Schedule</h4>
                    <span class="text-[11px] text-white/55 uppercase tracking-wider"><?php echo e($currentMonthLabel); ?></span>
                </div>
                <div class="grid grid-cols-7 gap-1 text-[10px] text-white/45 mb-2 font-semibold">
                    <span>MO</span><span>TU</span><span>WE</span><span>TH</span><span>FR</span><span>SA</span><span>SU</span>
                </div>
                <div class="grid grid-cols-7 gap-1">
                    <?php foreach ($calendarCells as $day): ?>
                        <?php if ($day === null): ?>
                            <span class="calendar-day opacity-0">0</span>
                        <?php else: ?>
                            <span class="calendar-day <?php echo $day === $currentDay ? 'active' : ''; ?>"><?php echo e((string) $day); ?></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </section>

            <?php if ($canEdit): ?>
                <section class="hb-card rounded-2xl p-5">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="text-lg font-semibold">Edit Metrics</h4>
                        <a href="provider_location.php#hiring-metrics" class="text-[12px] text-[#9af3e7] hover:underline">Company profile</a>
                    </div>
                    <form method="post" class="space-y-3">
                        <input type="hidden" name="save_metrics" value="1">
                        <input class="ui-input" type="number" name="applicants_total" min="0" value="<?php echo e((string) $applicantsTotal); ?>" placeholder="Applicants">
                        <input class="ui-input" type="number" name="interviewed_total" min="0" value="<?php echo e((string) $interviewedTotal); ?>" placeholder="Interviewed">
                        <input class="ui-input" type="number" name="hired_total" min="0" value="<?php echo e((string) $hiredTotal); ?>" placeholder="Hired">
                        <input class="ui-input" type="number" name="time_to_hire_days" min="1" value="<?php echo e((string) $timeToHireDays); ?>" placeholder="Time to hire (days)">
                        <input class="ui-input" type="number" name="open_positions_total" min="0" value="<?php echo e((string) ((int) $metrics['open_positions_total'])); ?>" placeholder="Open roles">
                        <div class="grid grid-cols-3 gap-2">
                            <input class="ui-input" type="number" name="ui_designer_pct" min="0" max="100" value="<?php echo e((string) ((int) $metrics['ui_designer_pct'])); ?>" placeholder="UI %">
                            <input class="ui-input" type="number" name="marketing_pct" min="0" max="100" value="<?php echo e((string) ((int) $metrics['marketing_pct'])); ?>" placeholder="Mkt %">
                            <input class="ui-input" type="number" name="graphic_design_pct" min="0" max="100" value="<?php echo e((string) ((int) $metrics['graphic_design_pct'])); ?>" placeholder="Gfx %">
                        </div>
                        <button type="submit" class="w-full liquid-glass-strong rounded-full py-3 text-sm">Save Board Metrics</button>
                    </form>
                </section>
            <?php else: ?>
                <section class="hb-card rounded-2xl p-5">
                    <h4 class="text-lg font-semibold mb-2">Board Access</h4>
                    <p class="text-sm text-white/65">This board is shared by the hiring company for viewing only.</p>
                    <a href="chat.php" class="mt-4 inline-block liquid-glass rounded-full px-4 py-2 text-sm">Back to Messages</a>
                </section>
            <?php endif; ?>
        </aside>
    </div>
</body>
</html>
