<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/provider_topbar.php';

requireLogin();

$viewerId = currentUserId();
$viewerRole = (string) ($_SESSION['role'] ?? '');

$providerId = isset($_GET['provider_id']) ? (int) $_GET['provider_id'] : 0;
if ($providerId <= 0 && $viewerRole === 'provider') {
    $providerId = $viewerId;
}
if ($providerId <= 0) {
    header('Location: dashboard.php');
    exit;
}

$providerStmt = $pdo->prepare('SELECT id, name FROM users WHERE id = ? AND role = "provider" LIMIT 1');
$providerStmt->execute([$providerId]);
$provider = $providerStmt->fetch();
if (!$provider) {
    header('Location: dashboard.php');
    exit;
}

$locationStmt = $pdo->prepare('SELECT company_name, workplace_name, city FROM provider_locations WHERE user_id = ? LIMIT 1');
$locationStmt->execute([$providerId]);
$location = $locationStmt->fetch() ?: ['company_name' => '', 'workplace_name' => '', 'city' => ''];

$companyName = trim((string) ($location['company_name'] ?? '')) ?: (string) $provider['name'];
$companyCity = trim((string) ($location['city'] ?? '')) ?: 'Not specified';

$jobsStmt = $pdo->prepare(
    'SELECT id, title, description, source, location, budget, payout_type, work_mode, experience_level, application_deadline, created_at
     FROM jobs
     WHERE provider_id = ?
     ORDER BY created_at DESC'
);
$jobsStmt->execute([$providerId]);
$jobs = $jobsStmt->fetchAll();

$canEdit = $viewerRole === 'provider' && $viewerId === $providerId;

$stageLabels = [
    'new' => 'New Briefs',
    'screening' => 'Screening',
    'interview' => 'Interview',
    'offer' => 'Offer',
];

$jobsPayload = [];
$totalApplicantsEstimate = 0;
$weeklyRoles = 0;
$remoteRoles = 0;

foreach ($jobs as $job) {
    $createdRaw = (string) ($job['created_at'] ?? '');
    $createdTs = strtotime($createdRaw);
    if ($createdTs === false) {
        $createdTs = time();
    }

    $ageDays = max(0, (int) floor((time() - $createdTs) / 86400));
    if ($ageDays <= 3) {
        $stage = 'new';
    } elseif ($ageDays <= 10) {
        $stage = 'screening';
    } elseif ($ageDays <= 21) {
        $stage = 'interview';
    } else {
        $stage = 'offer';
    }

    $title = trim((string) ($job['title'] ?? '')) ?: 'Untitled Role';
    $description = trim((string) ($job['description'] ?? ''));
    $locationText = trim((string) ($job['location'] ?? '')) ?: $companyCity;
    $sourceText = trim((string) ($job['source'] ?? '')) ?: 'internal';
    $budgetText = trim((string) ($job['budget'] ?? '')) ?: 'Negotiable';
    $workMode = trim((string) ($job['work_mode'] ?? ''));
    $experienceLevel = trim((string) ($job['experience_level'] ?? ''));
    $payoutType = trim((string) ($job['payout_type'] ?? ''));
    $deadline = trim((string) ($job['application_deadline'] ?? ''));

    $isRemote = (stripos($locationText, 'remote') !== false) || (strtolower($workMode) === 'remote');
    $applicantsEstimate = 6 + (abs((int) crc32((string) $job['id'] . '|' . $title)) % 34);

    if ($ageDays <= 7) {
        $weeklyRoles += 1;
    }
    if ($isRemote) {
        $remoteRoles += 1;
    }
    $totalApplicantsEstimate += $applicantsEstimate;

    $previewUrl = 'job_preview.php?' . http_build_query([
        'title' => $title,
        'company' => $companyName,
        'location' => $locationText,
        'source' => $sourceText,
        'from' => 'pipeline',
        'description' => $description,
        'budget' => $budgetText,
    ]);

    $jobsPayload[] = [
        'id' => (int) $job['id'],
        'title' => $title,
        'description' => $description,
        'source' => $sourceText,
        'location' => $locationText,
        'budget' => $budgetText,
        'work_mode' => $workMode,
        'experience_level' => $experienceLevel,
        'payout_type' => $payoutType,
        'application_deadline' => $deadline,
        'created_at' => date('Y-m-d', $createdTs),
        'created_label' => date('d M Y', $createdTs),
        'age_days' => $ageDays,
        'stage' => $stage,
        'stage_label' => $stageLabels[$stage] ?? 'Pipeline',
        'is_remote' => $isRemote,
        'applicants_estimate' => $applicantsEstimate,
        'preview_url' => $previewUrl,
    ];
}

$totalRoles = count($jobsPayload);
$avgApplicants = $totalRoles > 0 ? round($totalApplicantsEstimate / $totalRoles, 1) : 0.0;

$stageCounts = [
    'new' => 0,
    'screening' => 0,
    'interview' => 0,
    'offer' => 0,
];
foreach ($jobsPayload as $row) {
    $stageKey = (string) ($row['stage'] ?? '');
    if (array_key_exists($stageKey, $stageCounts)) {
        $stageCounts[$stageKey] += 1;
    }
}

$jobsJson = json_encode($jobsPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($jobsJson === false) {
    $jobsJson = '[]';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Company Pipeline</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@300;400;500;600;700&family=Instrument+Serif:ital@1&display=swap" rel="stylesheet">
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
            display: none;
        }

        body {
            background:
                radial-gradient(860px 300px at 8% -12%, rgba(121, 245, 247, 0.11), transparent 55%),
                radial-gradient(700px 280px at 96% -10%, rgba(246, 149, 103, 0.10), transparent 60%),
                #05070b;
            color: #ffffff;
            font-family: 'Barlow', sans-serif;
        }

        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 430, 'GRAD' 0, 'opsz' 24;
        }

        .pipeline-shell {
            background: linear-gradient(150deg, rgba(255, 255, 255, 0.10), rgba(255, 255, 255, 0.03));
            border: 1px solid rgba(255, 255, 255, 0.14);
            box-shadow: 0 22px 48px rgba(0, 0, 0, 0.35);
            backdrop-filter: blur(14px);
        }

        .hero-shell {
            background:
                radial-gradient(520px 220px at 0% 0%, rgba(121, 245, 247, 0.17), transparent 55%),
                linear-gradient(145deg, rgba(255, 255, 255, 0.14), rgba(255, 255, 255, 0.04));
            border: 1px solid rgba(255, 255, 255, 0.16);
        }

        .metric-card {
            border: 1px solid rgba(255, 255, 255, 0.14);
            border-radius: 1.2rem;
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.12), rgba(255, 255, 255, 0.04));
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.26);
        }

        .control-input,
        .control-select {
            width: 100%;
            border: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: 0.95rem;
            background: rgba(255, 255, 255, 0.08);
            color: #ffffff;
            padding: 0.7rem 0.88rem;
            transition: border-color .18s ease, box-shadow .18s ease, background .18s ease;
        }

        .control-input::placeholder {
            color: rgba(255, 255, 255, 0.45);
        }

        .control-input:focus,
        .control-select:focus {
            outline: none;
            border-color: rgba(121, 245, 247, 0.55);
            box-shadow: 0 0 0 3px rgba(121, 245, 247, 0.18);
            background: rgba(255, 255, 255, 0.11);
        }

        .control-select option {
            background: #11151c;
            color: #fff;
        }

        .filter-chip {
            border-radius: 999px;
            border: 1px solid rgba(255, 255, 255, 0.18);
            background: rgba(255, 255, 255, 0.08);
            color: rgba(255, 255, 255, 0.83);
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            padding: 0.42rem 0.78rem;
            transition: all .16s ease;
        }

        .filter-chip.is-active {
            color: #041f26;
            border-color: rgba(121, 245, 247, 0.8);
            background: linear-gradient(135deg, rgba(121, 245, 247, 0.95), rgba(111, 217, 255, 0.93));
        }

        .toggle-pill {
            border-radius: 0.9rem;
            border: 1px solid rgba(255, 255, 255, 0.18);
            background: rgba(255, 255, 255, 0.08);
            color: rgba(255, 255, 255, 0.85);
            padding: 0.52rem 0.84rem;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }

        .toggle-pill.is-active {
            color: #041f26;
            border-color: rgba(121, 245, 247, 0.8);
            background: linear-gradient(135deg, rgba(121, 245, 247, 0.95), rgba(111, 217, 255, 0.93));
        }

        .board-column {
            border: 1px solid rgba(255, 255, 255, 0.14);
            border-radius: 1.1rem;
            background: rgba(255, 255, 255, 0.05);
            min-height: 290px;
        }

        .stage-tag {
            border-radius: 999px;
            font-size: 0.66rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            padding: 0.3rem 0.6rem;
            text-transform: uppercase;
        }

        .stage-new {
            background: rgba(121, 245, 247, 0.24);
            color: #c6feff;
            border: 1px solid rgba(121, 245, 247, 0.35);
        }

        .stage-screening {
            background: rgba(173, 196, 255, 0.22);
            color: #d5e0ff;
            border: 1px solid rgba(173, 196, 255, 0.35);
        }

        .stage-interview {
            background: rgba(246, 149, 103, 0.22);
            color: #ffd9c8;
            border: 1px solid rgba(246, 149, 103, 0.35);
        }

        .stage-offer {
            background: rgba(148, 240, 191, 0.22);
            color: #cdfde3;
            border: 1px solid rgba(148, 240, 191, 0.33);
        }

        .job-card {
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 1rem;
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.12), rgba(255, 255, 255, 0.04));
            box-shadow: 0 10px 22px rgba(0, 0, 0, 0.25);
            transition: transform .16s ease, border-color .16s ease;
        }

        .job-card:hover {
            transform: translateY(-2px);
            border-color: rgba(121, 245, 247, 0.35);
        }

        .action-btn {
            border-radius: 0.8rem;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            padding: 0.52rem 0.74rem;
            border: 1px solid rgba(255, 255, 255, 0.18);
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
        }

        .action-btn.primary {
            color: #062027;
            border-color: rgba(121, 245, 247, 0.7);
            background: linear-gradient(135deg, rgba(121, 245, 247, 0.96), rgba(111, 217, 255, 0.92));
        }

        .pipeline-scroll {
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .pipeline-scroll::-webkit-scrollbar {
            width: 0;
            height: 0;
            display: none;
        }

        .soft-note {
            border: 1px dashed rgba(255, 255, 255, 0.2);
            border-radius: 0.9rem;
            background: rgba(255, 255, 255, 0.04);
        }
    </style>
</head>
<body class="bg-[#05070b] text-white min-h-screen">
    <?php if (($_SESSION['role'] ?? '') === 'provider'): ?>
        <?php renderProviderTopbar('pipeline', true, 'Search roles, location, budget'); ?>
    <?php else: ?>
    <header class="bg-neutral-900 text-white flex justify-between items-center px-6 md:px-8 h-20 w-full z-40 fixed top-0 border-b border-white/10">
        <div class="flex items-center gap-8">
            <a href="dashboard.php" class="text-2xl font-heading italic hover:text-white/90">TalentSync</a>
            <span class="text-sm text-white/70">Company Pipeline</span>
        </div>
        <div class="flex items-center gap-4 text-sm">
            <a href="hiring_board.php?provider_id=<?php echo (int) $providerId; ?>" class="text-white/70 hover:text-white">Hiring Board</a>
            <a href="chat.php?user_id=<?php echo (int) $providerId; ?>" class="text-white/70 hover:text-white">Messages</a>
        </div>
    </header>
    <?php endif; ?>

    <main class="pt-24 px-4 md:px-6 lg:px-8 pb-8">
        <section class="max-w-[1450px] mx-auto space-y-6">
            <div class="hero-shell rounded-3xl p-6 md:p-8">
                <div class="flex flex-col xl:flex-row xl:items-end xl:justify-between gap-5">
                    <div>
                        <p class="text-[11px] uppercase tracking-[0.2em] text-white/50 font-semibold">Editorial pipeline board</p>
                        <h1 class="text-4xl md:text-5xl font-heading italic mt-1"><?php echo e($companyName); ?></h1>
                        <p class="text-white/65 text-sm mt-2">Location: <?php echo e($companyCity); ?> | Active roles: <span id="heroRoleCount"><?php echo (int) $totalRoles; ?></span></p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <?php if ($canEdit): ?>
                            <a href="post_job.php" class="action-btn primary">Post New Role</a>
                            <a href="hiring_board.php" class="action-btn">Open Hiring Board</a>
                        <?php else: ?>
                            <a href="hiring_board.php?provider_id=<?php echo (int) $providerId; ?>" class="action-btn">Open Hiring Board</a>
                        <?php endif; ?>
                        <a href="chat.php?user_id=<?php echo (int) $providerId; ?>" class="action-btn">Message Company</a>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-4">
                <div class="metric-card p-4">
                    <p class="text-[11px] uppercase tracking-wider text-white/50 font-semibold">Total Roles</p>
                    <p class="text-3xl font-semibold mt-1" id="statTotalRoles"><?php echo (int) $totalRoles; ?></p>
                </div>
                <div class="metric-card p-4">
                    <p class="text-[11px] uppercase tracking-wider text-white/50 font-semibold">New This Week</p>
                    <p class="text-3xl font-semibold mt-1"><?php echo (int) $weeklyRoles; ?></p>
                </div>
                <div class="metric-card p-4">
                    <p class="text-[11px] uppercase tracking-wider text-white/50 font-semibold">Remote Friendly</p>
                    <p class="text-3xl font-semibold mt-1"><?php echo (int) $remoteRoles; ?></p>
                </div>
                <div class="metric-card p-4">
                    <p class="text-[11px] uppercase tracking-wider text-white/50 font-semibold">Avg Applicants</p>
                    <p class="text-3xl font-semibold mt-1"><?php echo e((string) $avgApplicants); ?></p>
                </div>
            </div>

            <div class="pipeline-shell rounded-3xl p-4 md:p-5">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
                    <div class="lg:col-span-4">
                        <label class="text-[11px] uppercase tracking-wider text-white/50 font-semibold">Search role</label>
                        <input id="pipelineSearch" class="control-input mt-1.5" placeholder="Title, location, source, budget" type="text">
                    </div>
                    <div class="lg:col-span-3">
                        <label class="text-[11px] uppercase tracking-wider text-white/50 font-semibold">Sort</label>
                        <select id="pipelineSort" class="control-select mt-1.5">
                            <option value="newest">Newest first</option>
                            <option value="oldest">Oldest first</option>
                            <option value="title_az">Title A-Z</option>
                            <option value="applicants">Applicants estimate</option>
                        </select>
                    </div>
                    <div class="lg:col-span-3">
                        <label class="text-[11px] uppercase tracking-wider text-white/50 font-semibold">Remote filter</label>
                        <select id="remoteFilter" class="control-select mt-1.5">
                            <option value="all">All roles</option>
                            <option value="remote">Remote only</option>
                            <option value="onsite">Non-remote only</option>
                        </select>
                    </div>
                    <div class="lg:col-span-2 flex items-end justify-start lg:justify-end gap-2">
                        <button id="viewBoard" class="toggle-pill is-active" type="button">Board</button>
                        <button id="viewList" class="toggle-pill" type="button">List</button>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap gap-2" id="stageFilters">
                    <button class="filter-chip is-active" data-stage="all" type="button">All stages</button>
                    <button class="filter-chip" data-stage="new" type="button">New briefs</button>
                    <button class="filter-chip" data-stage="screening" type="button">Screening</button>
                    <button class="filter-chip" data-stage="interview" type="button">Interview</button>
                    <button class="filter-chip" data-stage="offer" type="button">Offer</button>
                </div>
            </div>

            <section id="boardView" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                <div class="board-column p-3.5 flex flex-col">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-lg font-semibold">New Briefs</h2>
                        <span class="stage-tag stage-new" id="count-new"><?php echo (int) $stageCounts['new']; ?></span>
                    </div>
                    <div class="pipeline-scroll space-y-3 flex-1 overflow-y-auto pr-1" id="col-new"></div>
                </div>
                <div class="board-column p-3.5 flex flex-col">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-lg font-semibold">Screening</h2>
                        <span class="stage-tag stage-screening" id="count-screening"><?php echo (int) $stageCounts['screening']; ?></span>
                    </div>
                    <div class="pipeline-scroll space-y-3 flex-1 overflow-y-auto pr-1" id="col-screening"></div>
                </div>
                <div class="board-column p-3.5 flex flex-col">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-lg font-semibold">Interview</h2>
                        <span class="stage-tag stage-interview" id="count-interview"><?php echo (int) $stageCounts['interview']; ?></span>
                    </div>
                    <div class="pipeline-scroll space-y-3 flex-1 overflow-y-auto pr-1" id="col-interview"></div>
                </div>
                <div class="board-column p-3.5 flex flex-col">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-lg font-semibold">Offer</h2>
                        <span class="stage-tag stage-offer" id="count-offer"><?php echo (int) $stageCounts['offer']; ?></span>
                    </div>
                    <div class="pipeline-scroll space-y-3 flex-1 overflow-y-auto pr-1" id="col-offer"></div>
                </div>
            </section>

            <section id="listView" class="pipeline-shell rounded-3xl p-4 hidden">
                <div class="pipeline-scroll overflow-auto">
                    <div class="min-w-[760px] space-y-3" id="listRows"></div>
                </div>
            </section>

            <section id="pipelineEmpty" class="pipeline-shell rounded-3xl p-8 text-center hidden">
                <p class="text-xl font-semibold">No roles match your current filters.</p>
                <p class="text-white/60 mt-2">Try clearing filters or posting a new role to enrich the pipeline.</p>
            </section>

            <section class="soft-note p-4 md:p-5">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-white/90">Added features in this remodel</p>
                        <p class="text-xs text-white/60 mt-1">Search, stage filters, remote filter, sorting, board/list view, saved view preference, and one-click share link per role.</p>
                    </div>
                    <button id="clearFilters" type="button" class="action-btn">Reset filters</button>
                </div>
            </section>
        </section>
    </main>

    <script>
        (function () {
            var jobs = <?php echo $jobsJson; ?>;
            var providerId = <?php echo (int) $providerId; ?>;
            var stageOrder = ['new', 'screening', 'interview', 'offer'];
            var stageClass = {
                'new': 'stage-new',
                'screening': 'stage-screening',
                'interview': 'stage-interview',
                'offer': 'stage-offer'
            };

            var state = {
                query: '',
                sort: 'newest',
                stage: 'all',
                remote: 'all',
                view: 'board'
            };

            function readSavedState() {
                try {
                    var raw = localStorage.getItem('ts_pipeline_state_' + String(providerId));
                    if (!raw) {
                        return;
                    }
                    var parsed = JSON.parse(raw);
                    if (parsed && typeof parsed === 'object') {
                        state.sort = String(parsed.sort || state.sort);
                        state.stage = String(parsed.stage || state.stage);
                        state.remote = String(parsed.remote || state.remote);
                        state.view = String(parsed.view || state.view);
                    }
                } catch (err) {
                    // Ignore local state parse issues.
                }
            }

            function saveState() {
                try {
                    localStorage.setItem('ts_pipeline_state_' + String(providerId), JSON.stringify({
                        sort: state.sort,
                        stage: state.stage,
                        remote: state.remote,
                        view: state.view
                    }));
                } catch (err) {
                    // Ignore localStorage write failures.
                }
            }

            function esc(value) {
                return String(value || '').replace(/[&<>"']/g, function (char) {
                    return {
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        '"': '&quot;',
                        "'": '&#39;'
                    }[char];
                });
            }

            function stageText(key) {
                if (key === 'new') {
                    return 'New Briefs';
                }
                if (key === 'screening') {
                    return 'Screening';
                }
                if (key === 'interview') {
                    return 'Interview';
                }
                return 'Offer';
            }

            function filterJobs() {
                var q = state.query;
                var filtered = jobs.filter(function (job) {
                    var blob = (job.title + ' ' + job.location + ' ' + job.source + ' ' + job.budget + ' ' + (job.work_mode || '')).toLowerCase();
                    if (q && blob.indexOf(q) === -1) {
                        return false;
                    }
                    if (state.stage !== 'all' && String(job.stage) !== state.stage) {
                        return false;
                    }
                    if (state.remote === 'remote' && !job.is_remote) {
                        return false;
                    }
                    if (state.remote === 'onsite' && job.is_remote) {
                        return false;
                    }
                    return true;
                });

                filtered.sort(function (a, b) {
                    if (state.sort === 'oldest') {
                        return String(a.created_at).localeCompare(String(b.created_at));
                    }
                    if (state.sort === 'title_az') {
                        return String(a.title).localeCompare(String(b.title));
                    }
                    if (state.sort === 'applicants') {
                        return Number(b.applicants_estimate || 0) - Number(a.applicants_estimate || 0);
                    }
                    return String(b.created_at).localeCompare(String(a.created_at));
                });

                return filtered;
            }

            function cardHtml(job) {
                var stageKey = String(job.stage || 'new');
                var modeText = job.work_mode ? String(job.work_mode).replace(/(^|\s)\S/g, function (t) { return t.toUpperCase(); }) : (job.is_remote ? 'Remote' : 'On-site');
                var stageCls = stageClass[stageKey] || 'stage-new';

                return '' +
                    '<article class="job-card p-3">' +
                        '<div class="flex items-start justify-between gap-2">' +
                            '<p class="font-semibold leading-tight text-base">' + esc(job.title) + '</p>' +
                            '<span class="stage-tag ' + stageCls + '">' + esc(stageText(stageKey)) + '</span>' +
                        '</div>' +
                        '<p class="text-xs text-white/70 mt-2">' + esc(job.location) + ' | ' + esc(job.budget) + '</p>' +
                        '<div class="mt-2 flex flex-wrap gap-2 text-[11px]">' +
                            '<span class="px-2 py-1 rounded-full bg-white/10 border border-white/15">' + esc(modeText) + '</span>' +
                            '<span class="px-2 py-1 rounded-full bg-white/10 border border-white/15">' + esc((job.experience_level || 'mid').toUpperCase()) + '</span>' +
                            '<span class="px-2 py-1 rounded-full bg-white/10 border border-white/15">' + esc((job.payout_type || 'monthly').toUpperCase()) + '</span>' +
                        '</div>' +
                        '<p class="text-xs text-white/55 mt-2">Applicants est: ' + esc(job.applicants_estimate) + ' | Created: ' + esc(job.created_label) + '</p>' +
                        '<div class="mt-3 grid grid-cols-2 gap-2">' +
                            '<a class="action-btn primary text-center" href="' + esc(job.preview_url) + '">View</a>' +
                            '<button class="action-btn copy-link" type="button" data-url="' + esc(job.preview_url) + '">Share</button>' +
                        '</div>' +
                    '</article>';
            }

            function renderBoard(filtered) {
                var grouped = {
                    'new': [],
                    'screening': [],
                    'interview': [],
                    'offer': []
                };

                filtered.forEach(function (job) {
                    var key = String(job.stage || 'new');
                    if (!grouped[key]) {
                        grouped[key] = [];
                    }
                    grouped[key].push(job);
                });

                stageOrder.forEach(function (key) {
                    var wrap = document.getElementById('col-' + key);
                    var count = document.getElementById('count-' + key);
                    var items = grouped[key] || [];
                    if (count) {
                        count.textContent = String(items.length);
                    }
                    if (!wrap) {
                        return;
                    }
                    if (!items.length) {
                        wrap.innerHTML = '<div class="soft-note p-3 text-xs text-white/60">No roles in this stage.</div>';
                        return;
                    }
                    wrap.innerHTML = items.map(cardHtml).join('');
                });
            }

            function listRowHtml(job) {
                return '' +
                    '<article class="job-card p-4">' +
                        '<div class="grid grid-cols-1 lg:grid-cols-12 gap-3 items-center">' +
                            '<div class="lg:col-span-4">' +
                                '<p class="font-semibold text-lg">' + esc(job.title) + '</p>' +
                                '<p class="text-xs text-white/60 mt-1">Created ' + esc(job.created_label) + ' | ' + esc(job.age_days) + 'd ago</p>' +
                            '</div>' +
                            '<div class="lg:col-span-4 text-sm text-white/80">' +
                                '<p>' + esc(job.location) + '</p>' +
                                '<p class="text-white/60 text-xs mt-1">Budget ' + esc(job.budget) + ' | Source ' + esc(job.source) + '</p>' +
                            '</div>' +
                            '<div class="lg:col-span-2">' +
                                '<span class="stage-tag ' + (stageClass[job.stage] || 'stage-new') + '">' + esc(stageText(job.stage)) + '</span>' +
                            '</div>' +
                            '<div class="lg:col-span-2 grid grid-cols-2 gap-2">' +
                                '<a class="action-btn primary text-center" href="' + esc(job.preview_url) + '">View</a>' +
                                '<button class="action-btn copy-link" type="button" data-url="' + esc(job.preview_url) + '">Share</button>' +
                            '</div>' +
                        '</div>' +
                    '</article>';
            }

            function renderList(filtered) {
                var wrap = document.getElementById('listRows');
                if (!wrap) {
                    return;
                }
                if (!filtered.length) {
                    wrap.innerHTML = '<div class="soft-note p-4 text-sm text-white/65">No roles found for this view.</div>';
                    return;
                }
                wrap.innerHTML = filtered.map(listRowHtml).join('');
            }

            function refreshCopyButtons() {
                var buttons = document.querySelectorAll('.copy-link');
                Array.prototype.forEach.call(buttons, function (btn) {
                    btn.addEventListener('click', function () {
                        var url = String(btn.getAttribute('data-url') || '');
                        if (!url) {
                            return;
                        }
                        if (navigator.clipboard && navigator.clipboard.writeText) {
                            navigator.clipboard.writeText(url).then(function () {
                                btn.textContent = 'Copied';
                                setTimeout(function () { btn.textContent = 'Share'; }, 1000);
                            }).catch(function () {
                                btn.textContent = 'Share';
                            });
                        }
                    });
                });
            }

            function syncViewButtons() {
                var boardBtn = document.getElementById('viewBoard');
                var listBtn = document.getElementById('viewList');
                if (boardBtn) {
                    boardBtn.classList.toggle('is-active', state.view === 'board');
                }
                if (listBtn) {
                    listBtn.classList.toggle('is-active', state.view === 'list');
                }
            }

            function refresh() {
                var filtered = filterJobs();
                var total = filtered.length;
                var empty = document.getElementById('pipelineEmpty');
                var boardView = document.getElementById('boardView');
                var listView = document.getElementById('listView');
                var roleCount = document.getElementById('heroRoleCount');
                var statTotalRoles = document.getElementById('statTotalRoles');

                if (roleCount) {
                    roleCount.textContent = String(total);
                }
                if (statTotalRoles) {
                    statTotalRoles.textContent = String(total);
                }

                renderBoard(filtered);
                renderList(filtered);
                refreshCopyButtons();

                if (boardView && listView) {
                    boardView.classList.toggle('hidden', state.view !== 'board');
                    listView.classList.toggle('hidden', state.view !== 'list');
                }
                syncViewButtons();

                if (empty) {
                    empty.classList.toggle('hidden', total > 0);
                }

                saveState();
            }

            function bindStageFilters() {
                var wrap = document.getElementById('stageFilters');
                if (!wrap) {
                    return;
                }
                Array.prototype.forEach.call(wrap.querySelectorAll('.filter-chip'), function (btn) {
                    btn.addEventListener('click', function () {
                        state.stage = String(btn.getAttribute('data-stage') || 'all');
                        Array.prototype.forEach.call(wrap.querySelectorAll('.filter-chip'), function (chip) {
                            chip.classList.remove('is-active');
                        });
                        btn.classList.add('is-active');
                        refresh();
                    });
                });
            }

            readSavedState();

            var searchInput = document.getElementById('pipelineSearch');
            if (searchInput) {
                searchInput.addEventListener('input', function () {
                    state.query = String(searchInput.value || '').toLowerCase().trim();
                    refresh();
                });
            }

            var headerSearch = document.getElementById('headerSearch');
            if (headerSearch && searchInput) {
                headerSearch.addEventListener('input', function () {
                    searchInput.value = String(headerSearch.value || '');
                    state.query = String(searchInput.value || '').toLowerCase().trim();
                    refresh();
                });
            }

            var sortSelect = document.getElementById('pipelineSort');
            if (sortSelect) {
                sortSelect.value = state.sort;
                sortSelect.addEventListener('change', function () {
                    state.sort = String(sortSelect.value || 'newest');
                    refresh();
                });
            }

            var remoteSelect = document.getElementById('remoteFilter');
            if (remoteSelect) {
                remoteSelect.value = state.remote;
                remoteSelect.addEventListener('change', function () {
                    state.remote = String(remoteSelect.value || 'all');
                    refresh();
                });
            }

            var boardBtn = document.getElementById('viewBoard');
            var listBtn = document.getElementById('viewList');

            if (boardBtn) {
                boardBtn.addEventListener('click', function () {
                    state.view = 'board';
                    refresh();
                });
            }
            if (listBtn) {
                listBtn.addEventListener('click', function () {
                    state.view = 'list';
                    refresh();
                });
            }

            var clearBtn = document.getElementById('clearFilters');
            if (clearBtn) {
                clearBtn.addEventListener('click', function () {
                    state.query = '';
                    state.sort = 'newest';
                    state.stage = 'all';
                    state.remote = 'all';
                    if (searchInput) {
                        searchInput.value = '';
                    }
                    if (sortSelect) {
                        sortSelect.value = 'newest';
                    }
                    if (remoteSelect) {
                        remoteSelect.value = 'all';
                    }
                    var wrap = document.getElementById('stageFilters');
                    if (wrap) {
                        Array.prototype.forEach.call(wrap.querySelectorAll('.filter-chip'), function (chip) {
                            chip.classList.toggle('is-active', String(chip.getAttribute('data-stage') || '') === 'all');
                        });
                    }
                    refresh();
                });
            }

            bindStageFilters();

            var wrap = document.getElementById('stageFilters');
            if (wrap) {
                var activeBtn = wrap.querySelector('.filter-chip[data-stage="' + state.stage + '"]');
                if (activeBtn) {
                    Array.prototype.forEach.call(wrap.querySelectorAll('.filter-chip'), function (chip) {
                        chip.classList.remove('is-active');
                    });
                    activeBtn.classList.add('is-active');
                }
            }

            if (state.view !== 'list' && state.view !== 'board') {
                state.view = 'board';
            }

            refresh();
        })();
    </script>
</body>
</html>
