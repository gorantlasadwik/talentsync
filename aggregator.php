<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/seeker_topbar.php';

requireRole('seeker');

$userId = currentUserId();
$statusMessage = '';

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS peer_hiring_posts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        poster_user_id INT NOT NULL,
        title VARCHAR(190) NOT NULL,
        description TEXT NOT NULL,
        skills VARCHAR(255) DEFAULT NULL,
        company_name VARCHAR(190) DEFAULT NULL,
        payout_model ENUM("revenue_share", "fixed", "hourly") NOT NULL DEFAULT "revenue_share",
        share_percent INT DEFAULT NULL,
        budget VARCHAR(100) DEFAULT NULL,
        work_mode VARCHAR(40) DEFAULT "remote",
        location VARCHAR(120) DEFAULT NULL,
        views INT NOT NULL DEFAULT 0,
        clicks INT NOT NULL DEFAULT 0,
        status ENUM("open", "closed") NOT NULL DEFAULT "open",
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (poster_user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_peer_posts_owner (poster_user_id),
        INDEX idx_peer_posts_status (status, created_at)
    )'
);

if (($_GET['action'] ?? '') === 'track_click' && isset($_GET['post_id'])) {
    $postId = (int) $_GET['post_id'];
    $target = (string) ($_GET['target'] ?? 'details');

    $postStmt = $pdo->prepare(
        'SELECT p.id, p.poster_user_id, p.title, p.description, p.company_name, p.location, p.work_mode,
                p.payout_model, p.share_percent, p.budget, p.skills, u.name AS poster_name
         FROM peer_hiring_posts p
         JOIN users u ON u.id = p.poster_user_id
         WHERE p.id = ? LIMIT 1'
    );
    $postStmt->execute([$postId]);
    $post = $postStmt->fetch();

    if (!$post) {
        header('Location: aggregator.php');
        exit;
    }

    if ((int) $post['poster_user_id'] !== $userId) {
        $pdo->prepare('UPDATE peer_hiring_posts SET clicks = clicks + 1 WHERE id = ?')->execute([$postId]);
    }

    if ($target === 'apply') {
        header('Location: chat.php?user_id=' . (int) $post['poster_user_id']);
        exit;
    }

    $desc = trim((string) ($post['description'] ?? ''));
    $skills = trim((string) ($post['skills'] ?? ''));
    $share = (int) ($post['share_percent'] ?? 0);
    $payoutModel = (string) ($post['payout_model'] ?? 'revenue_share');
    $budget = trim((string) ($post['budget'] ?? ''));

    $extra = [];
    if ($skills !== '') {
        $extra[] = 'Skills: ' . $skills;
    }
    if ($payoutModel === 'revenue_share' && $share > 0) {
        $extra[] = 'Revenue Share: ' . $share . '%';
    }
    if ($budget !== '') {
        $extra[] = 'Budget/Expected payout: ' . $budget;
    }

    $fullDescription = $desc;
    if ($extra) {
        $fullDescription .= "\n\n" . implode(' | ', $extra);
    }

    $previewUrl = 'job_preview.php?' . http_build_query([
        'title' => (string) ($post['title'] ?? 'Sub-contract Opportunity'),
        'company' => (string) ($post['company_name'] ?: $post['poster_name']),
        'location' => (string) (($post['location'] ?: ucfirst((string) ($post['work_mode'] ?? 'remote')))),
        'source' => 'Peer Hiring',
        'url' => 'chat.php?user_id=' . (int) $post['poster_user_id'],
        'description' => $fullDescription,
    ]);

    header('Location: ' . $previewUrl);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_peer_post'])) {
    if (!verifyCsrf((string) ($_POST['csrf_token'] ?? ''))) {
        $statusMessage = 'Session expired. Refresh and submit again.';
    } else {
        $title = trim((string) ($_POST['title'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $skills = trim((string) ($_POST['skills'] ?? ''));
        $companyName = trim((string) ($_POST['company_name'] ?? ''));
        $payoutModel = (string) ($_POST['payout_model'] ?? 'revenue_share');
        $sharePercent = (int) ($_POST['share_percent'] ?? 0);
        $budget = trim((string) ($_POST['budget'] ?? ''));
        $workMode = (string) ($_POST['work_mode'] ?? 'remote');
        $location = trim((string) ($_POST['location'] ?? ''));

        if ($title === '' || $description === '') {
            $statusMessage = 'Title and description are required.';
        } else {
            $validPayouts = ['revenue_share', 'fixed', 'hourly'];
            if (!in_array($payoutModel, $validPayouts, true)) {
                $payoutModel = 'revenue_share';
            }

            $validModes = ['remote', 'hybrid', 'onsite'];
            if (!in_array($workMode, $validModes, true)) {
                $workMode = 'remote';
            }

            $sharePercent = max(0, min(95, $sharePercent));
            if ($payoutModel !== 'revenue_share') {
                $sharePercent = null;
            }

            $insert = $pdo->prepare(
                'INSERT INTO peer_hiring_posts (
                    poster_user_id, title, description, skills, company_name,
                    payout_model, share_percent, budget, work_mode, location
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $insert->execute([
                $userId,
                $title,
                $description,
                $skills !== '' ? $skills : null,
                $companyName !== '' ? $companyName : null,
                $payoutModel,
                $sharePercent,
                $budget !== '' ? $budget : null,
                $workMode,
                $location !== '' ? $location : null,
            ]);

            $statusMessage = 'Sub-contract opportunity posted successfully.';
        }
    }
}

$myPostsStmt = $pdo->prepare(
    'SELECT id, title, payout_model, share_percent, budget, created_at
     FROM peer_hiring_posts
     WHERE poster_user_id = ?
     ORDER BY created_at DESC'
);
$myPostsStmt->execute([$userId]);
$myPosts = $myPostsStmt->fetchAll();

$marketStmt = $pdo->prepare(
    'SELECT p.id, p.poster_user_id, p.title, p.description, p.skills, p.company_name, p.payout_model,
            p.share_percent, p.budget, p.work_mode, p.location, p.created_at, u.name AS poster_name
     FROM peer_hiring_posts p
     JOIN users u ON u.id = p.poster_user_id
     WHERE p.status = "open" AND p.poster_user_id <> ?
     ORDER BY p.created_at DESC
     LIMIT 60'
);
$marketStmt->execute([$userId]);
$marketPosts = $marketStmt->fetchAll();

if (!isset($_SESSION['peer_hiring_seen_posts']) || !is_array($_SESSION['peer_hiring_seen_posts'])) {
    $_SESSION['peer_hiring_seen_posts'] = [];
}

$seenMap = $_SESSION['peer_hiring_seen_posts'];
$newlySeenIds = [];

foreach ($marketPosts as $post) {
    $pid = (int) $post['id'];
    if (!isset($seenMap[$pid])) {
        $newlySeenIds[] = $pid;
        $seenMap[$pid] = time();
    }
}

if ($newlySeenIds) {
    $ph = implode(',', array_fill(0, count($newlySeenIds), '?'));
    $params = array_merge([count($newlySeenIds)], $newlySeenIds);
    $upd = $pdo->prepare('UPDATE peer_hiring_posts SET views = views + ? WHERE id IN (' . $ph . ')');
    $upd->execute($params);
}

if (count($seenMap) > 300) {
    asort($seenMap);
    $seenMap = array_slice($seenMap, -300, null, true);
}
$_SESSION['peer_hiring_seen_posts'] = $seenMap;

$totalMyPosts = count($myPosts);
$csrf = csrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hiring Marketplace</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@300;400;500;600;700&family=Instrument+Serif:ital@1&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .hire-shell {
            background:
                radial-gradient(760px 320px at 2% -10%, rgba(92, 235, 220, 0.16), transparent 56%),
                radial-gradient(680px 320px at 96% 0%, rgba(118, 159, 255, 0.16), transparent 56%),
                #05070b;
        }

        .hire-card {
            border: 1px solid rgba(255, 255, 255, 0.12);
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.05), rgba(255, 255, 255, 0.015));
            box-shadow: 0 18px 34px rgba(0, 0, 0, 0.3);
        }

        .chip {
            border: 1px solid rgba(255, 255, 255, 0.16);
            background: rgba(255, 255, 255, 0.08);
        }

        .sidebar-link {
            color: rgba(255, 255, 255, 0.72);
            border-radius: 12px;
            padding: 0.72rem 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            transition: background .18s ease, color .18s ease;
        }

        .sidebar-link:hover {
            color: #fff;
            background: rgba(255, 255, 255, 0.09);
        }

        .sidebar-link.active {
            color: #fff;
            background: rgba(129, 170, 255, 0.2);
            border: 1px solid rgba(129, 170, 255, 0.28);
        }
    </style>
</head>
<body class="hire-shell text-white min-h-screen">
    <?php renderSeekerTopbar('hiring'); ?>

    <div class="pt-24 pb-10 px-4 md:px-6 lg:px-8">
        <div class="max-w-[1500px] mx-auto xl:grid xl:grid-cols-12 gap-5">
            <aside class="xl:col-span-3 hire-card rounded-3xl p-5 md:p-6 h-fit xl:sticky xl:top-24">
                <h2 class="text-xl font-heading italic">Freelancer Hiring</h2>
                <p class="text-white/55 text-xs mt-1">Post sub-contract jobs and hire collaborators</p>

                <nav class="mt-5 space-y-2 text-sm">
                    <a href="aggregator.php" class="sidebar-link active"><span class="material-symbols-outlined" style="font-size:18px;">dashboard</span>Dashboard</a>
                    <a href="seeker_dashboard.php" class="sidebar-link"><span class="material-symbols-outlined" style="font-size:18px;">work</span>Find Jobs</a>
                    <a href="chat.php" class="sidebar-link"><span class="material-symbols-outlined" style="font-size:18px;">chat</span>Messages</a>
                    <a href="profile.php" class="sidebar-link"><span class="material-symbols-outlined" style="font-size:18px;">account_circle</span>Profile</a>
                </nav>

                <div class="mt-6 chip rounded-2xl p-4">
                    <p class="text-[11px] uppercase tracking-wider text-white/60">My Active Posts</p>
                    <p class="text-2xl font-semibold mt-1"><?php echo (int) $totalMyPosts; ?></p>
                    <p class="text-xs text-white/55 mt-1">Open a post details page to see views, clicks and charts.</p>
                </div>
            </aside>

            <main class="xl:col-span-9 space-y-5 mt-5 xl:mt-0">
                <section class="hire-card rounded-3xl p-6 md:p-8">
                    <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4">
                        <div>
                            <p class="text-xs uppercase tracking-[0.18em] text-white/55">Peer Hiring Studio</p>
                            <h1 class="text-4xl md:text-5xl font-heading italic mt-2">Build Your Sub-contract Team</h1>
                            <p class="text-white/65 mt-2 max-w-3xl">Create opportunities where freelancers work under your client projects with income-sharing or payout-based models.</p>
                        </div>
                        <div class="flex flex-wrap gap-2 text-xs">
                            <span class="chip rounded-full px-3 py-1">Revenue Share</span>
                            <span class="chip rounded-full px-3 py-1">Sub-contract Jobs</span>
                            <span class="chip rounded-full px-3 py-1">Peer Hiring</span>
                        </div>
                    </div>
                    <?php if ($statusMessage !== ''): ?>
                        <p class="text-[#93f1e3] mt-4 text-sm"><?php echo e($statusMessage); ?></p>
                    <?php endif; ?>
                </section>

                <section class="grid grid-cols-1 2xl:grid-cols-12 gap-5">
                    <article class="hire-card rounded-3xl p-5 md:p-6 2xl:col-span-8">
                        <div class="flex items-center justify-between">
                            <h3 class="text-2xl font-heading italic">Post New Sub-contract</h3>
                            <span class="text-xs text-white/55 uppercase tracking-wider">Visible to other freelancers only</span>
                        </div>
                        <form method="post" class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-3">
                            <input type="hidden" name="create_peer_post" value="1">
                            <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
                            <input class="ui-input" type="text" name="title" placeholder="Role title (e.g., React Support Developer)" required>
                            <input class="ui-input" type="text" name="company_name" placeholder="Client company name (optional)">
                            <textarea class="ui-textarea md:col-span-2" name="description" rows="4" placeholder="Describe scope, expected outcomes, and collaboration style" required></textarea>
                            <input class="ui-input md:col-span-2" type="text" name="skills" placeholder="Skills needed (comma separated)">
                            <select class="ui-select" name="payout_model">
                                <option value="revenue_share">Revenue share</option>
                                <option value="fixed">Fixed payout</option>
                                <option value="hourly">Hourly payout</option>
                            </select>
                            <input class="ui-input" type="number" name="share_percent" min="0" max="95" placeholder="Revenue share % (if share model)">
                            <input class="ui-input" type="text" name="budget" placeholder="Budget or payout estimate">
                            <select class="ui-select" name="work_mode">
                                <option value="remote">Remote</option>
                                <option value="hybrid">Hybrid</option>
                                <option value="onsite">Onsite</option>
                            </select>
                            <input class="ui-input md:col-span-2" type="text" name="location" placeholder="Location / timezone preference">
                            <button type="submit" class="liquid-glass-strong rounded-full px-5 py-3 text-sm md:col-span-2">Publish Opportunity</button>
                        </form>
                    </article>

                    <article class="hire-card rounded-3xl p-5 md:p-6 2xl:col-span-4">
                        <h3 class="text-2xl font-heading italic">My Active Posts</h3>
                        <p class="text-sm text-white/60 mt-1">Click any post and open detailed analytics page</p>
                        <div class="max-h-[360px] overflow-y-auto pr-1 space-y-2 mt-4">
                            <?php if (!$myPosts): ?>
                                <p class="text-sm text-white/60">No posts yet. Publish your first sub-contract above.</p>
                            <?php endif; ?>
                            <?php foreach (array_slice($myPosts, 0, 16) as $post): ?>
                                <?php $detailsPage = 'peer_post_details.php?post_id=' . (int) $post['id']; ?>
                                <article class="chip rounded-xl p-3">
                                    <p class="font-semibold text-sm"><?php echo e((string) ($post['title'] ?? 'Untitled')); ?></p>
                                    <div class="text-xs text-white/60 mt-1 flex flex-wrap gap-2">
                                        <span><?php echo e((string) date('M d, Y', strtotime((string) ($post['created_at'] ?? 'now')))); ?></span>
                                        <span><?php echo e((string) strtoupper((string) ($post['payout_model'] ?? 'revenue_share'))); ?></span>
                                    </div>
                                    <a href="<?php echo e($detailsPage); ?>" class="inline-block mt-3 liquid-glass rounded-full px-4 py-2 text-xs font-semibold">View Details</a>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </article>
                </section>

                <section class="hire-card rounded-3xl p-5 md:p-6">
                    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                        <h3 class="text-2xl font-heading italic">Marketplace Opportunities</h3>
                        <p class="text-sm text-white/60">Posts from other freelancers in TalentSync</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                        <?php if (!$marketPosts): ?>
                            <article class="chip rounded-2xl p-6 col-span-full text-center">
                                <p class="text-white/70 text-lg">No opportunities available yet.</p>
                                <p class="text-white/55 text-sm mt-1">Once others publish, they will appear here for collaboration.</p>
                            </article>
                        <?php endif; ?>

                        <?php foreach ($marketPosts as $post): ?>
                            <?php
                            $title = (string) ($post['title'] ?? 'Untitled Opportunity');
                            $poster = (string) ($post['poster_name'] ?? 'Freelancer');
                            $company = trim((string) ($post['company_name'] ?? '')) ?: $poster;
                            $desc = trim((string) ($post['description'] ?? ''));
                            $descShort = mb_strlen($desc) > 140 ? mb_substr($desc, 0, 140) . '...' : $desc;
                            $payoutModel = (string) ($post['payout_model'] ?? 'revenue_share');
                            $share = (int) ($post['share_percent'] ?? 0);
                            $payoutLabel = $payoutModel === 'revenue_share' ? ('Share ' . ($share > 0 ? $share : 0) . '%') : strtoupper($payoutModel);
                            $location = trim((string) ($post['location'] ?? '')) ?: ucfirst((string) ($post['work_mode'] ?? 'remote'));
                            $detailsUrl = 'aggregator.php?action=track_click&target=details&post_id=' . (int) $post['id'];
                            $applyUrl = 'aggregator.php?action=track_click&target=apply&post_id=' . (int) $post['id'];
                            ?>
                            <article class="chip rounded-2xl p-4">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <p class="text-[11px] uppercase tracking-wide text-white/55">Posted by <?php echo e($poster); ?></p>
                                        <h4 class="font-semibold mt-1 leading-tight"><?php echo e($title); ?></h4>
                                    </div>
                                    <span class="chip rounded-full px-2.5 py-1 text-[10px]"><?php echo e($payoutLabel); ?></span>
                                </div>
                                <p class="text-sm text-white/70 mt-2"><?php echo e($company); ?></p>
                                <p class="text-xs text-white/60 mt-1"><?php echo e($location); ?></p>
                                <p class="text-sm text-white/75 mt-3 min-h-[64px]"><?php echo e($descShort); ?></p>
                                <div class="mt-4 flex items-center gap-2">
                                    <a href="<?php echo e($detailsUrl); ?>" class="liquid-glass rounded-full px-4 py-2 text-xs font-semibold">View Details</a>
                                    <a href="<?php echo e($applyUrl); ?>" class="liquid-glass-strong rounded-full px-4 py-2 text-xs font-semibold">Apply / Chat</a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            </main>
        </div>
    </div>
</body>
</html>
