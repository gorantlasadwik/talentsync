<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/provider_topbar.php';

requireRole('provider');

$userId = currentUserId();
$message = '';

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS provider_locations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL UNIQUE,
        company_name VARCHAR(190) DEFAULT NULL,
        workplace_name VARCHAR(190) DEFAULT NULL,
        city VARCHAR(120) DEFAULT NULL,
        lat FLOAT DEFAULT NULL,
        lng FLOAT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_provider_location (lat, lng)
    )'
);

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_location'])) {
    $companyName = trim($_POST['company_name'] ?? '');
    $workplaceName = trim($_POST['workplace_name'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $lat = ($_POST['lat'] ?? '') !== '' ? (float) $_POST['lat'] : null;
    $lng = ($_POST['lng'] ?? '') !== '' ? (float) $_POST['lng'] : null;

    $upsert = $pdo->prepare(
        'INSERT INTO provider_locations (user_id, company_name, workplace_name, city, lat, lng)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            company_name = VALUES(company_name),
            workplace_name = VALUES(workplace_name),
            city = VALUES(city),
            lat = VALUES(lat),
            lng = VALUES(lng)'
    );
    $upsert->execute([$userId, $companyName, $workplaceName, $city, $lat, $lng]);
    $message = 'Company/workplace location saved.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_metrics'])) {
    $applicantsTotal = max(0, (int) ($_POST['applicants_total'] ?? 0));
    $interviewedTotal = max(0, (int) ($_POST['interviewed_total'] ?? 0));
    $hiredTotal = max(0, (int) ($_POST['hired_total'] ?? 0));
    $timeToHireDays = max(1, (int) ($_POST['time_to_hire_days'] ?? 18));
    $openPositionsTotal = max(0, (int) ($_POST['open_positions_total'] ?? 0));
    $uiDesignerPct = max(0, min(100, (int) ($_POST['ui_designer_pct'] ?? 52)));
    $marketingPct = max(0, min(100, (int) ($_POST['marketing_pct'] ?? 28)));
    $graphicDesignPct = max(0, min(100, (int) ($_POST['graphic_design_pct'] ?? 20)));

    $saveMetrics = $pdo->prepare(
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
    $saveMetrics->execute([
        $userId,
        $applicantsTotal,
        $interviewedTotal,
        $hiredTotal,
        $timeToHireDays,
        $openPositionsTotal,
        $uiDesignerPct,
        $marketingPct,
        $graphicDesignPct,
    ]);

    $message = 'Hiring board metrics saved.';
}

$locStmt = $pdo->prepare('SELECT company_name, workplace_name, city, lat, lng FROM provider_locations WHERE user_id = ? LIMIT 1');
$locStmt->execute([$userId]);
$location = $locStmt->fetch() ?: ['company_name' => '', 'workplace_name' => '', 'city' => '', 'lat' => '', 'lng' => ''];

$metricsStmt = $pdo->prepare(
    'SELECT applicants_total, interviewed_total, hired_total, time_to_hire_days,
            open_positions_total, ui_designer_pct, marketing_pct, graphic_design_pct
     FROM provider_hiring_metrics WHERE user_id = ? LIMIT 1'
);
$metricsStmt->execute([$userId]);
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

$formCity = trim((string) ($_GET['pick_city'] ?? (string) ($location['city'] ?? '')));
$formLat = trim((string) ($_GET['pick_lat'] ?? (string) ($location['lat'] ?? '')));
$formLng = trim((string) ($_GET['pick_lng'] ?? (string) ($location['lng'] ?? '')));

if (isset($_GET['pick_lat']) || isset($_GET['pick_lng']) || isset($_GET['pick_city'])) {
    $message = $message ?: 'Workplace location selected from map. Review and save.';
}

$mapPickerProviderUrl = 'location_picker.php?' . http_build_query([
    'return_to' => 'provider_location.php',
    'lat' => $formLat,
    'lng' => $formLng,
    'city' => $formCity,
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Provider Location</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body class="bg-[#05070b] text-white min-h-screen">
    <?php renderProviderTopbar('hub', false); ?>

    <div class="flex pt-20 min-h-screen">
    <aside class="fixed left-0 top-20 bottom-0 w-72 bg-[#090b0f] border-r border-white/10 p-6 overflow-y-auto">
        <h2 class="text-xl font-heading italic">Hiring Tools</h2>
        <nav class="mt-6 space-y-2 text-sm">
            <a class="block px-4 py-3 rounded-xl text-white/70 hover:bg-white/10" href="hiring_board.php">Hiring Board</a>
            <a class="block px-4 py-3 rounded-xl text-white/70 hover:bg-white/10" href="provider_dashboard.php">Provider Dashboard</a>
            <a class="block px-4 py-3 rounded-xl text-white/70 hover:bg-white/10" href="post_job.php">Post New Job</a>
            <a class="block px-4 py-3 rounded-xl bg-white/10 text-white" href="provider_location.php">Company Location</a>
            <a class="block px-4 py-3 rounded-xl text-white/70 hover:bg-white/10" href="map.php">Map View</a>
        </nav>
    </aside>

    <main class="ml-72 flex-1 p-8">
        <div class="max-w-4xl mx-auto liquid-glass rounded-3xl p-6 md:p-8">
            <div class="flex items-center justify-between gap-3">
                <h1 class="text-4xl font-heading italic">Company / Workplace Location</h1>
                <?php if ($message): ?><span class="text-sm text-white/80"><?php echo e($message); ?></span><?php endif; ?>
            </div>
            <p class="text-white/60 mt-2 text-sm">Set this once so seekers can see live distance to your workplace in map view.</p>

            <form method="post" class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                <input type="hidden" name="save_location" value="1">
                <input class="ui-input" type="text" name="company_name" value="<?php echo e((string) ($location['company_name'] ?? '')); ?>" placeholder="Company name">
                <input class="ui-input" type="text" name="workplace_name" value="<?php echo e((string) ($location['workplace_name'] ?? '')); ?>" placeholder="Workplace / Branch">
                <input id="city" class="ui-input" type="text" name="city" value="<?php echo e($formCity); ?>" placeholder="City / Place">
                <input id="lat" class="ui-input" type="text" name="lat" value="<?php echo e($formLat); ?>" placeholder="Latitude">
                <input id="lng" class="ui-input" type="text" name="lng" value="<?php echo e($formLng); ?>" placeholder="Longitude">

                <button id="captureLocationBtn" type="button" class="liquid-glass rounded-full px-4 py-3 text-sm">Use Current Location</button>
                <button class="liquid-glass-strong rounded-full px-4 py-3 text-sm" type="submit">Save Company Location</button>
                <a href="<?php echo e($mapPickerProviderUrl); ?>" class="md:col-span-2 text-center liquid-glass rounded-full px-4 py-3 text-sm">Select Location Using Map</a>
                <p id="locationHint" class="md:col-span-2 text-xs text-white/60">Use Current Location fills fields instantly. Select Location Using Map allows search suggestions and pin drop.</p>
            </form>

            <div id="hiring-metrics" class="mt-8 pt-7 border-t border-white/10">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-3xl font-heading italic">Hiring Board Metrics</h2>
                    <a href="hiring_board.php" class="text-sm text-white/70 hover:text-white">Open Hiring Board</a>
                </div>
                <p class="text-white/60 mt-2 text-sm">Edit these values to control the cards and charts shown on your hiring board.</p>

                <form method="post" class="mt-5 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <input type="hidden" name="save_metrics" value="1">
                    <input class="ui-input" type="number" min="0" name="applicants_total" value="<?php echo e((string) ((int) $metrics['applicants_total'])); ?>" placeholder="Applicants">
                    <input class="ui-input" type="number" min="0" name="interviewed_total" value="<?php echo e((string) ((int) $metrics['interviewed_total'])); ?>" placeholder="Interviewed">
                    <input class="ui-input" type="number" min="0" name="hired_total" value="<?php echo e((string) ((int) $metrics['hired_total'])); ?>" placeholder="Hired">
                    <input class="ui-input" type="number" min="1" name="time_to_hire_days" value="<?php echo e((string) ((int) $metrics['time_to_hire_days'])); ?>" placeholder="Time to hire (days)">
                    <input class="ui-input" type="number" min="0" name="open_positions_total" value="<?php echo e((string) ((int) $metrics['open_positions_total'])); ?>" placeholder="Open roles">
                    <div class="grid grid-cols-3 gap-2 md:col-span-3">
                        <input class="ui-input" type="number" min="0" max="100" name="ui_designer_pct" value="<?php echo e((string) ((int) $metrics['ui_designer_pct'])); ?>" placeholder="UI %">
                        <input class="ui-input" type="number" min="0" max="100" name="marketing_pct" value="<?php echo e((string) ((int) $metrics['marketing_pct'])); ?>" placeholder="Marketing %">
                        <input class="ui-input" type="number" min="0" max="100" name="graphic_design_pct" value="<?php echo e((string) ((int) $metrics['graphic_design_pct'])); ?>" placeholder="Graphic %">
                    </div>
                    <button class="liquid-glass-strong rounded-full px-4 py-3 text-sm md:col-span-3" type="submit">Save Hiring Board Metrics</button>
                </form>
            </div>
        </div>
    </main>
    </div>

    <script src="assets/js/app.js"></script>
</body>
</html>
