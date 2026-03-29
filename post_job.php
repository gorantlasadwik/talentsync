<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/provider_topbar.php';

requireRole('provider');

$message = '';
$statusType = '';
$formState = [
    'title' => '',
    'description' => '',
    'location' => '',
    'budget' => '',
    'payout_type' => 'monthly',
    'work_mode' => 'onsite',
    'experience_level' => 'mid',
    'application_deadline' => '',
];

try {
    $pdo->exec("ALTER TABLE jobs ADD COLUMN payout_type VARCHAR(40) DEFAULT NULL");
} catch (Throwable $e) {
    // Ignore if the column already exists.
}
try {
    $pdo->exec("ALTER TABLE jobs ADD COLUMN work_mode VARCHAR(40) DEFAULT NULL");
} catch (Throwable $e) {
    // Ignore if the column already exists.
}
try {
    $pdo->exec("ALTER TABLE jobs ADD COLUMN experience_level VARCHAR(40) DEFAULT NULL");
} catch (Throwable $e) {
    // Ignore if the column already exists.
}
try {
    $pdo->exec("ALTER TABLE jobs ADD COLUMN application_deadline DATE DEFAULT NULL");
} catch (Throwable $e) {
    // Ignore if the column already exists.
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formState['title'] = trim((string) ($_POST['title'] ?? ''));
    $formState['description'] = trim((string) ($_POST['description'] ?? ''));
    $formState['location'] = trim((string) ($_POST['location'] ?? ''));
    $formState['budget'] = trim((string) ($_POST['budget'] ?? ''));
    $formState['payout_type'] = trim((string) ($_POST['payout_type'] ?? ''));
    $formState['work_mode'] = trim((string) ($_POST['work_mode'] ?? ''));
    $formState['experience_level'] = trim((string) ($_POST['experience_level'] ?? ''));
    $formState['application_deadline'] = trim((string) ($_POST['application_deadline'] ?? ''));

    $allowedPayout = ['hourly', 'fixed', 'monthly', 'weekly'];
    $allowedMode = ['remote', 'hybrid', 'onsite'];
    $allowedLevel = ['junior', 'mid', 'senior'];

    if (!in_array($formState['payout_type'], $allowedPayout, true)) {
        $formState['payout_type'] = 'monthly';
    }
    if (!in_array($formState['work_mode'], $allowedMode, true)) {
        $formState['work_mode'] = 'onsite';
    }
    if (!in_array($formState['experience_level'], $allowedLevel, true)) {
        $formState['experience_level'] = 'mid';
    }
    if ($formState['application_deadline'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $formState['application_deadline'])) {
        $formState['application_deadline'] = '';
    }

    if ($formState['title'] === '') {
        $message = 'Title is required.';
        $statusType = 'error';
    } else {
        $stmt = $pdo->prepare('INSERT INTO jobs (provider_id, title, description, source, location, budget, payout_type, work_mode, experience_level, application_deadline) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            currentUserId(),
            $formState['title'],
            $formState['description'],
            'internal',
            $formState['location'],
            $formState['budget'],
            $formState['payout_type'],
            $formState['work_mode'],
            $formState['experience_level'],
            $formState['application_deadline'] !== '' ? $formState['application_deadline'] : null,
        ]);
        $message = 'Job posted successfully.';
        $statusType = 'success';
        $formState = [
            'title' => '',
            'description' => '',
            'location' => '',
            'budget' => '',
            'payout_type' => 'monthly',
            'work_mode' => 'onsite',
            'experience_level' => 'mid',
            'application_deadline' => '',
        ];
    }
}

$statsStmt = $pdo->prepare(
    'SELECT
        COUNT(*) AS total_jobs,
        SUM(CASE WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS jobs_this_week,
        SUM(CASE WHEN work_mode = "remote" THEN 1 ELSE 0 END) AS remote_jobs
     FROM jobs
     WHERE provider_id = ?'
);
$statsStmt->execute([currentUserId()]);
$postStats = $statsStmt->fetch() ?: ['total_jobs' => 0, 'jobs_this_week' => 0, 'remote_jobs' => 0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post Job | Provider Hub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        html,
        body {
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        body::-webkit-scrollbar {
            width: 0;
            height: 0;
            background: transparent;
        }

        body {
            font-family: 'Manrope', sans-serif;
            background:
                radial-gradient(720px 280px at 8% -8%, rgba(126, 235, 238, 0.10), transparent 56%),
                radial-gradient(680px 260px at 96% -4%, rgba(246, 149, 103, 0.08), transparent 60%),
                #05070b;
        }

        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 450, 'GRAD' 0, 'opsz' 24;
        }

        .panel-shell {
            background: linear-gradient(150deg, rgba(255, 255, 255, 0.10), rgba(255, 255, 255, 0.04));
            border: 1px solid rgba(255, 255, 255, 0.14);
            backdrop-filter: blur(16px);
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.28);
        }

        .hero-shell {
            background:
                radial-gradient(560px 240px at 8% 0%, rgba(121, 245, 247, 0.18), transparent 52%),
                linear-gradient(145deg, rgba(255, 255, 255, 0.14), rgba(255, 255, 255, 0.04));
            border: 1px solid rgba(255, 255, 255, 0.15);
        }

        .pill-chip {
            border-radius: 999px;
            border: 1px solid rgba(255, 255, 255, 0.18);
            background: rgba(255, 255, 255, 0.10);
            color: rgba(255, 255, 255, 0.84);
            font-size: 0.7rem;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            font-weight: 700;
            padding: 0.38rem 0.74rem;
        }

        .job-label {
            display: block;
            color: rgba(255, 255, 255, 0.64);
            font-size: 0.72rem;
            letter-spacing: 0.07em;
            text-transform: uppercase;
            font-weight: 700;
            margin-bottom: 0.45rem;
        }

        .job-input,
        .job-select,
        .job-textarea {
            width: 100%;
            border-radius: 1rem;
            border: 1px solid rgba(255, 255, 255, 0.14);
            background: rgba(255, 255, 255, 0.06);
            color: #fff;
            padding: 0.78rem 0.95rem;
            transition: border-color .18s ease, box-shadow .18s ease, background .18s ease;
        }

        .job-input::placeholder,
        .job-textarea::placeholder {
            color: rgba(255, 255, 255, 0.38);
        }

        .job-input:focus,
        .job-select:focus,
        .job-textarea:focus {
            outline: none;
            border-color: rgba(121, 245, 247, 0.55);
            background: rgba(255, 255, 255, 0.08);
            box-shadow: 0 0 0 3px rgba(121, 245, 247, 0.17);
        }

        .job-select option {
            background: #0e1218;
            color: #fff;
        }

        .job-input[type="date"] {
            color-scheme: dark;
            min-height: 3rem;
        }

        .job-input[type="date"]::-webkit-calendar-picker-indicator {
            cursor: pointer;
            border-radius: 0.65rem;
            padding: 0.28rem;
            background: rgba(255, 255, 255, 0.08);
            filter: invert(1) opacity(0.82);
            transition: filter .16s ease, background .16s ease;
        }

        .job-input[type="date"]::-webkit-calendar-picker-indicator:hover {
            background: rgba(121, 245, 247, 0.16);
            filter: invert(1) opacity(0.98);
        }

        .job-input[type="date"]::-webkit-datetime-edit {
            color: rgba(255, 255, 255, 0.92);
        }

        .job-input[type="date"]::-webkit-date-and-time-value {
            text-align: left;
        }

        .form-block {
            border-radius: 1.4rem;
            border: 1px solid rgba(255, 255, 255, 0.12);
            background: rgba(255, 255, 255, 0.04);
            padding: 1rem;
        }

        .publish-btn {
            border-radius: 1rem;
            background: linear-gradient(135deg, rgba(121, 245, 247, 0.95), rgba(111, 217, 255, 0.94));
            color: #032026;
            font-weight: 800;
            letter-spacing: 0.03em;
            transition: transform .16s ease, filter .16s ease;
        }

        .publish-btn:hover {
            filter: brightness(1.04);
            transform: translateY(-1px);
        }

        .publish-btn:active {
            transform: translateY(0);
        }

        .status-chip {
            border-radius: 0.9rem;
            padding: 0.7rem 0.9rem;
            font-size: 0.86rem;
            font-weight: 600;
        }

        .status-chip.success {
            border: 1px solid rgba(121, 245, 247, 0.40);
            background: rgba(121, 245, 247, 0.15);
            color: #bbfbff;
        }

        .status-chip.error {
            border: 1px solid rgba(255, 134, 134, 0.4);
            background: rgba(255, 134, 134, 0.12);
            color: #ffd7d7;
        }

        @media (max-width: 1023px) {
            .mobile-side-links a {
                display: inline-flex;
                align-items: center;
                gap: 0.35rem;
                border-radius: 999px;
                border: 1px solid rgba(255, 255, 255, 0.16);
                background: rgba(255, 255, 255, 0.09);
                padding: 0.45rem 0.8rem;
                color: rgba(255, 255, 255, 0.86);
                font-size: 0.76rem;
                font-weight: 700;
                white-space: nowrap;
            }
        }
    </style>
</head>
<body class="bg-[#05070b] text-white min-h-screen">
    <?php renderProviderTopbar('hub', false); ?>

    <div class="flex pt-20 min-h-screen">
        <aside class="hidden lg:flex fixed left-0 top-20 bottom-0 flex-col p-6 overflow-y-auto bg-[#090b0f] h-screen w-72 rounded-r-3xl border-r border-white/10 z-40">
            <div class="mb-8">
                <div class="flex items-center gap-3 mb-2">
                    <span class="text-lg font-bold text-white">Hiring Tools</span>
                    <span class="material-symbols-outlined text-white/70" style="font-size:18px;">dashboard_customize</span>
                </div>
                <p class="text-xs text-white/50">Craft a polished brief that attracts high-signal talent.</p>
            </div>

            <nav class="flex flex-col gap-1 flex-1 text-sm">
                <a class="flex items-center gap-3 px-4 py-3 bg-white/10 text-white rounded-xl font-bold" href="post_job.php">
                    <span class="material-symbols-outlined">add_circle</span>
                    Post New Job
                </a>
                <a class="flex items-center gap-3 px-4 py-3 text-white/70 hover:bg-white/10 rounded-xl transition-all" href="provider_dashboard.php">
                    <span class="material-symbols-outlined">groups</span>
                    Provider Dashboard
                </a>
                <a class="flex items-center gap-3 px-4 py-3 text-white/70 hover:bg-white/10 rounded-xl transition-all" href="hiring_board.php">
                    <span class="material-symbols-outlined">event_note</span>
                    Hiring Board
                </a>
                <a class="flex items-center gap-3 px-4 py-3 text-white/70 hover:bg-white/10 rounded-xl transition-all" href="company_pipeline.php?provider_id=<?php echo (int) currentUserId(); ?>">
                    <span class="material-symbols-outlined">account_tree</span>
                    Company Pipeline
                </a>
                <a class="flex items-center gap-3 px-4 py-3 text-white/70 hover:bg-white/10 rounded-xl transition-all" href="provider_location.php">
                    <span class="material-symbols-outlined">location_on</span>
                    Company Location
                </a>
                <a class="flex items-center gap-3 px-4 py-3 text-white/70 hover:bg-white/10 rounded-xl transition-all" href="chat.php">
                    <span class="material-symbols-outlined">chat</span>
                    Messages
                </a>
            </nav>

            <div class="mt-8 pt-6 border-t border-white/10 space-y-3 text-xs text-white/65">
                <div class="panel-shell rounded-2xl p-3">
                    <p class="uppercase tracking-wider text-[10px] text-white/45">Posting cadence</p>
                    <p class="text-sm font-semibold mt-1"><?php echo (int) ($postStats['jobs_this_week'] ?? 0); ?> jobs this week</p>
                </div>
                <div class="panel-shell rounded-2xl p-3">
                    <p class="uppercase tracking-wider text-[10px] text-white/45">Remote reach</p>
                    <p class="text-sm font-semibold mt-1"><?php echo (int) ($postStats['remote_jobs'] ?? 0); ?> remote roles active</p>
                </div>
            </div>
        </aside>

        <main class="lg:ml-72 flex-1 p-4 md:p-6 lg:p-8">
            <div class="max-w-7xl mx-auto space-y-6">
                <div class="mobile-side-links lg:hidden overflow-x-auto pb-1">
                    <div class="flex items-center gap-2 min-w-max">
                        <a href="post_job.php"><span class="material-symbols-outlined" style="font-size:14px;">add_circle</span>Post Job</a>
                        <a href="provider_dashboard.php"><span class="material-symbols-outlined" style="font-size:14px;">groups</span>Dashboard</a>
                        <a href="hiring_board.php"><span class="material-symbols-outlined" style="font-size:14px;">event_note</span>Hiring Board</a>
                        <a href="provider_location.php"><span class="material-symbols-outlined" style="font-size:14px;">location_on</span>Location</a>
                        <a href="chat.php"><span class="material-symbols-outlined" style="font-size:14px;">chat</span>Chat</a>
                    </div>
                </div>

                <section class="hero-shell rounded-3xl p-6 md:p-7">
                    <div class="flex flex-col xl:flex-row xl:items-end xl:justify-between gap-5">
                        <div>
                            <div class="pill-chip w-fit mb-3">Curated Hiring Canvas</div>
                            <h1 class="text-3xl md:text-4xl font-extrabold tracking-tight">Create a Job Brief That Gets Better Applicants</h1>
                            <p class="text-white/65 mt-2 max-w-3xl">Inspired by your Stitch references: softer surfaces, rounded cards, editorial spacing, and premium action hierarchy aligned to your provider theme.</p>
                        </div>
                        <div class="grid grid-cols-3 gap-3 min-w-[270px]">
                            <div class="panel-shell rounded-2xl p-3 text-center">
                                <p class="text-[10px] uppercase tracking-wider text-white/45">Total Posts</p>
                                <p class="text-xl font-extrabold mt-1"><?php echo (int) ($postStats['total_jobs'] ?? 0); ?></p>
                            </div>
                            <div class="panel-shell rounded-2xl p-3 text-center">
                                <p class="text-[10px] uppercase tracking-wider text-white/45">This Week</p>
                                <p class="text-xl font-extrabold mt-1"><?php echo (int) ($postStats['jobs_this_week'] ?? 0); ?></p>
                            </div>
                            <div class="panel-shell rounded-2xl p-3 text-center">
                                <p class="text-[10px] uppercase tracking-wider text-white/45">Remote</p>
                                <p class="text-xl font-extrabold mt-1"><?php echo (int) ($postStats['remote_jobs'] ?? 0); ?></p>
                            </div>
                        </div>
                    </div>
                </section>

                <div class="grid grid-cols-1 xl:grid-cols-12 gap-6">
                    <section class="xl:col-span-8 panel-shell rounded-3xl p-5 md:p-6">
                        <div class="flex items-center justify-between gap-3 mb-4">
                            <h2 class="text-2xl font-bold tracking-tight">Role Details</h2>
                            <span class="pill-chip">Provider Workflow</span>
                        </div>

                        <?php if ($message): ?>
                            <div class="status-chip <?php echo $statusType === 'success' ? 'success' : 'error'; ?> mb-4">
                                <?php echo e($message); ?>
                            </div>
                        <?php endif; ?>

                        <form method="post" class="space-y-4" id="postJobForm">
                            <div class="form-block">
                                <label class="job-label" for="titleInput">Role title</label>
                                <input id="titleInput" class="job-input" type="text" name="title" placeholder="Senior Full Stack Developer" value="<?php echo e((string) $formState['title']); ?>" required>
                            </div>

                            <div class="form-block">
                                <label class="job-label" for="descriptionInput">Role description</label>
                                <textarea id="descriptionInput" class="job-textarea" name="description" rows="5" placeholder="Describe scope, deliverables, expected collaboration style and tools."><?php echo e((string) $formState['description']); ?></textarea>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="form-block">
                                    <label class="job-label" for="locationInput">Location</label>
                                    <input id="locationInput" class="job-input" type="text" name="location" placeholder="Bengaluru / Remote" value="<?php echo e((string) $formState['location']); ?>">
                                </div>
                                <div class="form-block">
                                    <label class="job-label" for="budgetInput">Budget range</label>
                                    <input id="budgetInput" class="job-input" type="text" name="budget" placeholder="INR 1,20,000 - 1,80,000" value="<?php echo e((string) $formState['budget']); ?>">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="form-block">
                                    <label class="job-label" for="payoutSelect">Payout type</label>
                                    <select id="payoutSelect" class="job-select" name="payout_type">
                                        <option value="monthly" <?php echo $formState['payout_type'] === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                        <option value="hourly" <?php echo $formState['payout_type'] === 'hourly' ? 'selected' : ''; ?>>Hourly</option>
                                        <option value="fixed" <?php echo $formState['payout_type'] === 'fixed' ? 'selected' : ''; ?>>Fixed</option>
                                        <option value="weekly" <?php echo $formState['payout_type'] === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                                    </select>
                                </div>

                                <div class="form-block">
                                    <label class="job-label" for="modeSelect">Work mode</label>
                                    <select id="modeSelect" class="job-select" name="work_mode">
                                        <option value="onsite" <?php echo $formState['work_mode'] === 'onsite' ? 'selected' : ''; ?>>On-site</option>
                                        <option value="remote" <?php echo $formState['work_mode'] === 'remote' ? 'selected' : ''; ?>>Remote</option>
                                        <option value="hybrid" <?php echo $formState['work_mode'] === 'hybrid' ? 'selected' : ''; ?>>Hybrid</option>
                                    </select>
                                </div>

                                <div class="form-block">
                                    <label class="job-label" for="levelSelect">Experience level</label>
                                    <select id="levelSelect" class="job-select" name="experience_level">
                                        <option value="junior" <?php echo $formState['experience_level'] === 'junior' ? 'selected' : ''; ?>>Junior</option>
                                        <option value="mid" <?php echo $formState['experience_level'] === 'mid' ? 'selected' : ''; ?>>Mid</option>
                                        <option value="senior" <?php echo $formState['experience_level'] === 'senior' ? 'selected' : ''; ?>>Senior</option>
                                    </select>
                                </div>

                                <div class="form-block">
                                    <label class="job-label" for="deadlineInput">Application deadline</label>
                                    <input id="deadlineInput" class="job-input" type="date" name="application_deadline" value="<?php echo e((string) $formState['application_deadline']); ?>">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                                <button class="publish-btn w-full px-7 py-3" type="submit">Publish Job</button>
                                <a class="w-full px-6 py-3 rounded-2xl border border-white/15 text-white/80 hover:bg-white/10 text-center font-semibold" href="provider_dashboard.php">Back to dashboard</a>
                            </div>
                        </form>
                    </section>

                    <aside class="xl:col-span-4 space-y-4">
                        <div class="panel-shell rounded-3xl p-5 md:p-6 xl:sticky xl:top-24">
                            <h3 class="text-xl font-bold">Live Preview</h3>
                            <p class="text-sm text-white/60 mt-1">The card below updates while you type.</p>

                            <div class="mt-4 rounded-2xl p-4 bg-white/[0.04] border border-white/10 space-y-3">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <p id="previewTitle" class="font-bold text-lg leading-tight">Untitled role</p>
                                        <p id="previewMeta" class="text-sm text-white/65 mt-1">Set location and compensation</p>
                                    </div>
                                    <span id="previewMode" class="pill-chip">On-site</span>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <span id="previewLevel" class="pill-chip">Mid</span>
                                    <span id="previewPayout" class="pill-chip">Monthly</span>
                                    <span id="previewDeadline" class="pill-chip">No deadline</span>
                                </div>
                                <p id="previewDesc" class="text-sm text-white/70 leading-relaxed">Add description to surface role expectations and attract high quality applicants.</p>
                            </div>

                            <div class="grid grid-cols-2 gap-3 mt-4">
                                <div class="rounded-2xl bg-white/[0.04] border border-white/10 p-3">
                                    <p class="text-[10px] uppercase tracking-wider text-white/45">Suggestion</p>
                                    <p class="text-sm font-semibold mt-1">Use 5-7 bullet deliverables</p>
                                </div>
                                <div class="rounded-2xl bg-white/[0.04] border border-white/10 p-3">
                                    <p class="text-[10px] uppercase tracking-wider text-white/45">Signal</p>
                                    <p class="text-sm font-semibold mt-1">Mention tool stack + timeline</p>
                                </div>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </main>
    </div>

    <script>
        (function () {
            function text(id) {
                var node = document.getElementById(id);
                return node ? String(node.value || '').trim() : '';
            }

            function selectedText(id, fallback) {
                var node = document.getElementById(id);
                if (!node) {
                    return fallback;
                }
                var raw = node.options[node.selectedIndex] ? node.options[node.selectedIndex].text : fallback;
                return String(raw || fallback);
            }

            function previewUpdate() {
                var title = text('titleInput');
                var desc = text('descriptionInput');
                var location = text('locationInput');
                var budget = text('budgetInput');
                var mode = selectedText('modeSelect', 'On-site');
                var level = selectedText('levelSelect', 'Mid');
                var payout = selectedText('payoutSelect', 'Monthly');
                var deadline = text('deadlineInput');

                document.getElementById('previewTitle').textContent = title || 'Untitled role';
                document.getElementById('previewMeta').textContent = (location || 'Location TBD') + ' • ' + (budget || 'Budget TBD');
                document.getElementById('previewMode').textContent = mode;
                document.getElementById('previewLevel').textContent = level;
                document.getElementById('previewPayout').textContent = payout;
                document.getElementById('previewDeadline').textContent = deadline ? ('Deadline ' + deadline) : 'No deadline';
                document.getElementById('previewDesc').textContent = desc || 'Add description to surface role expectations and attract high quality applicants.';
            }

            ['titleInput', 'descriptionInput', 'locationInput', 'budgetInput', 'deadlineInput', 'modeSelect', 'levelSelect', 'payoutSelect'].forEach(function (id) {
                var node = document.getElementById(id);
                if (!node) {
                    return;
                }
                node.addEventListener('input', previewUpdate);
                node.addEventListener('change', previewUpdate);
            });

            previewUpdate();
        })();
    </script>
</body>
</html>
