<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/seeker_topbar.php';

requireRole('seeker');

$userId = currentUserId();
$postId = isset($_GET['post_id']) ? (int) $_GET['post_id'] : 0;

if ($postId <= 0) {
    header('Location: aggregator.php');
    exit;
}

$postStmt = $pdo->prepare(
    'SELECT p.id, p.title, p.description, p.skills, p.company_name, p.payout_model, p.share_percent,
            p.budget, p.work_mode, p.location, p.views, p.clicks, p.status, p.created_at,
            u.name AS poster_name
     FROM peer_hiring_posts p
     JOIN users u ON u.id = p.poster_user_id
     WHERE p.id = ? AND p.poster_user_id = ? LIMIT 1'
);
$postStmt->execute([$postId, $userId]);
$post = $postStmt->fetch();

if (!$post) {
    header('Location: aggregator.php');
    exit;
}

function postChartPath(array $series, int $width, int $height): string
{
    if (count($series) < 2) {
        return '';
    }
    $max = max(1, ...$series);
    $step = $width / (count($series) - 1);
    $pts = [];
    foreach ($series as $i => $v) {
        $x = (int) round($i * $step);
        $y = (int) round($height - (($v / $max) * ($height - 12)) - 6);
        $pts[] = $x . ',' . $y;
    }
    return 'M' . implode(' L', $pts);
}

$views = (int) ($post['views'] ?? 0);
$clicks = (int) ($post['clicks'] ?? 0);
$ctr = $views > 0 ? round(($clicks / $views) * 100, 1) : 0.0;

$viewsSeries = [
    max(1, (int) round($views * 0.22)),
    max(1, (int) round($views * 0.35)),
    max(1, (int) round($views * 0.48)),
    max(1, (int) round($views * 0.62)),
    max(1, (int) round($views * 0.74)),
    max(1, (int) round($views * 0.86)),
    max(1, $views),
];
$clickSeries = [
    max(1, (int) round($clicks * 0.18)),
    max(1, (int) round($clicks * 0.31)),
    max(1, (int) round($clicks * 0.45)),
    max(1, (int) round($clicks * 0.58)),
    max(1, (int) round($clicks * 0.7)),
    max(1, (int) round($clicks * 0.84)),
    max(1, $clicks),
];

$viewsPath = postChartPath($viewsSeries, 700, 210);
$clickPath = postChartPath($clickSeries, 700, 210);

$radius = 80;
$circ = 2 * pi() * $radius;
$clickArc = $circ * min(1, $clicks / max(1, $views));
$remainingArc = max(0.01, $circ - $clickArc);

$payoutModel = (string) ($post['payout_model'] ?? 'revenue_share');
$sharePercent = (int) ($post['share_percent'] ?? 0);
$payoutLabel = $payoutModel === 'revenue_share'
    ? ('Revenue share' . ($sharePercent > 0 ? ' (' . $sharePercent . '%)' : ''))
    : strtoupper($payoutModel);

$createdLabel = (string) date('M d, Y', strtotime((string) ($post['created_at'] ?? 'now')));
$skills = trim((string) ($post['skills'] ?? ''));
$skillItems = $skills !== '' ? preg_split('/\s*,\s*/', $skills) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post Analytics</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@300;400;500;600;700&family=Instrument+Serif:ital@1&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .details-shell {
            background:
                radial-gradient(740px 320px at 0% -12%, rgba(91, 233, 217, 0.16), transparent 55%),
                radial-gradient(680px 320px at 100% 0%, rgba(136, 176, 255, 0.17), transparent 54%),
                #05070b;
        }

        .details-card {
            border: 1px solid rgba(255, 255, 255, 0.12);
            background: linear-gradient(180deg, rgba(255,255,255,0.05), rgba(255,255,255,0.015));
            box-shadow: 0 18px 34px rgba(0,0,0,0.32);
        }

        .badge-chip {
            border: 1px solid rgba(255, 255, 255, 0.15);
            background: rgba(255, 255, 255, 0.08);
        }

        .entry-row + .entry-row {
            border-top: 1px solid rgba(255, 255, 255, 0.09);
        }
    </style>
</head>
<body class="details-shell text-white min-h-screen">
    <?php renderSeekerTopbar('hiring'); ?>

    <main class="pt-24 px-4 md:px-6 lg:px-8 pb-10">
        <div class="max-w-[1400px] mx-auto space-y-5">
            <section class="details-card rounded-3xl p-6 md:p-8">
                <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4">
                    <div>
                        <p class="text-xs uppercase tracking-[0.18em] text-white/55">My Post Details</p>
                        <h1 class="text-4xl md:text-5xl font-heading italic mt-2"><?php echo e((string) ($post['title'] ?? 'Untitled Post')); ?></h1>
                        <p class="text-white/65 mt-2 max-w-3xl"><?php echo e((string) ($post['description'] ?? '')); ?></p>
                    </div>
                    <div class="flex flex-wrap gap-2 text-xs">
                        <span class="badge-chip rounded-full px-3 py-1"><?php echo e($payoutLabel); ?></span>
                        <span class="badge-chip rounded-full px-3 py-1"><?php echo e((string) strtoupper((string) ($post['work_mode'] ?? 'remote'))); ?></span>
                        <span class="badge-chip rounded-full px-3 py-1">Posted <?php echo e($createdLabel); ?></span>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2 mt-4 text-xs text-white/70">
                    <a href="aggregator.php" class="liquid-glass rounded-full px-4 py-2">Back to Hiring Board</a>
                    <a href="job_preview.php?<?php echo e(http_build_query(['title' => (string) ($post['title'] ?? 'Untitled Post'), 'company' => (string) (($post['company_name'] ?? '') ?: ($post['poster_name'] ?? 'TalentSync')), 'location' => (string) (($post['location'] ?? '') ?: ucfirst((string) ($post['work_mode'] ?? 'remote'))), 'source' => 'Peer Hiring', 'url' => ('chat.php?user_id=' . $userId), 'description' => (string) ($post['description'] ?? '')])); ?>" class="liquid-glass rounded-full px-4 py-2">Open Public Details View</a>
                </div>
            </section>

            <section class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                <article class="details-card rounded-2xl p-5">
                    <p class="text-[11px] uppercase tracking-widest text-white/60">TOTAL VIEWS</p>
                    <p class="text-3xl font-semibold mt-1"><?php echo (int) $views; ?></p>
                </article>
                <article class="details-card rounded-2xl p-5">
                    <p class="text-[11px] uppercase tracking-widest text-white/60">TOTAL CLICKS</p>
                    <p class="text-3xl font-semibold mt-1"><?php echo (int) $clicks; ?></p>
                </article>
                <article class="details-card rounded-2xl p-5">
                    <p class="text-[11px] uppercase tracking-widest text-white/60">CLICK RATE</p>
                    <p class="text-3xl font-semibold mt-1"><?php echo e(number_format($ctr, 1)); ?>%</p>
                </article>
                <article class="details-card rounded-2xl p-5">
                    <p class="text-[11px] uppercase tracking-widest text-white/60">STATUS</p>
                    <p class="text-3xl font-semibold mt-1"><?php echo e((string) strtoupper((string) ($post['status'] ?? 'open'))); ?></p>
                </article>
            </section>

            <section class="grid grid-cols-1 2xl:grid-cols-12 gap-5">
                <article class="details-card rounded-2xl p-6 2xl:col-span-8">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-2xl font-heading italic">Performance Trend</h3>
                        <span class="text-xs text-white/55">Views vs Clicks</span>
                    </div>
                    <svg class="w-full h-[240px]" viewBox="0 0 700 210" role="img" aria-label="Post performance chart">
                        <line x1="0" y1="45" x2="700" y2="45" stroke="rgba(255,255,255,0.1)"></line>
                        <line x1="0" y1="100" x2="700" y2="100" stroke="rgba(255,255,255,0.1)"></line>
                        <line x1="0" y1="155" x2="700" y2="155" stroke="rgba(255,255,255,0.1)"></line>
                        <path d="<?php echo e($viewsPath); ?>" fill="none" stroke="#58d9cb" stroke-width="4" stroke-linecap="round"></path>
                        <path d="<?php echo e($clickPath); ?>" fill="none" stroke="#8bb7ff" stroke-width="4" stroke-linecap="round" stroke-dasharray="8 5"></path>
                    </svg>
                    <div class="flex items-center gap-5 text-xs text-white/65 mt-2">
                        <span class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-[#58d9cb]"></span>Views</span>
                        <span class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-[#8bb7ff]"></span>Clicks</span>
                    </div>
                </article>

                <article class="details-card rounded-2xl p-6 2xl:col-span-4">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="text-2xl font-heading italic">Click Funnel</h3>
                        <span class="text-xs text-white/55">Conversion</span>
                    </div>
                    <div class="relative w-52 h-52 mx-auto mt-4">
                        <svg class="w-full h-full -rotate-90" viewBox="0 0 220 220">
                            <circle cx="110" cy="110" r="80" fill="none" stroke="rgba(255,255,255,0.14)" stroke-width="20"></circle>
                            <circle cx="110" cy="110" r="80" fill="none" stroke="#58d9cb" stroke-width="20" stroke-dasharray="<?php echo e((string) round($clickArc, 2)); ?> <?php echo e((string) round($remainingArc, 2)); ?>"></circle>
                        </svg>
                        <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
                            <p class="text-3xl font-semibold"><?php echo e(number_format($ctr, 1)); ?>%</p>
                            <p class="text-xs text-white/55 uppercase tracking-wider">CTR</p>
                        </div>
                    </div>
                    <div class="mt-4 space-y-2 text-sm">
                        <div class="entry-row pt-2 flex items-center justify-between"><span>Views</span><span class="text-white/70"><?php echo (int) $views; ?></span></div>
                        <div class="entry-row pt-2 flex items-center justify-between"><span>Clicks</span><span class="text-white/70"><?php echo (int) $clicks; ?></span></div>
                        <div class="entry-row pt-2 flex items-center justify-between"><span>Budget</span><span class="text-white/70"><?php echo e((string) (($post['budget'] ?? '') ?: 'Negotiable')); ?></span></div>
                        <div class="entry-row pt-2 flex items-center justify-between"><span>Location</span><span class="text-white/70"><?php echo e((string) (($post['location'] ?? '') ?: ucfirst((string) ($post['work_mode'] ?? 'remote')))); ?></span></div>
                    </div>
                </article>
            </section>

            <section class="details-card rounded-2xl p-6">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                    <h3 class="text-2xl font-heading italic">Post Meta</h3>
                    <span class="text-xs text-white/55 uppercase tracking-wider">Sub-contract summary</span>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div class="badge-chip rounded-xl p-4">
                        <p class="text-white/55 text-xs uppercase tracking-wider">Client / Company</p>
                        <p class="mt-1 font-medium"><?php echo e((string) (($post['company_name'] ?? '') ?: ($post['poster_name'] ?? 'TalentSync'))); ?></p>
                    </div>
                    <div class="badge-chip rounded-xl p-4">
                        <p class="text-white/55 text-xs uppercase tracking-wider">Payout Model</p>
                        <p class="mt-1 font-medium"><?php echo e($payoutLabel); ?></p>
                    </div>
                    <div class="badge-chip rounded-xl p-4 md:col-span-2">
                        <p class="text-white/55 text-xs uppercase tracking-wider">Required Skills</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <?php if (!$skillItems): ?>
                                <span class="text-white/70">No specific skills listed.</span>
                            <?php endif; ?>
                            <?php foreach ($skillItems as $skill): ?>
                                <?php if (trim((string) $skill) !== ''): ?>
                                    <span class="badge-chip rounded-full px-3 py-1 text-xs"><?php echo e((string) $skill); ?></span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </main>
</body>
</html>
