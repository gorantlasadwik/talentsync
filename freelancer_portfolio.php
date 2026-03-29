<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/seeker_topbar.php';
require_once __DIR__ . '/includes/provider_topbar.php';

requireLogin();

$viewerId = currentUserId();
$targetUserId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : $viewerId;
if ($targetUserId <= 0) {
    $targetUserId = $viewerId;
}
$isMockProfile = isset($_GET['mock']) && (string) $_GET['mock'] === '1';
$mockQuery = [];
$sessionRole = (string) ($_SESSION['role'] ?? '');

try {
    $pdo->exec('ALTER TABLE freelancers ADD COLUMN city VARCHAR(120) DEFAULT NULL');
} catch (Throwable $e) {
}
foreach ([
    'gender VARCHAR(20) DEFAULT NULL',
    'age INT DEFAULT NULL',
    'linkedin_url VARCHAR(255) DEFAULT NULL',
    'github_url VARCHAR(255) DEFAULT NULL',
    'contact_email VARCHAR(190) DEFAULT NULL',
    'services TEXT DEFAULT NULL',
    'tools TEXT DEFAULT NULL',
    'portfolio_tagline VARCHAR(190) DEFAULT NULL',
] as $columnDef) {
    try {
        $pdo->exec('ALTER TABLE freelancers ADD COLUMN ' . $columnDef);
    } catch (Throwable $e) {
    }
}

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS freelancer_projects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(190) NOT NULL,
        description TEXT DEFAULT NULL,
        project_url VARCHAR(255) DEFAULT NULL,
        image_path VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_freelancer_projects_user (user_id, created_at)
    )'
);

if ($isMockProfile) {
    $mockName = trim((string) ($_GET['name'] ?? 'Mock Freelancer'));
    if ($mockName === '') {
        $mockName = 'Mock Freelancer';
    }
    $mockSkill = trim((string) ($_GET['skill'] ?? 'Freelancer'));
    if ($mockSkill === '') {
        $mockSkill = 'Freelancer';
    }
    $mockExperience = trim((string) ($_GET['experience'] ?? '3+ years'));
    $mockCity = trim((string) ($_GET['city'] ?? 'Remote'));
    $mockRating = trim((string) ($_GET['rating'] ?? '4.7'));
    $mockWorkMode = trim((string) ($_GET['work_mode'] ?? 'remote'));
    $mockEngagement = trim((string) ($_GET['engagement'] ?? 'project'));

    $mockBio = 'Mock profile for dashboard preview. Skilled in ' . $mockSkill . ' with ' . $mockExperience . ' of experience, available in ' . $mockWorkMode . ' mode for ' . $mockEngagement . ' engagements.';

    $profile = [
        'id' => 0,
        'name' => $mockName,
        'email' => 'mock@talentsync.local',
        'role' => 'seeker',
        'skill' => $mockSkill,
        'experience' => $mockExperience,
        'bio' => $mockBio,
        'city' => $mockCity,
        'image_path' => '',
        'resume_path' => '',
        'gender' => '',
        'age' => null,
        'linkedin_url' => '',
        'github_url' => '',
        'contact_email' => '',
        'services' => $mockSkill . ', Consulting, Delivery',
        'tools' => $mockSkill,
        'portfolio_tagline' => $mockSkill . ' | Rating ' . $mockRating,
    ];

    $isOwner = false;
    $mockQuery = [
        'mock' => 1,
        'name' => $mockName,
        'skill' => $mockSkill,
        'experience' => $mockExperience,
        'city' => $mockCity,
        'rating' => $mockRating,
        'work_mode' => $mockWorkMode,
        'engagement' => $mockEngagement,
    ];
} else {
    $profileStmt = $pdo->prepare(
        'SELECT u.id, u.name, u.email, u.role,
                f.skill, f.experience, f.bio, f.city, f.image_path, f.resume_path,
                f.gender, f.age, f.linkedin_url, f.github_url, f.contact_email, f.services, f.tools, f.portfolio_tagline
         FROM users u
         LEFT JOIN freelancers f ON f.user_id = u.id
         WHERE u.id = ? LIMIT 1'
    );
    $profileStmt->execute([$targetUserId]);
    $profile = $profileStmt->fetch();

    if (!$profile || (($profile['role'] ?? '') !== 'seeker' && $targetUserId !== $viewerId)) {
        header('Location: dashboard.php');
        exit;
    }

    $isOwner = $targetUserId === $viewerId;
}

function cartoonAvatarUrl(string $name, ?string $gender, ?int $age): string
{
    $g = strtolower(trim((string) $gender));
    $style = $g === 'female' ? 'adventurer-neutral' : 'adventurer';
    $seed = rawurlencode(strtolower(trim($name)) . '_' . (string) ($age ?? 0) . '_' . $g);
    return 'https://api.dicebear.com/9.x/' . $style . '/svg?seed=' . $seed . '&radius=20&backgroundType=gradientLinear';
}

$name = (string) ($profile['name'] ?? 'Freelancer');
$gender = trim((string) ($profile['gender'] ?? ''));
$age = isset($profile['age']) ? (int) $profile['age'] : null;
$imagePath = trim((string) ($profile['image_path'] ?? ''));
$avatar = $imagePath !== '' ? $imagePath : cartoonAvatarUrl($name, $gender, $age);

$tagline = trim((string) ($profile['portfolio_tagline'] ?? ''));
if ($tagline === '') {
    $tagline = trim((string) ($profile['skill'] ?? '')) !== ''
        ? (string) $profile['skill']
        : 'Freelance Professional';
}

$bio = trim((string) ($profile['bio'] ?? ''));
if ($bio === '') {
    $bio = 'I help clients transform ideas into polished digital outcomes. I collaborate across design, development and delivery with clear communication and practical execution.';
}

$city = trim((string) ($profile['city'] ?? ''));
if ($city === '') {
    $city = 'Remote';
}

$experience = trim((string) ($profile['experience'] ?? ''));
if ($experience === '') {
    $experience = '2+ years of experience';
}

$linkedin = trim((string) ($profile['linkedin_url'] ?? ''));
$github = trim((string) ($profile['github_url'] ?? ''));
$contactEmail = trim((string) ($profile['contact_email'] ?? ''));
if ($contactEmail === '') {
    $contactEmail = trim((string) ($profile['email'] ?? ''));
}
$resumePath = trim((string) ($profile['resume_path'] ?? ''));

$serviceRaw = trim((string) ($profile['services'] ?? ''));
$services = array_values(array_filter(array_map('trim', preg_split('/[,\n]+/', $serviceRaw ?: ''))));
if (!$services) {
    $services = ['Landing Page Design', 'Frontend Development', 'Website Revamp', 'Performance Optimization'];
}

$toolRaw = trim((string) ($profile['tools'] ?? ''));
$toolItems = array_values(array_filter(array_map('trim', preg_split('/[,\n]+/', $toolRaw ?: ''))));
if (!$toolItems && trim((string) ($profile['skill'] ?? '')) !== '') {
    $toolItems = [trim((string) ($profile['skill'] ?? ''))];
}
if (!$toolItems) {
    $toolItems = ['Photoshop', 'PowerPoint', 'Figma'];
}

function toolIconClass(string $tool): ?string
{
    $t = strtolower(trim($tool));
    $map = [
        'photoshop' => 'devicon-photoshop-plain colored',
        'powerpoint' => 'devicon-powerpoint-plain colored',
        'figma' => 'devicon-figma-plain colored',
        'react' => 'devicon-react-original colored',
        'javascript' => 'devicon-javascript-plain colored',
        'typescript' => 'devicon-typescript-plain colored',
        'python' => 'devicon-python-plain colored',
        'illustrator' => 'devicon-illustrator-plain colored',
        'canva' => 'devicon-canva-original colored',
        'excel' => 'devicon-excel-plain colored',
        'power bi' => 'devicon-powerbi-plain colored',
        'wordpress' => 'devicon-wordpress-plain colored',
        'node' => 'devicon-nodejs-plain colored',
        'mongo' => 'devicon-mongodb-plain colored',
        'mysql' => 'devicon-mysql-plain colored',
        'postgres' => 'devicon-postgresql-plain colored',
        'git' => 'devicon-git-plain colored',
        'github' => 'devicon-github-original',
        'java' => 'devicon-java-plain colored',
        'ruby' => 'devicon-ruby-plain colored',
        'html' => 'devicon-html5-plain colored',
        'css' => 'devicon-css3-plain colored',
        'apache' => 'devicon-apache-plain colored',
        'nginx' => 'devicon-nginx-original colored',
        'mariadb' => 'devicon-mariadb-original colored',
        'firebase' => 'devicon-firebase-plain colored',
        'influx' => 'devicon-influxdb-original colored',
        'aws' => 'devicon-amazonwebservices-original colored',
        'azure' => 'devicon-azure-plain colored',
        'cloudflare' => 'devicon-cloudflare-plain colored',
        'netlify' => 'devicon-netlify-plain colored',
        'linux' => 'devicon-linux-plain colored',
        'shell' => 'devicon-bash-plain colored',
        'powershell' => 'devicon-powershell-plain colored',
        'terminal' => 'devicon-bash-plain colored',
        'windows' => 'devicon-windows8-original colored',
        'alibaba' => 'devicon-alibabacloud-plain colored',
        'linode' => 'devicon-linode-plain colored',
        'c++' => 'devicon-cplusplus-plain colored',
        'c#' => 'devicon-csharp-plain colored',
        'php' => 'devicon-php-plain colored',
        'bootstrap' => 'devicon-bootstrap-plain colored',
    ];

    foreach ($map as $needle => $className) {
        if (str_contains($t, $needle)) {
            return $className;
        }
    }
    return null;
}

$realProjects = [];
if (!$isMockProfile) {
    $projectsStmt = $pdo->prepare(
        'SELECT id, title, description, project_url, image_path, created_at
         FROM freelancer_projects
         WHERE user_id = ?
         ORDER BY created_at DESC'
    );
    $projectsStmt->execute([$targetUserId]);
    $realProjects = $projectsStmt->fetchAll();
}

$mockProjects = [
    [
        'title' => 'Conversion-Focused Landing Page',
        'description' => 'Designed and built a fast, responsive landing page for a SaaS campaign with strong lead conversion.',
        'project_url' => '#',
        'image_path' => 'https://images.unsplash.com/photo-1461749280684-dccba630e2f6?auto=format&fit=crop&w=1200&q=80',
    ],
    [
        'title' => 'Brand Refresh Portfolio Site',
        'description' => 'Delivered a clean personal-brand website with modular sections for case studies and services.',
        'project_url' => '#',
        'image_path' => 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?auto=format&fit=crop&w=1200&q=80',
    ],
    [
        'title' => 'Client Onboarding Dashboard',
        'description' => 'Implemented a dashboard UI to simplify onboarding workflows and reduce support tickets.',
        'project_url' => '#',
        'image_path' => 'https://images.unsplash.com/photo-1518779578993-ec3579fee39f?auto=format&fit=crop&w=1200&q=80',
    ],
];

$projects = $realProjects;
if (count($projects) < 3) {
    $need = 3 - count($projects);
    shuffle($mockProjects);
    $projects = array_merge($projects, array_slice($mockProjects, 0, $need));
}

$hireLink = $isMockProfile ? 'chat.php' : ($isOwner ? 'chat.php' : ('chat.php?user_id=' . $targetUserId));

$currentPage = strtolower(trim((string) ($_GET['page'] ?? 'home')));
$allowedPages = ['home', 'about', 'projects', 'services', 'contact'];
if (!in_array($currentPage, $allowedPages, true)) {
    $currentPage = 'home';
}

$buildPageUrl = static function (string $page) use ($targetUserId, $isMockProfile, $mockQuery): string {
    $params = $isMockProfile
        ? array_merge($mockQuery, ['page' => $page])
        : ['user_id' => $targetUserId, 'page' => $page];
    return 'freelancer_portfolio.php?' . http_build_query($params);
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($name); ?> | Portfolio</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@300;400;500;600;700;800&family=Instrument+Serif:ital@1&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/devicons/devicon@latest/devicon.min.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        body {
            background: #020202;
            color: #fff;
            font-family: 'Barlow', sans-serif;
        }

        .portfolio-frame {
            border: 1px solid rgba(255, 255, 255, 0.14);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08);
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.03));
            backdrop-filter: blur(10px);
        }

        .hero-surface {
            background:
                radial-gradient(520px 240px at 20% 0%, rgba(129, 170, 255, 0.2), transparent 60%),
                radial-gradient(460px 220px at 100% 0%, rgba(89, 214, 200, 0.17), transparent 60%),
                linear-gradient(145deg, rgba(255, 255, 255, 0.06), rgba(255, 255, 255, 0.02));
        }

        .tab-link.active {
            background: rgba(255, 255, 255, 0.16);
            color: #fff;
            border-color: rgba(255, 255, 255, 0.28);
        }

        .tab-link {
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 999px;
            padding: 0.4rem 0.9rem;
            font-size: 0.82rem;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.78);
            background: rgba(255, 255, 255, 0.04);
        }

        .section-block {
            border: 1px solid rgba(255, 255, 255, 0.14);
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.03));
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(8px);
        }

        .project-card {
            border: 1px solid rgba(255, 255, 255, 0.14);
            background: rgba(255, 255, 255, 0.04);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.07);
        }

        .tool-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.55rem 0.85rem;
            border-radius: 0.55rem;
            border: 1px solid rgba(255, 255, 255, 0.18);
            background: rgba(255, 255, 255, 0.08);
            color: #f9fafb;
            font-size: 0.88rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            text-transform: uppercase;
        }

        .tool-chip i,
        .tool-chip .material-symbols-outlined {
            font-size: 1.02rem;
        }
    </style>
</head>
<body>
    <div class="page-bg"></div>
    <?php if ($sessionRole === 'provider'): ?>
        <?php renderProviderTopbar('hub', true, 'Search freelancers, skills, city'); ?>
    <?php elseif ($sessionRole === 'seeker'): ?>
        <?php renderSeekerTopbar('hiring'); ?>
    <?php else: ?>
        <header class="fixed top-0 left-0 right-0 z-50 bg-neutral-900 text-white h-20 border-b border-white/10 flex items-center justify-between px-6 md:px-10">
            <a href="dashboard.php" class="text-2xl font-heading italic">TalentSync</a>
            <a href="dashboard.php" class="text-white/70 hover:text-white text-sm">Back</a>
        </header>
    <?php endif; ?>

    <main class="pt-28 pb-16 px-4 md:px-8">
        <div class="max-w-6xl mx-auto space-y-10">
            <nav class="bg-white/5 border border-white/15 rounded-full px-4 py-2 shadow-sm flex flex-wrap items-center gap-2 w-fit mx-auto backdrop-blur-md">
                <a class="tab-link <?php echo $currentPage === 'home' ? 'active' : ''; ?>" href="<?php echo e($buildPageUrl('home')); ?>">Home</a>
                <a class="tab-link <?php echo $currentPage === 'about' ? 'active' : ''; ?>" href="<?php echo e($buildPageUrl('about')); ?>">About</a>
                <a class="tab-link <?php echo $currentPage === 'projects' ? 'active' : ''; ?>" href="<?php echo e($buildPageUrl('projects')); ?>">Projects</a>
                <a class="tab-link <?php echo $currentPage === 'services' ? 'active' : ''; ?>" href="<?php echo e($buildPageUrl('services')); ?>">Services</a>
                <a class="tab-link <?php echo $currentPage === 'contact' ? 'active' : ''; ?>" href="<?php echo e($buildPageUrl('contact')); ?>">Contact</a>
            </nav>

            <?php if ($currentPage === 'home'): ?>
            <section class="portfolio-frame hero-surface rounded-3xl p-6 md:p-10">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-center">
                    <div>
                        <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/12 text-xs font-semibold border border-white/20">Available for freelance work</span>
                        <h1 class="text-5xl md:text-6xl font-bold tracking-tight mt-4">Hi, I'm <?php echo e($name); ?></h1>
                        <p class="text-3xl font-heading italic mt-3"><?php echo e($tagline); ?><span class="animate-pulse">|</span></p>
                        <p class="text-white/70 mt-5 leading-7 max-w-xl"><?php echo e($bio); ?></p>

                        <div class="mt-6 flex flex-wrap gap-3 text-sm text-white/75">
                            <span class="inline-flex items-center gap-1"><span class="material-symbols-outlined" style="font-size:18px;">location_on</span><?php echo e($city); ?></span>
                            <span class="inline-flex items-center gap-1"><span class="material-symbols-outlined" style="font-size:18px;">verified</span><?php echo e($experience); ?></span>
                        </div>

                        <div class="mt-6 flex flex-wrap gap-3">
                            <a href="<?php echo e($hireLink); ?>" class="px-6 py-3 rounded-xl bg-white text-zinc-900 font-semibold">Hire Me</a>
                            <?php if ($resumePath !== ''): ?>
                                <a href="<?php echo e($resumePath); ?>" target="_blank" rel="noopener" class="px-6 py-3 rounded-xl border border-white/30 bg-white/10 text-white font-semibold">Download CV</a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div>
                        <img src="<?php echo e($avatar); ?>" alt="<?php echo e($name); ?> avatar" class="w-full max-w-md mx-auto rounded-3xl object-cover">
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <?php if ($currentPage === 'about'): ?>
            <section class="section-block rounded-2xl p-6 md:p-8">
                <h2 class="text-4xl font-bold">About</h2>
                <p class="mt-4 text-white/70 leading-8"><?php echo e($bio); ?></p>
                <div class="mt-5 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <article class="border border-white/15 rounded-xl p-4 bg-white/5">
                        <p class="text-xs uppercase tracking-wider text-white/50">Primary Skill</p>
                        <p class="font-semibold mt-1"><?php echo e((string) (($profile['skill'] ?? '') ?: 'Generalist')); ?></p>
                    </article>
                    <article class="border border-white/15 rounded-xl p-4 bg-white/5">
                        <p class="text-xs uppercase tracking-wider text-white/50">Experience</p>
                        <p class="font-semibold mt-1"><?php echo e($experience); ?></p>
                    </article>
                    <article class="border border-white/15 rounded-xl p-4 bg-white/5">
                        <p class="text-xs uppercase tracking-wider text-white/50">Location</p>
                        <p class="font-semibold mt-1"><?php echo e($city); ?></p>
                    </article>
                </div>
            </section>
            <?php endif; ?>

            <?php if ($currentPage === 'projects'): ?>
            <section class="section-block rounded-2xl p-6 md:p-8">
                <div class="flex items-center justify-between mb-5">
                    <h2 class="text-4xl font-bold">Projects</h2>
                    <?php if ($isOwner): ?>
                        <a href="profile.php" class="text-sm underline">Upload more from profile</a>
                    <?php endif; ?>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <?php foreach ($projects as $project): ?>
                        <?php
                            $projectTitle = (string) ($project['title'] ?? 'Untitled Project');
                            $projectDesc = (string) ($project['description'] ?? '');
                            $projectImg = trim((string) ($project['image_path'] ?? ''));
                            if ($projectImg === '') {
                                $projectImg = 'https://images.unsplash.com/photo-1518770660439-4636190af475?auto=format&fit=crop&w=1200&q=80';
                            }
                            $projectUrl = trim((string) ($project['project_url'] ?? '#'));
                        ?>
                        <article class="project-card rounded-xl overflow-hidden">
                            <img src="<?php echo e($projectImg); ?>" alt="<?php echo e($projectTitle); ?>" class="w-full h-44 object-cover border-b border-white/20">
                            <div class="p-5">
                                <h3 class="text-2xl font-semibold"><?php echo e($projectTitle); ?></h3>
                                <p class="text-white/70 mt-3 leading-7"><?php echo e($projectDesc); ?></p>
                                <?php if ($projectUrl !== '' && $projectUrl !== '#'): ?>
                                    <a href="<?php echo e($projectUrl); ?>" target="_blank" rel="noopener" class="inline-block mt-4 px-4 py-2 border border-white/30 bg-white/10 rounded-lg font-semibold">View project</a>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <?php if ($currentPage === 'services'): ?>
            <section class="section-block rounded-2xl p-6 md:p-8">
                <h2 class="text-4xl font-bold">Services & Tools</h2>
                <div class="mt-6 space-y-8">
                    <div>
                        <h3 class="text-2xl font-semibold mb-3">Services</h3>
                        <div class="space-y-2">
                            <?php foreach ($services as $service): ?>
                                <div class="border border-white/15 rounded-lg px-4 py-3 bg-white/5 font-medium"><?php echo e($service); ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-2xl font-semibold mb-3">Works & Tools</h3>
                        <div class="flex flex-wrap gap-2.5">
                            <?php foreach ($toolItems as $tool): ?>
                                <?php
                                    $toolName = trim((string) $tool);
                                    $iconClass = toolIconClass($toolName);
                                ?>
                                <div class="tool-chip" title="<?php echo e($toolName); ?>">
                                    <?php if ($iconClass !== null): ?>
                                        <i class="<?php echo e($iconClass); ?>" aria-hidden="true"></i>
                                    <?php else: ?>
                                        <span class="material-symbols-outlined" aria-hidden="true">terminal</span>
                                    <?php endif; ?>
                                    <span><?php echo e($toolName); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <?php if ($currentPage === 'contact'): ?>
            <section class="section-block rounded-2xl p-6 md:p-8">
                <h2 class="text-4xl font-bold">Contact</h2>
                <p class="text-white/70 mt-3">Interested in collaborating? Reach out directly or hire through TalentSync chat.</p>
                <div class="mt-6 flex flex-wrap gap-3">
                    <a href="<?php echo e($hireLink); ?>" class="px-6 py-3 rounded-xl bg-white text-zinc-900 font-semibold">Hire Me</a>
                    <?php if ($linkedin !== ''): ?>
                        <a href="<?php echo e($linkedin); ?>" target="_blank" rel="noopener" class="px-4 py-3 border border-white/30 rounded-xl bg-white/10 inline-flex items-center gap-2"><i class="devicon-linkedin-plain colored text-lg" aria-hidden="true"></i><span>LinkedIn</span></a>
                    <?php endif; ?>
                    <?php if ($github !== ''): ?>
                        <a href="<?php echo e($github); ?>" target="_blank" rel="noopener" class="px-4 py-3 border border-white/30 rounded-xl bg-white/10 inline-flex items-center gap-2"><i class="devicon-github-original text-lg" aria-hidden="true"></i><span>GitHub</span></a>
                    <?php endif; ?>
                    <?php if ($contactEmail !== ''): ?>
                        <a href="mailto:<?php echo e($contactEmail); ?>" class="px-4 py-3 border border-white/30 rounded-xl bg-white/10 inline-flex items-center gap-2"><span class="material-symbols-outlined" style="font-size:18px;">mail</span><span>Mail</span></a>
                    <?php endif; ?>
                </div>
            </section>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
