<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/provider_topbar.php';

requireRole('provider');

$userId = currentUserId();

function portfolioAvatarUrlForCard(string $name, ?string $imagePath, ?string $gender = null, ?int $age = null): string
{
    $img = trim((string) ($imagePath ?? ''));
    if ($img !== '') {
        return $img;
    }

    $g = strtolower(trim((string) $gender));
    $style = $g === 'female' ? 'adventurer-neutral' : 'adventurer';
    $seed = rawurlencode(strtolower(trim($name)) . '_' . (string) ($age ?? 0) . '_' . $g);
    return 'https://api.dicebear.com/9.x/' . $style . '/svg?seed=' . $seed . '&radius=20&backgroundType=gradientLinear';
}

$providerStmt = $pdo->prepare(
    'SELECT u.name, pl.company_name, pl.city
     FROM users u
     LEFT JOIN provider_locations pl ON pl.user_id = u.id
     WHERE u.id = ? LIMIT 1'
);
$providerStmt->execute([$userId]);
$provider = $providerStmt->fetch() ?: ['name' => ($_SESSION['name'] ?? 'Provider'), 'company_name' => '', 'city' => ''];

$companyName = trim((string) ($provider['company_name'] ?? ''));
if ($companyName === '') {
    $companyName = (string) ($provider['name'] ?? 'TalentSync Provider');
}
$companyCity = trim((string) ($provider['city'] ?? ''));
if ($companyCity === '') {
    $companyCity = 'Remote';
}

$myJobsStmt = $pdo->prepare(
    'SELECT id, title, source, location, budget, created_at
     FROM jobs
     WHERE provider_id = ?
     ORDER BY created_at DESC'
);
$myJobsStmt->execute([$userId]);
$myJobs = $myJobsStmt->fetchAll();

$freelancersStmt = $pdo->query(
    'SELECT f.id, f.user_id, u.name, f.skill, f.experience, f.rating, f.city, f.resume_path, f.image_path, f.gender, f.age
     FROM freelancers f
     JOIN (
         SELECT user_id, MAX(id) AS latest_id
         FROM freelancers
         GROUP BY user_id
     ) latest ON latest.latest_id = f.id
     JOIN users u ON u.id = f.user_id
     WHERE u.role = "seeker"
     ORDER BY f.rating DESC, u.name ASC
     LIMIT 50'
);
$realFreelancers = $freelancersStmt->fetchAll();

$mockProfiles = [
    ['name' => 'Aarav Sharma', 'skill' => 'Full Stack Developer', 'experience' => '5+ years', 'rating' => 4.9, 'city' => 'Remote', 'work_mode' => 'remote', 'engagement' => 'contract'],
    ['name' => 'Sofia Khan', 'skill' => 'UI/UX Designer', 'experience' => '4+ years', 'rating' => 4.8, 'city' => 'Bengaluru', 'work_mode' => 'hybrid', 'engagement' => 'project'],
    ['name' => 'Noah Patel', 'skill' => 'DevOps Engineer', 'experience' => '6+ years', 'rating' => 4.7, 'city' => 'Pune', 'work_mode' => 'onsite', 'engagement' => 'full-time'],
    ['name' => 'Meera Iyer', 'skill' => 'Content Strategist', 'experience' => '3+ years', 'rating' => 4.6, 'city' => 'Remote', 'work_mode' => 'remote', 'engagement' => 'part-time'],
    ['name' => 'Ethan Roy', 'skill' => 'Mobile App Developer', 'experience' => '5+ years', 'rating' => 4.8, 'city' => 'Hyderabad', 'work_mode' => 'hybrid', 'engagement' => 'contract'],
    ['name' => 'Riya Das', 'skill' => 'Data Analyst', 'experience' => '4+ years', 'rating' => 4.7, 'city' => 'Chennai', 'work_mode' => 'onsite', 'engagement' => 'full-time'],
    ['name' => 'Kabir Mehta', 'skill' => 'Backend Developer', 'experience' => '7+ years', 'rating' => 4.9, 'city' => 'Remote', 'work_mode' => 'remote', 'engagement' => 'contract'],
    ['name' => 'Anaya Gupta', 'skill' => 'SEO Specialist', 'experience' => '3+ years', 'rating' => 4.5, 'city' => 'Mumbai', 'work_mode' => 'hybrid', 'engagement' => 'project'],
    ['name' => 'Vihaan Nair', 'skill' => 'QA Automation Engineer', 'experience' => '5+ years', 'rating' => 4.6, 'city' => 'Kochi', 'work_mode' => 'remote', 'engagement' => 'full-time'],
    ['name' => 'Zara Ali', 'skill' => 'Motion Graphic Designer', 'experience' => '4+ years', 'rating' => 4.7, 'city' => 'Delhi', 'work_mode' => 'onsite', 'engagement' => 'project'],
];

$talentRows = [];
foreach ($realFreelancers as $row) {
    $name = trim((string) ($row['name'] ?? 'Freelancer'));
    $skill = trim((string) ($row['skill'] ?? 'Generalist'));
    if ($skill === '') {
        $skill = 'Generalist';
    }
    $city = trim((string) ($row['city'] ?? ''));
    if ($city === '') {
        $city = 'Remote';
    }

    $seed = (int) abs(crc32(strtolower($name . '|' . $skill)));
    $modeOptions = ['remote', 'hybrid', 'onsite'];
    $engagementOptions = ['full-time', 'part-time', 'contract', 'project'];
    $workMode = $modeOptions[$seed % count($modeOptions)];
    $engagement = $engagementOptions[$seed % count($engagementOptions)];

    $resumePath = trim((string) ($row['resume_path'] ?? ''));
    $avatarPath = portfolioAvatarUrlForCard(
        $name,
        (string) ($row['image_path'] ?? ''),
        isset($row['gender']) ? (string) $row['gender'] : null,
        isset($row['age']) ? (int) $row['age'] : null
    );
    $portfolioLink = 'freelancer_portfolio.php?user_id=' . (int) $row['user_id'];
    $chatLink = 'chat.php?user_id=' . (int) $row['user_id'];
    $briefLink = 'job_preview.php?' . http_build_query([
        'title' => $name . ' - ' . $skill,
        'company' => 'TalentSync Freelancer Network',
        'location' => $city,
        'source' => 'talentsync',
        'from' => 'hub',
        'description' => $name . ' is available for ' . $engagement . ' opportunities in ' . $workMode . ' mode.',
        'url' => $chatLink,
    ]);

    $talentRows[] = [
        'name' => $name,
        'skill' => $skill,
        'experience' => trim((string) ($row['experience'] ?? '')) ?: 'Not specified',
        'rating' => round((float) ($row['rating'] ?? 0), 1),
        'city' => $city,
        'work_mode' => $workMode,
        'engagement' => $engagement,
        'has_resume' => $resumePath !== '',
        'resume_link' => $resumePath !== '' ? $resumePath : $briefLink,
        'resume_label' => $resumePath !== '' ? 'Download Resume' : 'Profile Brief',
        'portfolio_link' => $portfolioLink,
        'chat_link' => $chatLink,
        'avatar' => $avatarPath,
        'is_mock' => false,
    ];
}

foreach ($mockProfiles as $idx => $mock) {
    $name = (string) $mock['name'];
    $skill = (string) $mock['skill'];
    $city = (string) $mock['city'];
    $mockKey = 'mock_' . ($idx + 1);
    $chatLink = 'chat.php?mock_id=' . rawurlencode($mockKey);
    $mockPortfolioLink = 'freelancer_portfolio.php?' . http_build_query([
        'mock' => 1,
        'mock_id' => $mockKey,
        'name' => $name,
        'skill' => $skill,
        'experience' => (string) $mock['experience'],
        'city' => $city,
        'rating' => (string) $mock['rating'],
        'work_mode' => (string) $mock['work_mode'],
        'engagement' => (string) $mock['engagement'],
    ]);
    $briefLink = 'job_preview.php?' . http_build_query([
        'title' => $name . ' - ' . $skill,
        'company' => 'TalentSync Freelancer Network',
        'location' => $city,
        'source' => 'mock-profile',
        'from' => 'hub',
        'description' => $name . ' (mock profile) has ' . $mock['experience'] . ' and prefers ' . $mock['work_mode'] . ' work.',
        'url' => $chatLink,
    ]);
    $mockAvatar = portfolioAvatarUrlForCard($name, null, null, null);

    $talentRows[] = [
        'name' => $name,
        'skill' => $skill,
        'experience' => (string) $mock['experience'],
        'rating' => (float) $mock['rating'],
        'city' => $city,
        'work_mode' => (string) $mock['work_mode'],
        'engagement' => (string) $mock['engagement'],
        'has_resume' => true,
        'resume_link' => $briefLink,
        'resume_label' => 'Profile Brief',
        'portfolio_link' => $mockPortfolioLink,
        'chat_link' => $chatLink,
        'avatar' => $mockAvatar,
        'mock_key' => $mockKey,
        'is_mock' => true,
    ];
}

$totalTalents = count($talentRows);
$topRatedTalents = 0;
$resumeReadyTalents = 0;
foreach ($talentRows as $talent) {
    if ((float) $talent['rating'] >= 4.7) {
        $topRatedTalents += 1;
    }
    if (!empty($talent['has_resume'])) {
        $resumeReadyTalents += 1;
    }
}

$activeJobs = count($myJobs);
$latestJob = $activeJobs > 0 ? (string) ($myJobs[0]['title'] ?? 'No active role') : 'No active role';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TalentSync PRO - Provider Dashboard</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@300;400;500;600;700&family=Instrument+Serif:ital@1&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
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

        .provider-scroll {
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .provider-scroll::-webkit-scrollbar {
            width: 0;
            height: 0;
            background: transparent;
            display: none;
        }

        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        body {
            font-family: 'Barlow', sans-serif;
            background: #020202;
            color: #fff;
        }
        .ts-heading {
            font-family: 'Instrument Serif', serif;
            font-style: italic;
            letter-spacing: -0.02em;
        }
        .sort-select,
        .filter-select {
            width: 100%;
            border-radius: 0.9rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.14), rgba(255, 255, 255, 0.04));
            color: #fff;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.15);
            transition: border-color .2s ease, box-shadow .2s ease, background .2s ease;
        }
        .sort-select:hover,
        .filter-select:hover {
            border-color: rgba(255, 255, 255, 0.33);
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.18), rgba(255, 255, 255, 0.06));
        }
        .sort-select:focus,
        .filter-select:focus {
            outline: none;
            border-color: rgba(255, 255, 255, 0.45);
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.12);
        }
        .sort-select option,
        .filter-select option {
            background: #0d1117;
            color: #fff;
        }
        .talent-card {
            position: relative;
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 1.7rem;
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.11), rgba(255, 255, 255, 0.03));
            overflow: hidden;
            cursor: pointer;
            padding: 1.1rem;
            box-shadow: 0 12px 26px rgba(0, 0, 0, 0.28), inset 0 1px 0 rgba(255, 255, 255, 0.06);
        }
        .member-image-container {
            position: relative;
            width: 100%;
            aspect-ratio: 1 / 0.78;
            overflow: visible;
            margin-top: 0.6rem;
            border-radius: 0;
            background: transparent;
            border: none;
        }
        .member-image-mask {
            width: 100%;
            height: 100%;
            object-fit: contain;
            object-position: center;
            mask-image: none;
            -webkit-mask-image: none;
        }
        .chip {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.18rem 0.5rem;
            border-radius: 999px;
            font-size: 0.64rem;
            font-weight: 700;
            border: 1px solid rgba(255, 255, 255, 0.2);
            background: rgba(255, 255, 255, 0.08);
            color: #e8edf7;
        }
        .card-actions {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.5rem;
        }
        .card-action {
            min-height: 52px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            line-height: 1.2;
            font-size: 0.9rem;
            font-weight: 600;
        }
    </style>
</head>
<body>
<?php renderProviderTopbar('hub', true, 'Search freelancers, skills, city'); ?>

<div class="flex pt-20 min-h-screen">
    <aside class="provider-scroll fixed left-0 top-20 bottom-0 flex flex-col p-6 overflow-y-auto bg-[#090b0f] h-screen w-72 rounded-r-3xl border-r border-white/10 z-40">
        <div class="mb-8">
            <div class="flex items-center gap-3 mb-2">
                <span class="text-lg font-bold text-white">Talent Filters</span>
                <span class="material-symbols-outlined text-white/70" style="font-size: 18px;">tune</span>
            </div>
            <p class="text-xs text-white/50">Find and shortlist the best freelancers</p>
        </div>

        <nav class="flex flex-col gap-1 flex-1 text-sm">
            <button class="sidebar-filter flex items-center gap-3 px-4 py-3 bg-white/10 text-white rounded-xl font-bold text-left" data-type="all" type="button">
                <span class="material-symbols-outlined">groups</span>
                All Talent
            </button>
            <button class="sidebar-filter flex items-center gap-3 px-4 py-3 text-white/70 hover:bg-white/10 rounded-xl transition-all text-left" data-type="top_rated" type="button">
                <span class="material-symbols-outlined">stars</span>
                Top Rated
            </button>
            <button class="sidebar-filter flex items-center gap-3 px-4 py-3 text-white/70 hover:bg-white/10 rounded-xl transition-all text-left" data-type="developer" type="button">
                <span class="material-symbols-outlined">code</span>
                Developers
            </button>
            <button class="sidebar-filter flex items-center gap-3 px-4 py-3 text-white/70 hover:bg-white/10 rounded-xl transition-all text-left" data-type="designer" type="button">
                <span class="material-symbols-outlined">design_services</span>
                Designers
            </button>
            <button class="sidebar-filter flex items-center gap-3 px-4 py-3 text-white/70 hover:bg-white/10 rounded-xl transition-all text-left" data-type="remote" type="button">
                <span class="material-symbols-outlined">home_work</span>
                Remote Ready
            </button>
        </nav>

        <div class="mt-8 pt-6 border-t border-white/10 space-y-2">
            <a href="provider_location.php" class="w-full py-3 bg-white text-black rounded-lg font-bold text-sm block text-center">Edit Company Profile</a>
            <a class="flex items-center gap-3 px-4 py-3 text-white/70 hover:bg-white/10 rounded-xl transition-all" href="post_job.php">
                <span class="material-symbols-outlined">add_circle</span>
                Post New Job
            </a>
            <a class="flex items-center gap-3 px-4 py-3 text-white/70 hover:bg-white/10 rounded-xl transition-all" href="hiring_board.php">
                <span class="material-symbols-outlined">dashboard_customize</span>
                Open Hiring Board
            </a>
            <a class="flex items-center gap-3 px-4 py-3 text-white/70 hover:bg-white/10 rounded-xl transition-all" href="company_pipeline.php?provider_id=<?php echo (int) $userId; ?>">
                <span class="material-symbols-outlined">account_tree</span>
                Company Pipeline
            </a>
            <a class="flex items-center gap-3 px-4 py-3 text-white/70 hover:bg-white/10 rounded-xl transition-all" href="map.php">
                <span class="material-symbols-outlined">map</span>
                Nearby Talent
            </a>
        </div>
    </aside>

    <main class="ml-72 flex-1 p-8 bg-[#05070b]">
        <div class="max-w-7xl mx-auto">
            <div class="grid grid-cols-12 gap-8 mb-12">
                <div class="col-span-12 lg:col-span-4 rounded-xl p-8 flex flex-col justify-between relative overflow-hidden h-full border border-white/15" style="background: linear-gradient(145deg, rgba(255,255,255,0.12), rgba(255,255,255,0.04));">
                    <div class="relative z-10">
                        <h1 class="text-4xl ts-heading text-white leading-tight mb-4">Build your hiring squad</h1>
                        <p class="text-white/70 text-sm mb-8 max-w-xs">Shortlist, message, and onboard freelancers with one workflow inspired by your seeker experience.</p>
                        <a href="provider_location.php" class="bg-white text-black px-6 py-3 rounded-lg font-bold text-sm shadow-xl inline-block">Edit Company Profile</a>
                    </div>
                    <div class="absolute bottom-0 right-0 w-48 h-48 opacity-20">
                        <span class="material-symbols-outlined" style="font-size: 200px;">diversity_3</span>
                    </div>
                </div>

                <div class="col-span-12 lg:col-span-8 grid grid-cols-2 gap-6">
                    <div class="rounded-xl p-6 flex flex-col justify-center items-center text-center border border-white/15" style="background: linear-gradient(145deg, rgba(255,255,255,0.10), rgba(255,255,255,0.03));">
                        <span class="material-symbols-outlined text-white text-4xl mb-4">groups</span>
                        <span class="text-3xl font-extrabold text-white" id="talentCountStat"><?php echo (int) $totalTalents; ?></span>
                        <span class="text-sm text-white/70 font-medium">Talent Profiles</span>
                    </div>
                    <div class="rounded-xl p-6 flex flex-col justify-center items-center text-center border border-white/15" style="background: linear-gradient(145deg, rgba(255,255,255,0.10), rgba(255,255,255,0.03));">
                        <span class="material-symbols-outlined text-white text-4xl mb-4">verified</span>
                        <span class="text-3xl font-extrabold text-white"><?php echo (int) $topRatedTalents; ?></span>
                        <span class="text-sm text-white/70 font-medium">Top Rated (4.7+)</span>
                    </div>
                    <div class="col-span-2 rounded-xl p-6 flex items-center justify-between shadow-sm border border-white/15" style="background: linear-gradient(145deg, rgba(255,255,255,0.10), rgba(255,255,255,0.03));">
                        <div>
                            <p class="text-sm font-bold text-white"><?php echo e($companyName); ?> | <?php echo e($companyCity); ?></p>
                            <p class="text-xs text-white/70">Active Jobs: <?php echo (int) $activeJobs; ?> | Resume Ready: <?php echo (int) $resumeReadyTalents; ?> | Latest Role: <?php echo e($latestJob); ?></p>
                        </div>
                        <a href="post_job.php" class="px-4 py-2 rounded-lg bg-white text-black text-sm font-semibold">Post Job</a>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between mb-8">
                <h2 class="text-4xl ts-heading text-white">Recommended Freelancers <span id="talentCountBadge" class="text-sm text-white/70">(<?php echo (int) $totalTalents; ?>)</span></h2>
                <div class="flex gap-3 items-center">
                    <label class="text-sm text-white/70" for="sortTalents">Sort by</label>
                    <select id="sortTalents" class="sort-select text-sm px-3 py-2 min-w-[220px]">
                        <option value="rating_high">Rating: High to Low</option>
                        <option value="rating_low">Rating: Low to High</option>
                        <option value="az">Name A-Z</option>
                        <option value="skill">Skill A-Z</option>
                    </select>
                </div>
            </div>

            <section class="mb-8 rounded-2xl border border-white/15 p-4 md:p-5" style="background: linear-gradient(145deg, rgba(255,255,255,0.10), rgba(255,255,255,0.03));">
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-6 gap-3 mb-3">
                    <div class="xl:col-span-2">
                        <label class="text-xs text-white/65 mb-1 block" for="globalSearch">Search</label>
                        <input id="globalSearch" type="text" class="w-full rounded-lg border border-white/20 text-sm px-3 py-2 bg-white/10 text-white placeholder-white/40" placeholder="Name, skill, experience, city" />
                    </div>
                    <div>
                        <label class="text-xs text-white/65 mb-1 block" for="skillFilter">Skill family</label>
                        <select id="skillFilter" class="filter-select text-sm px-3 py-2">
                            <option value="all">All skills</option>
                            <option value="developer">Developers</option>
                            <option value="designer">Designers</option>
                            <option value="data">Data</option>
                            <option value="marketing">Marketing</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-white/65 mb-1 block" for="ratingFilter">Min rating</label>
                        <select id="ratingFilter" class="filter-select text-sm px-3 py-2">
                            <option value="0">Any</option>
                            <option value="4">4.0+</option>
                            <option value="4.5">4.5+</option>
                            <option value="4.7">4.7+</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-white/65 mb-1 block" for="modeFilter">Work mode</label>
                        <select id="modeFilter" class="filter-select text-sm px-3 py-2">
                            <option value="all">All modes</option>
                            <option value="remote">Remote</option>
                            <option value="hybrid">Hybrid</option>
                            <option value="onsite">On-site</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-white/65 mb-1 block" for="engagementFilter">Engagement</label>
                        <select id="engagementFilter" class="filter-select text-sm px-3 py-2">
                            <option value="all">All engagement</option>
                            <option value="full-time">Full-time</option>
                            <option value="part-time">Part-time</option>
                            <option value="contract">Contract</option>
                            <option value="project">Project</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-6 gap-3 items-end">
                    <div>
                        <label class="text-xs text-white/65 mb-1 block" for="resumeFilter">Resume</label>
                        <select id="resumeFilter" class="filter-select text-sm px-3 py-2">
                            <option value="all">Any</option>
                            <option value="yes">Has resume/brief</option>
                            <option value="no">No resume</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-xs text-white/65 mb-1 block" for="cityFilter">City contains</label>
                        <input id="cityFilter" type="text" class="w-full rounded-lg border border-white/20 text-sm px-3 py-2 bg-white/10 text-white placeholder-white/40" placeholder="e.g., Bengaluru, Remote" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-xs text-white/65 mb-1 block" for="experienceFilter">Experience keyword</label>
                        <input id="experienceFilter" type="text" class="w-full rounded-lg border border-white/20 text-sm px-3 py-2 bg-white/10 text-white placeholder-white/40" placeholder="e.g., 5+ years" />
                    </div>
                    <div class="md:text-right">
                        <button id="clearFilters" type="button" class="px-4 py-2 rounded-lg border border-white/20 text-sm bg-white/10 text-white hover:bg-white/15">Clear Filters</button>
                    </div>
                </div>
                <div class="mt-3 text-xs text-white/60">Includes 10 mock freelancer profiles so you can demo the client-facing hiring workflow instantly.</div>
            </section>

            <div id="talentCards" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6"></div>
        </div>
    </main>
</div>

<script>
    var allTalents = <?php echo json_encode($talentRows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var activeSidebarType = 'all';

    function sanitize(value) {
        return String(value || '').replace(/[&<>'"]/g, function (char) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                "'": '&#39;',
                '"': '&quot;'
            }[char];
        });
    }

    function lower(value) {
        return String(value || '').toLowerCase().trim();
    }

    function skillFamily(skill) {
        var s = lower(skill);
        if (/(developer|engineer|full stack|frontend|backend|mobile|devops|qa|software|php|java|python|react)/.test(s)) {
            return 'developer';
        }
        if (/(design|ux|ui|graphic|motion|brand|illustrator)/.test(s)) {
            return 'designer';
        }
        if (/(data|analyst|ml|ai|analytics)/.test(s)) {
            return 'data';
        }
        if (/(seo|marketing|content|social|growth)/.test(s)) {
            return 'marketing';
        }
        return 'other';
    }

    function sidebarMatches(talent) {
        if (activeSidebarType === 'all') {
            return true;
        }
        if (activeSidebarType === 'top_rated') {
            return Number(talent.rating || 0) >= 4.7;
        }
        if (activeSidebarType === 'developer') {
            return skillFamily(talent.skill) === 'developer';
        }
        if (activeSidebarType === 'designer') {
            return skillFamily(talent.skill) === 'designer';
        }
        if (activeSidebarType === 'remote') {
            return lower(talent.work_mode) === 'remote' || lower(talent.city).indexOf('remote') > -1;
        }
        return true;
    }

    function filterTalents() {
        var q = lower($('#globalSearch').val());
        var skill = $('#skillFilter').val();
        var minRating = Number($('#ratingFilter').val() || 0);
        var mode = $('#modeFilter').val();
        var engagement = $('#engagementFilter').val();
        var resumeFilter = $('#resumeFilter').val();
        var city = lower($('#cityFilter').val());
        var exp = lower($('#experienceFilter').val());

        return allTalents.filter(function (talent) {
            if (!sidebarMatches(talent)) {
                return false;
            }

            var text = lower([talent.name, talent.skill, talent.experience, talent.city, talent.work_mode, talent.engagement].join(' '));
            if (q && text.indexOf(q) === -1) {
                return false;
            }

            if (skill !== 'all' && skillFamily(talent.skill) !== skill) {
                return false;
            }

            if (Number(talent.rating || 0) < minRating) {
                return false;
            }

            if (mode !== 'all' && lower(talent.work_mode) !== mode) {
                return false;
            }

            if (engagement !== 'all' && lower(talent.engagement) !== engagement) {
                return false;
            }

            if (resumeFilter === 'yes' && !talent.has_resume) {
                return false;
            }
            if (resumeFilter === 'no' && talent.has_resume) {
                return false;
            }

            if (city && lower(talent.city).indexOf(city) === -1) {
                return false;
            }

            if (exp && lower(talent.experience).indexOf(exp) === -1) {
                return false;
            }

            return true;
        });
    }

    function sortTalents(items) {
        var sortBy = $('#sortTalents').val();
        var sorted = items.slice();

        if (sortBy === 'az') {
            sorted.sort(function (a, b) {
                return String(a.name).localeCompare(String(b.name));
            });
        } else if (sortBy === 'skill') {
            sorted.sort(function (a, b) {
                return String(a.skill).localeCompare(String(b.skill));
            });
        } else if (sortBy === 'rating_low') {
            sorted.sort(function (a, b) {
                return Number(a.rating || 0) - Number(b.rating || 0);
            });
        } else {
            sorted.sort(function (a, b) {
                return Number(b.rating || 0) - Number(a.rating || 0);
            });
        }

        return sorted;
    }

    function renderCards(items) {
        var html = '';

        if (!items.length) {
            html = '<div class="col-span-full rounded-xl border border-white/15 p-6 text-sm text-white/70" style="background: linear-gradient(145deg, rgba(255,255,255,0.10), rgba(255,255,255,0.03));">No freelancers found for this filter combination.</div>';
            $('#talentCards').html(html);
            $('#talentCountBadge').text('(0)');
            $('#talentCountStat').text('0');
            return;
        }

        items.forEach(function (talent) {
            var chips = [
                '<span class="chip">' + sanitize(talent.work_mode) + '</span>',
                '<span class="chip">' + sanitize(talent.engagement) + '</span>'
            ];
            if (talent.is_mock) {
                chips.push('<span class="chip">Mock</span>');
            }

            html += '' +
                '<article class="talent-card p-5" data-portfolio="' + sanitize(talent.portfolio_link) + '" tabindex="0" role="link" title="Open portfolio">' +
                    '<div class="flex items-start justify-between gap-3">' +
                        '<div class="min-w-0">' +
                            '<h3 class="text-xl font-bold tracking-tight text-white truncate"><a href="' + sanitize(talent.portfolio_link) + '" class="hover:underline underline-offset-4">' + sanitize(talent.name) + '</a></h3>' +
                            '<p class="text-sm text-white/70 mt-1 truncate">' + sanitize(talent.skill) + ' | ' + sanitize(talent.experience) + '</p>' +
                            '<p class="text-xs text-white/60 mt-1">City: ' + sanitize(talent.city) + '</p>' +
                        '</div>' +
                        '<div class="text-right">' +
                            '<p class="text-xs text-white/55">Rating</p>' +
                            '<p class="text-xl font-bold text-white">' + sanitize(Number(talent.rating || 0).toFixed(1)) + '</p>' +
                        '</div>' +
                    '</div>' +
                    '<div class="member-image-container">' +
                        '<img src="' + sanitize(talent.avatar || '') + '" alt="' + sanitize(talent.name) + '" class="member-image-mask" loading="lazy">' +
                    '</div>' +
                    '<div class="mt-3 flex flex-wrap gap-2">' + chips.join('') + '</div>' +
                    '<div class="mt-4 card-actions pt-3 border-t border-white/15">' +
                        '<a href="' + sanitize(talent.portfolio_link) + '" class="card-action rounded-xl bg-white text-black hover:bg-white/90">View Portfolio</a>' +
                        '<a href="' + sanitize(talent.chat_link) + '" class="card-action rounded-xl border border-white/25 text-white/85 hover:bg-white/10">Hire / Chat</a>' +
                        '<a href="' + sanitize(talent.resume_link) + '" target="_blank" rel="noopener" class="card-action rounded-xl border border-white/25 text-white/85 hover:bg-white/10" title="' + sanitize(talent.resume_label) + '">' + sanitize(talent.resume_label) + '</a>' +
                    '</div>' +
                '</article>';
        });

        $('#talentCards').html(html);
        $('#talentCountBadge').text('(' + items.length + ')');
        $('#talentCountStat').text(items.length);
    }

    function refresh() {
        var filtered = filterTalents();
        var sorted = sortTalents(filtered);
        renderCards(sorted);
    }

    $('.sidebar-filter').on('click', function () {
        activeSidebarType = $(this).data('type');
        $('.sidebar-filter').removeClass('bg-white/10 text-white font-bold').addClass('text-white/70');
        $(this).removeClass('text-white/70').addClass('bg-white/10 text-white font-bold');
        refresh();
    });

    $('#globalSearch, #cityFilter, #experienceFilter').on('input', refresh);
    $('#skillFilter, #ratingFilter, #modeFilter, #engagementFilter, #resumeFilter, #sortTalents').on('change', refresh);

    $('#headerSearch').on('input', function () {
        $('#globalSearch').val($(this).val());
        refresh();
    });

    $('#talentCards').on('click', '.talent-card', function (event) {
        if ($(event.target).closest('a,button').length) {
            return;
        }
        var url = String($(this).data('portfolio') || '');
        if (url) {
            window.location.href = url;
        }
    });

    $('#talentCards').on('keydown', '.talent-card', function (event) {
        if (event.key !== 'Enter' && event.key !== ' ') {
            return;
        }
        if ($(event.target).closest('a,button').length) {
            return;
        }
        event.preventDefault();
        var url = String($(this).data('portfolio') || '');
        if (url) {
            window.location.href = url;
        }
    });

    $('#clearFilters').on('click', function () {
        $('#globalSearch').val('');
        $('#headerSearch').val('');
        $('#skillFilter').val('all');
        $('#ratingFilter').val('0');
        $('#modeFilter').val('all');
        $('#engagementFilter').val('all');
        $('#resumeFilter').val('all');
        $('#cityFilter').val('');
        $('#experienceFilter').val('');
        $('#sortTalents').val('rating_high');
        activeSidebarType = 'all';
        $('.sidebar-filter').removeClass('bg-white/10 text-white font-bold').addClass('text-white/70');
        $('.sidebar-filter[data-type="all"]').removeClass('text-white/70').addClass('bg-white/10 text-white font-bold');
        refresh();
    });

    refresh();
</script>
</body>
</html>
