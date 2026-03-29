<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/seeker_topbar.php';

requireRole('seeker');

$userId = currentUserId();
$message = '';

try {
    $pdo->exec('ALTER TABLE freelancers ADD COLUMN city VARCHAR(120) DEFAULT NULL');
} catch (Throwable $e) {
    // Ignore when the column already exists.
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
        // Ignore when the column already exists.
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
    $skill = trim($_POST['skill'] ?? '');
    $experience = trim($_POST['experience'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $gender = trim((string) ($_POST['gender'] ?? ''));
    $age = ($_POST['age'] ?? '') !== '' ? max(0, (int) $_POST['age']) : null;
    $linkedinUrl = trim((string) ($_POST['linkedin_url'] ?? ''));
    $githubUrl = trim((string) ($_POST['github_url'] ?? ''));
    $contactEmail = trim((string) ($_POST['contact_email'] ?? ''));
    $services = trim((string) ($_POST['services'] ?? ''));
    $tools = trim((string) ($_POST['tools'] ?? ''));
    $portfolioTagline = trim((string) ($_POST['portfolio_tagline'] ?? ''));
    $lat = $_POST['lat'] !== '' ? (float) $_POST['lat'] : null;
    $lng = $_POST['lng'] !== '' ? (float) $_POST['lng'] : null;

    $imagePath = null;
    if (!empty($_FILES['img']['name']) && is_uploaded_file($_FILES['img']['tmp_name'])) {
        $file = $_FILES['img'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
            $avatarDir = __DIR__ . '/uploads/avatars';
            if (!is_dir($avatarDir)) {
                mkdir($avatarDir, 0775, true);
            }

            $targetRaw = 'uploads/avatars/' . uniqid('avatar_', true) . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], __DIR__ . '/' . $targetRaw)) {
                $imagePath = $targetRaw;
                $canResize = function_exists('imagescale')
                    && (
                        ($ext === 'png' && function_exists('imagecreatefrompng') && function_exists('imagepng'))
                        || ($ext !== 'png' && function_exists('imagecreatefromjpeg') && function_exists('imagejpeg'))
                    );

                if ($canResize) {
                    $img = $ext === 'png'
                        ? imagecreatefrompng(__DIR__ . '/' . $targetRaw)
                        : imagecreatefromjpeg(__DIR__ . '/' . $targetRaw);

                    if ($img) {
                        $resized = imagescale($img, 200, 200);
                        if ($resized) {
                            $saved = false;
                            if ($ext === 'png') {
                                imagealphablending($resized, false);
                                imagesavealpha($resized, true);
                                $targetResized = 'uploads/avatars/' . uniqid('avatar_resized_', true) . '.png';
                                $saved = imagepng($resized, __DIR__ . '/' . $targetResized, 6);
                            } else {
                                $targetResized = 'uploads/avatars/' . uniqid('avatar_resized_', true) . '.jpg';
                                $saved = imagejpeg($resized, __DIR__ . '/' . $targetResized, 90);
                            }

                            imagedestroy($resized);
                            if ($saved) {
                                @unlink(__DIR__ . '/' . $targetRaw);
                                $imagePath = $targetResized;
                            }
                        }

                        imagedestroy($img);
                    }
                }
            }
        }
    }

    $resumePath = null;
    if (!empty($_FILES['resume']['name']) && is_uploaded_file($_FILES['resume']['tmp_name'])) {
        $type = mime_content_type($_FILES['resume']['tmp_name']) ?: '';
        if ($type === 'application/pdf') {
            $resumePath = 'uploads/resumes/' . uniqid('resume_', true) . '.pdf';
            move_uploaded_file($_FILES['resume']['tmp_name'], __DIR__ . '/' . $resumePath);
        } else {
            $message = 'Only PDF allowed for resume.';
        }
    }

    $existingStmt = $pdo->prepare('SELECT image_path, resume_path FROM freelancers WHERE user_id = ? LIMIT 1');
    $existingStmt->execute([$userId]);
    $existing = $existingStmt->fetch() ?: ['image_path' => null, 'resume_path' => null];

    $cleanupFreelancerDupes = $pdo->prepare(
        'DELETE f_old
         FROM freelancers f_old
         JOIN freelancers f_new
           ON f_old.user_id = f_new.user_id
          AND f_old.id < f_new.id
         WHERE f_old.user_id = ?'
    );
    $cleanupFreelancerDupes->execute([$userId]);

    $ensureFreelancer = $pdo->prepare(
        'INSERT INTO freelancers (user_id)
         SELECT ?
         WHERE NOT EXISTS (
             SELECT 1 FROM freelancers WHERE user_id = ?
         )'
    );
    $ensureFreelancer->execute([$userId, $userId]);

    $imagePath = $imagePath ?: $existing['image_path'];
    $resumePath = $resumePath ?: $existing['resume_path'];

    $stmt = $pdo->prepare(
        'UPDATE freelancers
         SET skill = ?, experience = ?, bio = ?, city = ?, image_path = ?, resume_path = ?, lat = ?, lng = ?,
             gender = ?, age = ?, linkedin_url = ?, github_url = ?, contact_email = ?, services = ?, tools = ?, portfolio_tagline = ?
         WHERE user_id = ?'
    );
    $stmt->execute([
        $skill,
        $experience,
        $bio,
        $city,
        $imagePath,
        $resumePath,
        $lat,
        $lng,
        $gender !== '' ? $gender : null,
        $age,
        $linkedinUrl !== '' ? $linkedinUrl : null,
        $githubUrl !== '' ? $githubUrl : null,
        $contactEmail !== '' ? $contactEmail : null,
        $services !== '' ? $services : null,
        $tools !== '' ? $tools : null,
        $portfolioTagline !== '' ? $portfolioTagline : null,
        $userId,
    ]);

    $message = $message ?: 'Profile updated successfully.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_project'])) {
    $projectTitle = trim((string) ($_POST['project_title'] ?? ''));
    $projectDescription = trim((string) ($_POST['project_description'] ?? ''));
    $projectUrl = trim((string) ($_POST['project_url'] ?? ''));

    if ($projectTitle === '') {
        $message = 'Project title is required.';
    } else {
        $projectImagePath = null;
        if (!empty($_FILES['project_image']['name']) && is_uploaded_file($_FILES['project_image']['tmp_name'])) {
            $ext = strtolower(pathinfo((string) $_FILES['project_image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                $projectDir = __DIR__ . '/uploads/projects';
                if (!is_dir($projectDir)) {
                    mkdir($projectDir, 0775, true);
                }
                $projectImagePath = 'uploads/projects/' . uniqid('project_', true) . '.' . $ext;
                move_uploaded_file($_FILES['project_image']['tmp_name'], __DIR__ . '/' . $projectImagePath);
            }
        }

        $insertProject = $pdo->prepare(
            'INSERT INTO freelancer_projects (user_id, title, description, project_url, image_path)
             VALUES (?, ?, ?, ?, ?)'
        );
        $insertProject->execute([
            $userId,
            $projectTitle,
            $projectDescription !== '' ? $projectDescription : null,
            $projectUrl !== '' ? $projectUrl : null,
            $projectImagePath,
        ]);

        $message = 'Project added to portfolio.';
    }
}

$stmt = $pdo->prepare(
    'SELECT u.name, f.skill, f.experience, f.bio, f.city, f.image_path, f.resume_path, f.lat, f.lng,
            f.gender, f.age, f.linkedin_url, f.github_url, f.contact_email, f.services, f.tools, f.portfolio_tagline
     FROM users u LEFT JOIN freelancers f ON f.user_id = u.id WHERE u.id = ? LIMIT 1'
);
$stmt->execute([$userId]);
$profile = $stmt->fetch();
$avatar = avatarUrl(
    (string) ($profile['name'] ?? 'Freelancer'),
    isset($profile['image_path']) ? (string) $profile['image_path'] : null,
    isset($profile['gender']) ? (string) $profile['gender'] : null,
    isset($profile['age']) && $profile['age'] !== null ? (int) $profile['age'] : null
);

$projectsStmt = $pdo->prepare('SELECT id, title, description, project_url, image_path, created_at FROM freelancer_projects WHERE user_id = ? ORDER BY created_at DESC LIMIT 20');
$projectsStmt->execute([$userId]);
$portfolioProjects = $projectsStmt->fetchAll();

$unreadMsgStmt = $pdo->prepare(
    'SELECT COUNT(*)
     FROM messages m
     JOIN users u ON u.id = m.sender_id
     WHERE m.receiver_id = ? AND u.role = ? AND m.is_read = 0'
);
$unreadMsgStmt->execute([$userId, 'provider']);
$unreadMessages = (int) $unreadMsgStmt->fetchColumn();

$bookmarksStmt = $pdo->prepare('SELECT COUNT(*) FROM bookmarks WHERE seeker_id = ?');
$bookmarksStmt->execute([$userId]);
$savedProfiles = (int) $bookmarksStmt->fetchColumn();

$totalMessagesStmt = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE sender_id = ? OR receiver_id = ?');
$totalMessagesStmt->execute([$userId, $userId]);
$totalMessages = (int) $totalMessagesStmt->fetchColumn();

$notificationStmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
$notificationStmt->execute([$userId]);
$pendingNotifications = (int) $notificationStmt->fetchColumn();

$profileViewsStmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND (LOWER(title) LIKE ? OR LOWER(body) LIKE ?)');
$profileViewsStmt->execute([$userId, '%view%', '%view%']);
$profileViews = (int) $profileViewsStmt->fetchColumn();
if ($profileViews === 0) {
    $profileViews = max(28, ($totalMessages * 2) + ($savedProfiles * 5) + ($pendingNotifications * 3));
}

$formCity = trim((string) ($_GET['pick_city'] ?? (string) ($profile['city'] ?? '')));
$formLat = trim((string) ($_GET['pick_lat'] ?? (string) ($profile['lat'] ?? '')));
$formLng = trim((string) ($_GET['pick_lng'] ?? (string) ($profile['lng'] ?? '')));

if (isset($_GET['pick_lat']) || isset($_GET['pick_lng']) || isset($_GET['pick_city'])) {
    $message = $message ?: 'Location selected from map. Review and save profile.';
}

$mapPickerProfileUrl = 'location_picker.php?' . http_build_query([
    'return_to' => 'profile.php',
    'lat' => $formLat,
    'lng' => $formLng,
    'city' => $formCity,
]);

$profileFields = [
    trim((string) ($profile['skill'] ?? '')),
    trim((string) ($profile['experience'] ?? '')),
    trim((string) ($profile['bio'] ?? '')),
    $formCity,
    $formLat,
    $formLng,
    trim((string) ($profile['image_path'] ?? '')),
    trim((string) ($profile['resume_path'] ?? '')),
];
$filledFields = 0;
foreach ($profileFields as $field) {
    if ($field !== '') {
        $filledFields++;
    }
}
$profileScore = (int) round(($filledFields / count($profileFields)) * 100);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .profile-shell {
            background:
                radial-gradient(660px 260px at 98% -10%, rgba(121, 245, 247, 0.14), transparent 60%),
                radial-gradient(580px 260px at 2% 0%, rgba(246, 149, 103, 0.14), transparent 60%),
                #05070b;
        }
        .soft-card {
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 1rem;
        }
        .metric-1 { background: linear-gradient(145deg, rgba(125, 175, 255, 0.26), rgba(255, 255, 255, 0.06)); }
        .metric-2 { background: linear-gradient(145deg, rgba(121, 245, 247, 0.2), rgba(255, 255, 255, 0.05)); }
        .metric-3 { background: linear-gradient(145deg, rgba(246, 149, 103, 0.22), rgba(255, 255, 255, 0.05)); }
        .metric-4 { background: linear-gradient(145deg, rgba(255, 255, 255, 0.12), rgba(255, 255, 255, 0.04)); }
        .trend-col {
            background: linear-gradient(to top, rgba(255, 255, 255, 0.24), rgba(255, 255, 255, 0.08));
        }
        .trend-col.active {
            background: linear-gradient(to top, rgba(121, 245, 247, 0.95), rgba(121, 245, 247, 0.45));
            box-shadow: 0 12px 28px rgba(121, 245, 247, 0.26);
        }
    </style>
</head>
<body class="profile-shell text-white min-h-screen">
    <?php renderSeekerTopbar('jobs'); ?>

    <div class="flex pt-20 min-h-screen">
    <aside class="fixed left-0 top-20 bottom-0 w-72 bg-[#090b0f] border-r border-white/10 p-6 overflow-y-auto">
        <h2 class="text-xl font-heading italic">Account</h2>
        <nav class="mt-6 space-y-2 text-sm">
            <a class="block px-4 py-3 rounded-xl bg-white/10 text-white" href="profile.php">Edit Profile</a>
            <a class="block px-4 py-3 rounded-xl text-white/70 hover:bg-white/10" href="map.php">Location</a>
            <a class="block px-4 py-3 rounded-xl text-white/70 hover:bg-white/10" href="notifications.php">Notifications</a>
        </nav>
    </aside>

    <main class="ml-72 flex-1 p-8">
    <div class="max-w-7xl mx-auto space-y-8">
        <section class="flex flex-col md:flex-row md:items-end justify-between gap-5">
            <div>
                <h1 class="text-4xl font-heading italic">Welcome back, <?php echo e((string) $profile['name']); ?>!</h1>
                <p class="text-white/70 mt-2">Manage your profile, uploads, and location with a single workspace.</p>
            </div>
            <div class="liquid-glass rounded-xl px-4 py-3 flex items-center gap-2">
                <span class="relative flex h-3 w-3">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-300"></span>
                </span>
                <span class="text-sm font-medium">Profile visibility active</span>
            </div>
        </section>

        <section class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">
            <div class="soft-card metric-1 p-5">
                <div class="flex items-center justify-between">
                    <span class="material-symbols-outlined">mail</span>
                    <span class="text-xs bg-white/20 px-2 py-1 rounded-full">Live</span>
                </div>
                <p class="text-3xl font-bold mt-4"><?php echo e((string) $unreadMessages); ?></p>
                <p class="text-sm text-white/75 mt-1">Unread Client Messages</p>
            </div>
            <div class="soft-card metric-2 p-5">
                <div class="flex items-center justify-between">
                    <span class="material-symbols-outlined">visibility</span>
                    <span class="text-xs bg-white/20 px-2 py-1 rounded-full">Views</span>
                </div>
                <p class="text-3xl font-bold mt-4"><?php echo e((string) $profileViews); ?></p>
                <p class="text-sm text-white/75 mt-1">Profile Views</p>
                <button type="button" id="viewInsightsBtn" class="inline-block mt-3 text-xs px-3 py-1.5 rounded-full bg-white/20 hover:bg-white/30">View Insights</button>
            </div>
            <div class="soft-card metric-3 p-5">
                <div class="flex items-center justify-between">
                    <span class="material-symbols-outlined">chat_bubble</span>
                    <span class="text-xs bg-white/20 px-2 py-1 rounded-full">Saved</span>
                </div>
                <p class="text-3xl font-bold mt-4"><?php echo e((string) $savedProfiles); ?></p>
                <p class="text-sm text-white/75 mt-1">Bookmarked Talent</p>
            </div>
            <div class="soft-card metric-4 p-5">
                <div class="flex items-center justify-between">
                    <span class="material-symbols-outlined">chat</span>
                    <span class="text-xs bg-white/20 px-2 py-1 rounded-full">Total</span>
                </div>
                <p class="text-3xl font-bold mt-4"><?php echo e((string) $totalMessages); ?></p>
                <p class="text-sm text-white/75 mt-1">Message Threads</p>
            </div>
        </section>

        <section id="marketTrendsSection" class="liquid-glass rounded-3xl p-6 md:p-8 transition-all duration-300">
            <div class="flex items-center justify-between mb-7">
                <div>
                    <h3 class="text-2xl font-heading italic">Market Trends</h3>
                    <p class="text-sm text-white/65 mt-1">Hiring activity trend around your skill profile.</p>
                </div>
                <button class="text-sm px-4 py-2 rounded-full bg-white/10 border border-white/15">Last 6 Months</button>
            </div>

            <div class="h-64 flex items-end justify-between gap-3 md:gap-4 px-2">
                <div class="flex-1 flex flex-col items-center gap-2">
                    <div class="trend-col w-full rounded-t-lg h-24"></div>
                    <span class="text-xs text-white/60 font-semibold">JAN</span>
                </div>
                <div class="flex-1 flex flex-col items-center gap-2">
                    <div class="trend-col w-full rounded-t-lg h-32"></div>
                    <span class="text-xs text-white/60 font-semibold">FEB</span>
                </div>
                <div class="flex-1 flex flex-col items-center gap-2">
                    <div class="trend-col w-full rounded-t-lg h-44"></div>
                    <span class="text-xs text-white/60 font-semibold">MAR</span>
                </div>
                <div class="flex-1 flex flex-col items-center gap-2">
                    <div class="trend-col active w-full rounded-t-lg h-56 relative">
                        <div class="absolute -top-9 left-1/2 -translate-x-1/2 text-[10px] px-2 py-1 rounded-md bg-[#0c0f10] text-white/90">Peak</div>
                    </div>
                    <span class="text-xs text-cyan-200 font-semibold">APR</span>
                </div>
                <div class="flex-1 flex flex-col items-center gap-2">
                    <div class="trend-col w-full rounded-t-lg h-40"></div>
                    <span class="text-xs text-white/60 font-semibold">MAY</span>
                </div>
                <div class="flex-1 flex flex-col items-center gap-2">
                    <div class="trend-col w-full rounded-t-lg h-48"></div>
                    <span class="text-xs text-white/60 font-semibold">JUN</span>
                </div>
            </div>
        </section>

        <section class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            <div class="xl:col-span-2 liquid-glass rounded-3xl p-6 md:p-8">
                <div class="flex items-center justify-between gap-4 mb-5">
                    <h2 class="text-2xl font-heading italic">Edit Profile</h2>
                    <?php if ($message): ?><p class="text-sm text-white/80"><?php echo e($message); ?></p><?php endif; ?>
                </div>

                <form method="post" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <input type="hidden" name="save_profile" value="1">
                    <div class="md:col-span-2 flex items-center gap-4">
                        <img class="w-20 h-20 rounded-full object-cover border border-white/20" src="<?php echo e($avatar); ?>" alt="avatar">
                        <div>
                            <p class="text-sm text-white/70">Avatar auto-fallback uses portfolio character style. PNG uploads keep transparent backgrounds.</p>
                            <a class="text-xs text-white underline block mt-1" href="freelancer_portfolio.php?user_id=<?php echo (int) $userId; ?>" target="_blank">Preview Public Portfolio</a>
                            <?php if (!empty($profile['resume_path'])): ?>
                                <a class="text-xs text-white underline" href="<?php echo e($profile['resume_path']); ?>" target="_blank">View uploaded resume</a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <input class="ui-input" type="text" name="skill" value="<?php echo e((string) ($profile['skill'] ?? '')); ?>" placeholder="Primary skill">
                    <input class="ui-input" type="text" name="experience" value="<?php echo e((string) ($profile['experience'] ?? '')); ?>" placeholder="Experience (e.g., 3 years)">
                    <select class="ui-select" name="gender">
                        <option value="">Gender (optional)</option>
                        <option value="male" <?php echo (($profile['gender'] ?? '') === 'male') ? 'selected' : ''; ?>>Male</option>
                        <option value="female" <?php echo (($profile['gender'] ?? '') === 'female') ? 'selected' : ''; ?>>Female</option>
                        <option value="other" <?php echo (($profile['gender'] ?? '') === 'other') ? 'selected' : ''; ?>>Other</option>
                    </select>
                    <input class="ui-input" type="number" min="0" max="100" name="age" value="<?php echo e((string) (($profile['age'] ?? '') ?: '')); ?>" placeholder="Age">
                    <input id="city" class="ui-input" type="text" name="city" value="<?php echo e($formCity); ?>" placeholder="City / Place (auto-filled from location)">
                    <input id="lat" class="ui-input" type="text" name="lat" value="<?php echo e($formLat); ?>" placeholder="Latitude">
                    <input id="lng" class="ui-input" type="text" name="lng" value="<?php echo e($formLng); ?>" placeholder="Longitude">
                    <input class="ui-input" type="url" name="linkedin_url" value="<?php echo e((string) ($profile['linkedin_url'] ?? '')); ?>" placeholder="LinkedIn profile URL">
                    <input class="ui-input" type="url" name="github_url" value="<?php echo e((string) ($profile['github_url'] ?? '')); ?>" placeholder="GitHub profile URL">
                    <input class="ui-input md:col-span-2" type="email" name="contact_email" value="<?php echo e((string) ($profile['contact_email'] ?? '')); ?>" placeholder="Public contact email">
                    <input class="ui-input md:col-span-2" type="text" name="portfolio_tagline" value="<?php echo e((string) ($profile['portfolio_tagline'] ?? '')); ?>" placeholder="Portfolio headline (e.g., Frontend Developer)">
                    <textarea class="md:col-span-2 ui-textarea" name="bio" rows="4" placeholder="Short bio"><?php echo e((string) ($profile['bio'] ?? '')); ?></textarea>
                    <textarea class="md:col-span-2 ui-textarea" name="services" rows="3" placeholder="Services (comma separated, e.g., UI Design, Landing Pages, React Development)"><?php echo e((string) ($profile['services'] ?? '')); ?></textarea>
                    <textarea class="md:col-span-2 ui-textarea" name="tools" rows="3" placeholder="Tools/skills (comma separated, e.g., Photoshop, PowerPoint, Figma, React)"><?php echo e((string) ($profile['tools'] ?? '')); ?></textarea>

                    <div>
                        <label class="block text-xs text-white/70 mb-2">Profile Image (JPG/PNG)</label>
                        <input class="ui-file" type="file" name="img" accept=".jpg,.jpeg,.png">
                    </div>
                    <div>
                        <label class="block text-xs text-white/70 mb-2">Resume (PDF only)</label>
                        <input class="ui-file" type="file" name="resume" accept="application/pdf">
                    </div>

                    <button id="captureLocationBtn" type="button" class="liquid-glass rounded-full px-4 py-3 text-sm">Use Current Location</button>
                    <a href="<?php echo e($mapPickerProfileUrl); ?>" class="text-center liquid-glass rounded-full px-4 py-3 text-sm">Select Location Using Map</a>
                    <button class="md:col-span-2 liquid-glass-strong rounded-full px-4 py-3 text-sm" type="submit">Save Profile</button>
                    <p id="locationHint" class="md:col-span-2 text-xs text-white/60">Use Current Location fills fields instantly. Select Location Using Map lets you search places (e.g., Hyderabad Railway Station) or drop a pin.</p>
                </form>

                <div class="mt-7 pt-6 border-t border-white/10">
                    <h3 class="text-xl font-heading italic mb-4">Add Portfolio Project</h3>
                    <form method="post" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <input type="hidden" name="add_project" value="1">
                        <input class="ui-input" type="text" name="project_title" placeholder="Project title" required>
                        <input class="ui-input" type="url" name="project_url" placeholder="Project URL (optional)">
                        <textarea class="md:col-span-2 ui-textarea" name="project_description" rows="3" placeholder="Project description"></textarea>
                        <div class="md:col-span-2">
                            <label class="block text-xs text-white/70 mb-2">Project image (JPG/PNG/WEBP)</label>
                            <input class="ui-file" type="file" name="project_image" accept=".jpg,.jpeg,.png,.webp">
                        </div>
                        <button class="md:col-span-2 liquid-glass rounded-full px-4 py-3 text-sm" type="submit">Add Project</button>
                    </form>

                    <div class="mt-5 grid grid-cols-1 md:grid-cols-2 gap-3">
                        <?php if (!$portfolioProjects): ?>
                            <p class="text-sm text-white/60 md:col-span-2">No projects added yet.</p>
                        <?php endif; ?>
                        <?php foreach ($portfolioProjects as $project): ?>
                            <article class="liquid-glass rounded-2xl p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <h4 class="font-semibold truncate"><?php echo e((string) ($project['title'] ?? 'Untitled Project')); ?></h4>
                                    <span class="text-[11px] text-white/55"><?php echo e((string) date('M d, Y', strtotime((string) ($project['created_at'] ?? 'now')))); ?></span>
                                </div>
                                <p class="text-xs text-white/65 mt-2"><?php echo e((string) ($project['description'] ?? '')); ?></p>
                                <?php if (!empty($project['project_url'])): ?>
                                    <a class="inline-block mt-3 text-xs underline text-white/80" href="<?php echo e((string) $project['project_url']); ?>" target="_blank" rel="noopener">Open project</a>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="liquid-glass rounded-3xl p-6 text-center">
                    <h3 class="text-lg font-semibold mb-4">Profile Completeness</h3>
                    <div class="relative w-36 h-36 mx-auto mb-4">
                        <svg class="w-full h-full transform -rotate-90" viewBox="0 0 160 160">
                            <circle cx="80" cy="80" r="70" stroke="rgba(255,255,255,0.15)" stroke-width="10" fill="none"></circle>
                            <circle cx="80" cy="80" r="70" stroke="rgba(121,245,247,0.95)" stroke-width="10" fill="none" stroke-linecap="round" stroke-dasharray="439.82" stroke-dashoffset="<?php echo e((string) (439.82 - (439.82 * $profileScore / 100))); ?>"></circle>
                        </svg>
                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                            <span class="text-3xl font-bold"><?php echo e((string) $profileScore); ?>%</span>
                            <span class="text-[11px] text-white/60 uppercase tracking-wider">Complete</span>
                        </div>
                    </div>
                    <p class="text-sm text-white/70">Complete profile fields and uploads to rank higher in searches.</p>
                </div>

                <div class="liquid-glass rounded-3xl p-6">
                    <h3 class="text-lg font-semibold mb-3">Quick Actions</h3>
                    <div class="space-y-3 text-sm">
                        <a href="map.php" class="block liquid-glass rounded-xl px-4 py-3">Update Location Preferences</a>
                        <a href="notifications.php" class="block liquid-glass rounded-xl px-4 py-3">Check Notifications</a>
                        <a href="chat.php" class="block liquid-glass rounded-xl px-4 py-3">Open Messages</a>
                        <a href="freelancer_portfolio.php?user_id=<?php echo (int) $userId; ?>" class="block liquid-glass rounded-xl px-4 py-3">View Public Portfolio</a>
                        <a href="seeker_dashboard.php" class="block liquid-glass rounded-xl px-4 py-3">Back to Dashboard</a>
                    </div>
                </div>
            </div>
        </section>
    </div>
    </main>
    </div>

    <script src="assets/js/app.js"></script>
    <script>
        (function () {
            var btn = document.getElementById('viewInsightsBtn');
            var trends = document.getElementById('marketTrendsSection');
            if (!btn || !trends) {
                return;
            }

            btn.addEventListener('click', function () {
                trends.scrollIntoView({ behavior: 'smooth', block: 'start' });
                trends.classList.add('ring-2', 'ring-cyan-300/60');
                setTimeout(function () {
                    trends.classList.remove('ring-2', 'ring-cyan-300/60');
                }, 1200);
            });
        })();
    </script>
</body>
</html>
