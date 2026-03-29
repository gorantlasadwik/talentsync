<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/seeker_topbar.php';

requireLogin();

$jobId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$job = null;
$similarJobs = [];

if ($jobId > 0) {
    $stmt = $pdo->prepare('SELECT j.id, j.title, j.description, j.location, j.budget, j.created_at, u.name AS provider_name FROM jobs j LEFT JOIN users u ON u.id = j.provider_id WHERE j.id = ? LIMIT 1');
    $stmt->execute([$jobId]);
    $job = $stmt->fetch();

    if ($job) {
        $similarStmt = $pdo->prepare('SELECT id, title, location, budget FROM jobs WHERE id <> ? ORDER BY created_at DESC LIMIT 6');
        $similarStmt->execute([$jobId]);
        $similarJobs = $similarStmt->fetchAll();
    }
}

$jobTitle = (string) ($job['title'] ?? 'Job not found');
$jobDescription = trim((string) ($job['description'] ?? ''));
$jobLocation = (string) ($job['location'] ?? 'Not specified');
$jobBudget = (string) ($job['budget'] ?? 'Negotiable');
$providerName = (string) (($job['provider_name'] ?? '') ?: 'TalentSync Provider');

$type = 'Full-time';
$level = 'Mid-Level';
$titleBlob = strtolower($jobTitle . ' ' . $jobDescription);
if (str_contains($titleBlob, 'intern')) {
    $type = 'Internship';
    $level = 'Entry';
} elseif (str_contains($titleBlob, 'contract') || str_contains($titleBlob, 'freelance')) {
    $type = 'Contract';
}
if (str_contains($titleBlob, 'senior') || str_contains($titleBlob, 'lead')) {
    $level = 'Senior';
}

$responsibilities = [
    'Deliver high-quality outcomes aligned with the posted role goals and timeline.',
    'Collaborate with hiring stakeholders and communicate progress clearly.',
    'Own task execution end-to-end with focus on reliability and consistency.',
    'Contribute ideas that improve speed, quality, and user impact.',
];

$requirements = [
    'Relevant experience matching this role domain and expected responsibilities.',
    'Strong communication and ability to work effectively across teams.',
    'Problem-solving mindset with attention to quality and detail.',
    'Portfolio, project history, or proof of work is a strong plus.',
];

$benefits = [
    'Flexible collaboration model',
    'Fast hiring process on TalentSync',
    'High-ownership project opportunities',
    'Growth-focused team environment',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($jobTitle); ?> | TalentSync</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .job-hero {
            background:
                radial-gradient(500px 240px at 90% 0%, rgba(120, 170, 255, 0.2), transparent 60%),
                radial-gradient(460px 260px at 0% 100%, rgba(255, 155, 120, 0.14), transparent 60%),
                linear-gradient(180deg, #0b0e13 0%, #07090d 100%);
        }
        .job-chip {
            background: rgba(255, 255, 255, 0.14);
            border: 1px solid rgba(255, 255, 255, 0.18);
        }
        .job-pastel-1 { background: linear-gradient(145deg, rgba(124, 183, 255, 0.28), rgba(255, 255, 255, 0.06)); }
        .job-pastel-2 { background: linear-gradient(145deg, rgba(255, 153, 122, 0.26), rgba(255, 255, 255, 0.06)); }
        .job-pastel-3 { background: linear-gradient(145deg, rgba(152, 235, 196, 0.24), rgba(255, 255, 255, 0.06)); }
        .scroll-lite::-webkit-scrollbar { height: 8px; }
        .scroll-lite::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.2); border-radius: 999px; }
    </style>
</head>
<body class="bg-[#05070b] text-white min-h-screen">
    <?php if (($_SESSION['role'] ?? '') === 'seeker') { renderSeekerTopbar('jobs'); } else { ?>
    <header class="bg-neutral-900 text-white flex justify-between items-center px-8 h-20 w-full z-50 fixed top-0">
        <div class="flex items-center gap-10"><a href="dashboard.php" class="text-2xl font-heading italic hover:text-white/90">TalentSync</a><span class="text-sm text-white/70">Job Details</span></div>
        <a href="dashboard.php" class="text-white/70 hover:text-white">Dashboard</a>
    </header>
    <?php } ?>

    <?php if (!$job): ?>
    <main class="pt-24 px-6 pb-10">
        <div class="max-w-4xl mx-auto liquid-glass rounded-3xl p-6 md:p-8">
            <h1 class="text-3xl font-heading italic">Job not found</h1>
            <p class="text-white/70 mt-3">The requested job may have been removed.</p>
            <a href="seeker_dashboard.php" class="inline-block mt-6 liquid-glass rounded-full px-4 py-2 text-sm">Back to Jobs</a>
        </div>
    </main>
    <?php else: ?>
    <div class="pt-20">
        <header class="job-hero relative overflow-hidden px-6 md:px-12 lg:px-16 pt-14 pb-24">
            <div class="max-w-6xl mx-auto flex flex-col md:flex-row justify-between items-start md:items-end gap-8">
                <div class="space-y-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="job-chip text-white px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wide">TalentSync Listing</span>
                        <span class="text-white/70 text-xs flex items-center gap-1"><span class="material-symbols-outlined" style="font-size:16px;">location_on</span><?php echo e($jobLocation); ?></span>
                    </div>
                    <h1 class="text-4xl md:text-5xl font-heading italic leading-tight"><?php echo e($jobTitle); ?></h1>
                    <p class="text-white/75 text-lg">Posted by <?php echo e($providerName); ?> on <?php echo e((string) date('d M Y', strtotime((string) $job['created_at']))); ?></p>
                </div>
                <a href="chat.php" class="liquid-glass-strong rounded-xl px-8 py-4 font-semibold text-base">Apply / Contact</a>
            </div>
        </header>

        <div class="sticky top-20 z-40 bg-[#0f141b]/85 backdrop-blur-xl border-y border-white/10">
            <div class="max-w-6xl mx-auto px-6 md:px-12 lg:px-16 py-5 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="liquid-glass rounded-2xl p-4 flex items-center gap-3">
                    <span class="material-symbols-outlined">payments</span>
                    <div>
                        <p class="text-[11px] uppercase tracking-wide text-white/60">Budget</p>
                        <p class="font-semibold"><?php echo e($jobBudget); ?></p>
                    </div>
                </div>
                <div class="liquid-glass rounded-2xl p-4 flex items-center gap-3">
                    <span class="material-symbols-outlined">schedule</span>
                    <div>
                        <p class="text-[11px] uppercase tracking-wide text-white/60">Job Type</p>
                        <p class="font-semibold"><?php echo e($type); ?></p>
                    </div>
                </div>
                <div class="liquid-glass rounded-2xl p-4 flex items-center gap-3">
                    <span class="material-symbols-outlined">trending_up</span>
                    <div>
                        <p class="text-[11px] uppercase tracking-wide text-white/60">Level</p>
                        <p class="font-semibold"><?php echo e($level); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <main class="max-w-6xl mx-auto px-6 md:px-12 lg:px-16 py-12 grid grid-cols-1 lg:grid-cols-3 gap-10">
            <div class="lg:col-span-2 space-y-10">
                <section class="liquid-glass rounded-3xl p-6 md:p-8">
                    <h2 class="text-2xl font-heading italic mb-4">About the Role</h2>
                    <p class="text-white/80 leading-8 whitespace-pre-line"><?php echo e($jobDescription !== '' ? $jobDescription : 'No detailed description was provided for this listing yet.'); ?></p>
                </section>

                <section class="liquid-glass rounded-3xl p-6 md:p-8">
                    <h2 class="text-2xl font-heading italic mb-5">Responsibilities</h2>
                    <ul class="space-y-4">
                        <?php foreach ($responsibilities as $item): ?>
                            <li class="flex gap-3">
                                <span class="material-symbols-outlined text-white/90" style="font-variation-settings:'FILL' 1;">check_circle</span>
                                <span class="text-white/80 leading-7"><?php echo e($item); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>

                <section class="liquid-glass rounded-3xl p-6 md:p-8">
                    <h2 class="text-2xl font-heading italic mb-5">Requirements</h2>
                    <ul class="space-y-4">
                        <?php foreach ($requirements as $item): ?>
                            <li class="flex gap-3">
                                <span class="material-symbols-outlined text-white/90" style="font-variation-settings:'FILL' 1;">verified</span>
                                <span class="text-white/80 leading-7"><?php echo e($item); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            </div>

            <aside class="space-y-6">
                <div class="liquid-glass rounded-3xl p-6">
                    <div class="flex items-center gap-4 mb-5">
                        <div class="w-14 h-14 rounded-xl bg-white/10 flex items-center justify-center">
                            <span class="material-symbols-outlined">apartment</span>
                        </div>
                        <div>
                            <h3 class="font-semibold text-lg">TalentSync</h3>
                            <p class="text-sm text-white/60">Internal Hiring Network</p>
                        </div>
                    </div>
                    <p class="text-sm text-white/75 leading-7">This opportunity is posted directly on TalentSync by a verified provider. You can connect quickly and track responses from your dashboard.</p>
                    <div class="mt-5 space-y-3">
                        <a href="seeker_dashboard.php" class="block text-center liquid-glass rounded-xl px-4 py-3 text-sm font-semibold">View More Jobs</a>
                        <a href="chat.php" class="block text-center liquid-glass rounded-xl px-4 py-3 text-sm font-semibold">Open Messages</a>
                    </div>
                </div>

                <div class="liquid-glass rounded-3xl p-6">
                    <h4 class="font-semibold mb-4">Benefits Highlight</h4>
                    <ul class="space-y-3 text-sm text-white/80">
                        <?php foreach ($benefits as $item): ?>
                            <li class="flex gap-2"><span class="material-symbols-outlined" style="font-size:18px;">bolt</span><?php echo e($item); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </aside>
        </main>

        <section class="px-6 md:px-12 lg:px-16 pb-14">
            <div class="max-w-6xl mx-auto">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-3xl font-heading italic">Similar Jobs</h2>
                    <a href="seeker_dashboard.php" class="text-sm text-white/70 hover:text-white">View all</a>
                </div>
                <div class="flex gap-5 overflow-x-auto scroll-lite pb-4">
                    <?php if (!$similarJobs): ?>
                        <article class="min-w-[320px] job-pastel-1 rounded-3xl p-6 border border-white/15">
                            <div class="flex justify-between items-start mb-6">
                                <div class="w-12 h-12 rounded-xl bg-white/85 text-black flex items-center justify-center"><span class="material-symbols-outlined">work</span></div>
                                <span class="job-chip px-3 py-1 rounded-full text-[11px]">TalentSync</span>
                            </div>
                            <h3 class="text-xl font-semibold mb-2">No other internal jobs yet</h3>
                            <p class="text-white/75 text-sm mb-6">Postings from providers will appear here as similar opportunities.</p>
                            <a href="seeker_dashboard.php" class="liquid-glass rounded-xl px-4 py-2 text-sm inline-block">Browse jobs</a>
                        </article>
                    <?php endif; ?>

                    <?php foreach ($similarJobs as $idx => $sJob): ?>
                        <?php $tone = $idx % 3 === 0 ? 'job-pastel-1' : ($idx % 3 === 1 ? 'job-pastel-2' : 'job-pastel-3'); ?>
                        <article class="min-w-[320px] <?php echo e($tone); ?> rounded-3xl p-6 border border-white/15">
                            <div class="flex justify-between items-start mb-6">
                                <div class="w-12 h-12 rounded-xl bg-white/85 text-black flex items-center justify-center"><span class="material-symbols-outlined">work</span></div>
                                <span class="job-chip px-3 py-1 rounded-full text-[11px] uppercase">Internal</span>
                            </div>
                            <h3 class="text-xl font-semibold mb-2 leading-tight"><?php echo e((string) $sJob['title']); ?></h3>
                            <p class="text-white/75 text-sm mb-5"><?php echo e((string) (($sJob['location'] ?? '') ?: 'Location not specified')); ?></p>
                            <div class="flex items-center justify-between">
                                <p class="font-semibold"><?php echo e((string) (($sJob['budget'] ?? '') ?: 'Negotiable')); ?></p>
                                <a href="job_details.php?id=<?php echo e((string) $sJob['id']); ?>" class="bg-black/70 text-white px-4 py-2 rounded-lg border border-white/25 text-sm">Details</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <div class="mt-8 flex items-center gap-3">
                    <a href="javascript:history.back()" class="liquid-glass rounded-full px-4 py-2 text-sm">Back</a>
                    <?php if (($_SESSION['role'] ?? '') === 'seeker'): ?>
                        <a href="seeker_dashboard.php" class="liquid-glass rounded-full px-4 py-2 text-sm">Seeker Dashboard</a>
                    <?php elseif (($_SESSION['role'] ?? '') === 'provider'): ?>
                        <a href="provider_dashboard.php" class="liquid-glass rounded-full px-4 py-2 text-sm">Provider Dashboard</a>
                    <?php else: ?>
                        <a href="dashboard.php" class="liquid-glass rounded-full px-4 py-2 text-sm">Dashboard</a>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <footer class="border-t border-white/10 py-10 px-6 md:px-12 lg:px-16 bg-[#070a0f]">
            <div class="max-w-6xl mx-auto flex flex-col md:flex-row justify-between items-center gap-6 text-white/60 text-sm">
                <span class="text-white text-lg font-heading italic">TalentSync</span>
                <div class="flex items-center gap-6">
                    <a href="dashboard.php" class="hover:text-white">Dashboard</a>
                    <a href="notifications.php" class="hover:text-white">Notifications</a>
                    <a href="chat.php" class="hover:text-white">Support</a>
                </div>
                <span>TalentSync Pro</span>
            </div>
        </footer>
    </div>
    <?php endif; ?>
</body>
</html>
