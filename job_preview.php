<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/seeker_topbar.php';
require_once __DIR__ . '/includes/provider_topbar.php';

requireLogin();

$title = trim((string) ($_GET['title'] ?? 'Untitled Job'));
$company = trim((string) ($_GET['company'] ?? 'Unknown Company'));
$location = trim((string) ($_GET['location'] ?? 'Not specified'));
$source = trim((string) ($_GET['source'] ?? 'API'));
$applyUrl = trim((string) ($_GET['url'] ?? ''));
$description = trim((string) ($_GET['description'] ?? ''));

if ($description === '') {
    $description = 'Description not provided in source listing.';
}

function previewSnippet(?string $text, int $max = 260): string
{
    $clean = trim(strip_tags((string) $text));
    if ($clean === '') {
        return '';
    }
    if (mb_strlen($clean) <= $max) {
        return $clean;
    }
    return mb_substr($clean, 0, $max - 1) . '...';
}

function extractDomainFromUrl(string $rawUrl): string
{
    $host = (string) (parse_url(trim($rawUrl), PHP_URL_HOST) ?? '');
    $host = strtolower(trim($host));
    return preg_replace('/^www\./i', '', $host) ?: '';
}

function companyDomainFromNamePreview(string $companyName): string
{
    $name = strtolower(trim($companyName));
    if ($name === '') {
        return '';
    }

    $known = [
        'cisco' => 'cisco.com',
        'discord' => 'discord.com',
        'nasdaq' => 'nasdaq.com',
        'google' => 'google.com',
        'microsoft' => 'microsoft.com',
        'amazon' => 'amazon.com',
        'meta' => 'meta.com',
        'apple' => 'apple.com',
        'netflix' => 'netflix.com',
        'spotify' => 'spotify.com',
        'uber' => 'uber.com',
        'adobe' => 'adobe.com',
        'oracle' => 'oracle.com',
        'ibm' => 'ibm.com',
        'nvidia' => 'nvidia.com',
        'tesla' => 'tesla.com',
        'linkedin' => 'linkedin.com',
    ];

    foreach ($known as $needle => $domain) {
        if (str_contains($name, $needle)) {
            return $domain;
        }
    }

    $slug = preg_replace('/[^a-z0-9]+/i', '', $name) ?: '';
    return $slug !== '' ? ($slug . '.com') : '';
}

function resolveCardLogoPreview(array $job): string
{
    $existing = trim((string) ($job['company_logo'] ?? ''));
    if ($existing !== '') {
        return $existing;
    }

    $domain = companyDomainFromNamePreview((string) ($job['company'] ?? ''));
    if ($domain === '') {
        $domain = extractDomainFromUrl((string) ($job['url'] ?? ''));
    }
    if ($domain === '') {
        return '';
    }

    return 'api/company_logo.php?domain=' . rawurlencode($domain);
}

function hashToRgb(string $text, int $index): string
{
    $seed = (int) sprintf('%u', crc32($text . '|' . (string) $index));
    $h = $seed % 360;
    $s = 58;
    $l = 50;

    $c = (1 - abs((2 * $l / 100) - 1)) * ($s / 100);
    $x = $c * (1 - abs(fmod(($h / 60), 2) - 1));
    $m = ($l / 100) - ($c / 2);

    $r = 0.0;
    $g = 0.0;
    $b = 0.0;
    if ($h < 60) {
        $r = $c; $g = $x; $b = 0;
    } elseif ($h < 120) {
        $r = $x; $g = $c; $b = 0;
    } elseif ($h < 180) {
        $r = 0; $g = $c; $b = $x;
    } elseif ($h < 240) {
        $r = 0; $g = $x; $b = $c;
    } elseif ($h < 300) {
        $r = $x; $g = 0; $b = $c;
    } else {
        $r = $c; $g = 0; $b = $x;
    }

    return implode(',', [
        (string) round(($r + $m) * 255),
        (string) round(($g + $m) * 255),
        (string) round(($b + $m) * 255),
    ]);
}

function companyPalettePreview(array $job, int $index): string
{
    $brandByDomain = [
        'cisco.com' => '30,98,193',
        'discord.com' => '88,101,242',
        'nasdaq.com' => '0,168,181',
        'google.com' => '66,133,244',
        'microsoft.com' => '0,120,215',
        'amazon.com' => '255,153,0',
        'meta.com' => '24,119,242',
        'apple.com' => '148,163,184',
        'netflix.com' => '229,9,20',
        'spotify.com' => '30,215,96',
        'airbnb.com' => '255,90,95',
        'uber.com' => '46,46,46',
        'adobe.com' => '255,0,0',
        'salesforce.com' => '0,161,224',
        'linkedin.com' => '10,102,194',
    ];

    $domain = companyDomainFromNamePreview((string) ($job['company'] ?? ''));
    if ($domain === '') {
        $domain = extractDomainFromUrl((string) ($job['url'] ?? ''));
    }
    if ($domain !== '' && isset($brandByDomain[$domain])) {
        return $brandByDomain[$domain];
    }

    $text = trim((string) ($job['company'] ?? ''));
    if ($text === '') {
        $text = trim((string) ($job['title'] ?? ''));
    }
    return hashToRgb($text, $index);
}

function getJobIconPreview(string $title): array
{
    $t = strtolower($title);

    if (preg_match('/(ui|ux|design|designer|graphic|artist|illustrator|brand)/', $t)) {
        return ['icon' => 'palette', 'bg' => '255,145,102'];
    }
    if (preg_match('/(editor|content|writer|copy|proofread|review)/', $t)) {
        return ['icon' => 'edit_note', 'bg' => '255,173,79'];
    }
    if (preg_match('/(developer|engineer|software|full stack|frontend|backend|devops|qa|sre)/', $t)) {
        return ['icon' => 'laptop_mac', 'bg' => '96,165,250'];
    }
    if (preg_match('/(data|analyst|analytics|scientist|ml|ai)/', $t)) {
        return ['icon' => 'query_stats', 'bg' => '56,189,248'];
    }
    if (preg_match('/(support|customer|service|helpdesk)/', $t)) {
        return ['icon' => 'support_agent', 'bg' => '167,139,250'];
    }
    if (preg_match('/(marketing|seo|growth|social|community)/', $t)) {
        return ['icon' => 'campaign', 'bg' => '251,113,133'];
    }
    if (preg_match('/(manager|lead|director|coordinator|operations)/', $t)) {
        return ['icon' => 'manage_accounts', 'bg' => '52,211,153'];
    }

    return ['icon' => 'work', 'bg' => '148,163,184'];
}

function fetchJsonPreview(string $url): ?array
{
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'header' => "User-Agent: TalentSyncPro/1.0\r\n",
        ],
    ]);

    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        return null;
    }

    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : null;
}

function loadLocalJsonArray(string $absolutePath): array
{
    if (!is_file($absolutePath)) {
        return [];
    }

    $raw = @file_get_contents($absolutePath);
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function loadJobsBySourceDir(string $absoluteDir): array
{
    if (!is_dir($absoluteDir)) {
        return [];
    }

    $files = glob(rtrim($absoluteDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'jobs_*.json');
    if (!is_array($files) || !$files) {
        return [];
    }

    $merged = [];
    foreach ($files as $file) {
        $rows = loadLocalJsonArray($file);
        if (!$rows) {
            continue;
        }
        foreach ($rows as $row) {
            if (is_array($row)) {
                $merged[] = $row;
            }
        }
    }

    return $merged;
}

function cleanText(string $text): string
{
    $normalized = preg_replace('/<br\s*\/?>/i', "\n", $text) ?? $text;
    $normalized = strip_tags($normalized);
    $normalized = html_entity_decode($normalized, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $normalized = preg_replace('/\r\n?|\x{2028}|\x{2029}/u', "\n", $normalized) ?? $normalized;
    return trim($normalized);
}

function extractBulletCandidates(string $text): array
{
    $lines = preg_split('/\n+/', cleanText($text)) ?: [];
    $out = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        $line = preg_replace('/^[\-\*•●▪◦\d\)\.\s]+/u', '', $line) ?? $line;
        $line = trim($line);
        if (mb_strlen($line) < 18) {
            continue;
        }
        $out[] = $line;
    }
    return array_values(array_unique($out));
}

function splitSentences(string $text): array
{
    $clean = cleanText($text);
    if ($clean === '') {
        return [];
    }
    $parts = preg_split('/(?<=[\.!?])\s+/u', $clean) ?: [];
    $out = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if (mb_strlen($part) >= 30) {
            $out[] = $part;
        }
    }
    return array_values(array_unique($out));
}

function extractSectionLines(string $description, array $headings, array $stopHeadings): array
{
    $text = cleanText($description);
    if ($text === '') {
        return [];
    }

    $headingPart = implode('|', array_map(static fn($h) => preg_quote($h, '/'), $headings));
    $stopPart = implode('|', array_map(static fn($h) => preg_quote($h, '/'), $stopHeadings));
    $pattern = '/(?:^|\n)\s*(?:' . $headingPart . ')\s*:?\s*(.*?)(?=(?:\n\s*(?:' . $stopPart . ')\s*:?)|\z)/isu';

    if (!preg_match($pattern, $text, $m)) {
        return [];
    }

    return extractBulletCandidates((string) ($m[1] ?? ''));
}

function buildPreviewLists(string $description): array
{
    $sectionResponsibilities = extractSectionLines(
        $description,
        ['Responsibilities', 'Roles & Responsibilities', 'Role Responsibilities', 'What you will do'],
        ['Requirements', 'Qualifications', 'Skills', 'Technologies', 'We offer', 'Benefits']
    );
    $sectionRequirements = extractSectionLines(
        $description,
        ['Requirements', 'Qualifications', 'Required Skills', 'Professional & Technical Skills', 'Must have skills'],
        ['Responsibilities', 'Technologies', 'We offer', 'Benefits', 'Additional Information']
    );

    if ($sectionResponsibilities || $sectionRequirements) {
        return [
            array_slice(array_values(array_unique($sectionResponsibilities)), 0, 6),
            array_slice(array_values(array_unique($sectionRequirements)), 0, 6),
        ];
    }

    $candidates = array_merge(extractBulletCandidates($description), splitSentences($description));

    $respKeywords = '/\b(build|design|develop|implement|collaborate|lead|deliver|own|maintain|create|drive|support|deploy|optimi[sz]e|review|triage|monitor|test)\b/i';
    $reqKeywords = '/\b(experience|degree|bachelor|master|years|proficient|knowledge|familiar|skills?|qualification|required|must|ability|strong|understanding|expertise|certification)\b/i';

    $responsibilities = [];
    $requirements = [];

    foreach ($candidates as $line) {
        if (preg_match($reqKeywords, $line)) {
            $requirements[] = $line;
        } elseif (preg_match($respKeywords, $line)) {
            $responsibilities[] = $line;
        }
    }

    if (count($responsibilities) < 3) {
        foreach ($candidates as $line) {
            if (count($responsibilities) >= 4) {
                break;
            }
            if (!in_array($line, $requirements, true) && !in_array($line, $responsibilities, true)) {
                $responsibilities[] = $line;
            }
        }
    }

    if (count($requirements) < 3) {
        foreach ($candidates as $line) {
            if (count($requirements) >= 4) {
                break;
            }
            if (!in_array($line, $responsibilities, true) && !in_array($line, $requirements, true)) {
                $requirements[] = $line;
            }
        }
    }

    return [
        array_slice(array_values(array_unique($responsibilities)), 0, 4),
        array_slice(array_values(array_unique($requirements)), 0, 4),
    ];
}

function normalizeJobUrl(string $url): string
{
    $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if ($url === '') {
        return '';
    }

    $parts = parse_url($url);
    if (!is_array($parts)) {
        return rtrim($url, '/');
    }

    $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
    $host = strtolower((string) ($parts['host'] ?? ''));
    $path = (string) ($parts['path'] ?? '');
    $query = (string) ($parts['query'] ?? '');

    if ($host === '') {
        return rtrim($url, '/');
    }

    $normalized = $scheme . '://' . $host . rtrim($path, '/');
    if ($query !== '') {
        parse_str($query, $queryArray);
        if (is_array($queryArray) && $queryArray) {
            // Drop common tracking params to improve URL matching.
            foreach (array_keys($queryArray) as $key) {
                if (preg_match('/^(utm_|ref|src|source|trk|tracking)/i', (string) $key)) {
                    unset($queryArray[$key]);
                }
            }
            if ($queryArray) {
                ksort($queryArray);
                $normalized .= '?' . http_build_query($queryArray);
            }
        }
    }

    return $normalized;
}

function findCachedJob(string $applyUrl, string $title, string $company, string $location = '', string $source = ''): ?array
{
    // Prefer the combined cache because it is the canonical merged source.
    $sourceRows = loadLocalJsonArray(__DIR__ . '/data/custom_scraped_jobs.json');
    if (!$sourceRows) {
        $sourceRows = loadJobsBySourceDir(__DIR__ . '/data/jobs_by_source');
    }

    if (!$sourceRows) {
        return null;
    }

    $targetUrl = normalizeJobUrl($applyUrl);
    $targetTitle = mb_strtolower(trim($title));
    $targetCompany = mb_strtolower(trim($company));
    $targetLocation = mb_strtolower(trim($location));
    $targetSource = mb_strtolower(trim($source));

    $bestMatch = null;
    $bestScore = -1;

    foreach ($sourceRows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $rowUrl = normalizeJobUrl((string) ($row['url'] ?? ''));
        if ($targetUrl !== '' && $rowUrl !== '' && $rowUrl === $targetUrl) {
            return $row;
        }

        $rowTitle = mb_strtolower(trim((string) ($row['title'] ?? '')));
        $rowCompany = mb_strtolower(trim((string) ($row['company'] ?? '')));
        $rowLocation = mb_strtolower(trim((string) ($row['location'] ?? '')));
        $rowSource = mb_strtolower(trim((string) ($row['source'] ?? '')));

        $score = 0;
        if ($targetTitle !== '' && $rowTitle === $targetTitle) {
            $score += 3;
        }
        if ($targetCompany !== '' && $rowCompany === $targetCompany) {
            $score += 3;
        }
        if ($targetLocation !== '' && $rowLocation === $targetLocation) {
            $score += 2;
        }
        if ($targetSource !== '' && $rowSource === $targetSource) {
            $score += 1;
        }

        if ($score > $bestScore) {
            $bestScore = $score;
            $bestMatch = $row;
        }
    }

    // Require at least title+company match quality to avoid wrong descriptions.
    if ($bestScore >= 6 && is_array($bestMatch)) {
        return $bestMatch;
    }

    return null;
}

$jobType = 'Not specified';
$level = 'Not specified';
$salary = 'Not specified';

$cachedJob = findCachedJob($applyUrl, $title, $company, $location, $source);
if ($cachedJob) {
    $title = trim((string) ($cachedJob['title'] ?? $title));
    $company = trim((string) ($cachedJob['company'] ?? $company));
    $location = trim((string) ($cachedJob['location'] ?? $location));
    $source = trim((string) ($cachedJob['source'] ?? $source));
    $applyUrl = trim((string) ($cachedJob['url'] ?? $applyUrl));

    $cachedDescription = trim((string) ($cachedJob['description'] ?? ''));
    if ($cachedDescription !== '') {
        $description = cleanText($cachedDescription);
    }

    $salaryCandidate = trim((string) ($cachedJob['budget'] ?? ''));
    if ($salaryCandidate !== '') {
        $salary = $salaryCandidate;
    }

    $jobTypeCandidate = trim((string) ($cachedJob['job_type'] ?? ''));
    if ($jobTypeCandidate !== '') {
        $jobType = $jobTypeCandidate;
    }

    $levelCandidate = trim((string) ($cachedJob['experience_level'] ?? ''));
    if ($levelCandidate !== '') {
        $level = $levelCandidate;
    }
}

[$responsibilities, $requirements] = buildPreviewLists($description);

if (!$responsibilities) {
    $responsibilities = [
        'Responsibilities are not explicitly listed in the source description.',
    ];
}

if (!$requirements) {
    $requirements = [
        'Requirements are not explicitly listed in the source description.',
    ];
}

$similarJobs = [];

$internalStmt = $pdo->prepare('SELECT id, title, description, location FROM jobs WHERE title <> ? ORDER BY created_at DESC LIMIT 6');
$internalStmt->execute([$title]);
foreach ($internalStmt->fetchAll() as $j) {
    $similarJobs[] = [
        'title' => (string) ($j['title'] ?? 'Untitled'),
        'company' => 'TalentSync',
        'source' => 'TalentSync',
        'location' => (string) ($j['location'] ?? 'On-site'),
        'description' => previewSnippet((string) ($j['description'] ?? '')),
        'url' => 'job_details.php?id=' . (int) $j['id'],
        'company_logo' => '',
    ];
}

if (count($similarJobs) < 6) {
    $remotive = fetchJsonPreview('https://remotive.com/api/remote-jobs');
    if (!empty($remotive['jobs']) && is_array($remotive['jobs'])) {
        foreach ($remotive['jobs'] as $job) {
            if (count($similarJobs) >= 6) {
                break;
            }

            $jobTitle = (string) ($job['title'] ?? 'Untitled');
            if (strcasecmp($jobTitle, $title) === 0) {
                continue;
            }

            $similarJobs[] = [
                'title' => $jobTitle,
                'company' => (string) ($job['company_name'] ?? 'N/A'),
                'source' => 'Remotive API',
                'location' => (string) ($job['candidate_required_location'] ?? 'Remote'),
                'description' => previewSnippet((string) ($job['description'] ?? '')),
                'url' => (string) ($job['url'] ?? ''),
                'company_logo' => (string) ($job['company_logo_url'] ?? ''),
            ];
        }
    }
}

$similarJobs = array_slice($similarJobs, 0, 6);
$isProviderView = (string) ($_SESSION['role'] ?? '') === 'provider';
$providerTab = trim((string) ($_GET['from'] ?? 'hub'));
if (!in_array($providerTab, ['hub', 'messages', 'pipeline', 'hiring'], true)) {
    $providerTab = 'hub';
}
$dashboardLink = $isProviderView ? 'provider_dashboard.php' : 'seeker_dashboard.php';
$dashboardLabel = $isProviderView ? 'Back to Provider Hub' : 'Browse More Jobs';

$sidebarTitle = $isProviderView ? 'Quick Access' : 'Filters';
$sidebarSubtitle = $isProviderView ? 'Navigate hiring workflow' : 'Refine this search';
$sidebarLinks = $isProviderView
        ? [
                ['label' => 'Provider Hub', 'href' => 'provider_dashboard.php', 'active' => true],
                ['label' => 'Hiring Board', 'href' => 'hiring_board.php', 'active' => false],
                ['label' => 'Company Pipeline', 'href' => 'company_pipeline.php?provider_id=' . (int) currentUserId(), 'active' => false],
                ['label' => 'Nearby Talent', 'href' => 'map.php', 'active' => false],
            ]
        : [
                ['label' => 'Find Jobs', 'href' => 'seeker_dashboard.php', 'active' => true],
                ['label' => 'Hiring', 'href' => 'aggregator.php', 'active' => false],
                ['label' => 'Map View', 'href' => 'map.php', 'active' => false],
                ['label' => 'Inbox', 'href' => 'notifications.php', 'active' => false],
            ];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($title); ?> | Job Preview</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .preview-hero {
            background:
                radial-gradient(500px 240px at 90% 0%, rgba(120, 170, 255, 0.2), transparent 60%),
                radial-gradient(460px 260px at 0% 100%, rgba(255, 155, 120, 0.14), transparent 60%),
                linear-gradient(180deg, #0b0e13 0%, #07090d 100%);
        }
        .preview-chip {
            background: rgba(255, 255, 255, 0.14);
            border: 1px solid rgba(255, 255, 255, 0.18);
        }
        .job-tile {
            min-width: 300px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 1rem;
            transition: transform .2s ease, box-shadow .2s ease;
        }
        .job-tile:hover {
            transform: translateY(-2px);
            box-shadow: 0 18px 30px rgba(0, 0, 0, 0.28);
        }
        .hide-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        .hide-scrollbar::-webkit-scrollbar {
            display: none;
        }
    </style>
</head>
<body class="bg-[#05070b] text-white min-h-screen overflow-x-hidden">
    <?php if (!$isProviderView) { renderSeekerTopbar('jobs'); } else { renderProviderTopbar($providerTab, true, 'Search roles, skills, location'); } ?>

    <div class="pt-20 min-h-screen">
        <aside class="hidden lg:block fixed left-0 top-20 bottom-0 w-72 bg-[#090b0f] border-r border-white/10 p-6 overflow-y-auto">
            <h2 class="text-xl font-heading italic"><?php echo e($sidebarTitle); ?></h2>
            <p class="text-xs text-white/55 mt-1"><?php echo e($sidebarSubtitle); ?></p>
            <nav class="mt-6 space-y-2 text-sm">
                <?php foreach ($sidebarLinks as $link): ?>
                    <a class="block px-4 py-3 rounded-xl <?php echo !empty($link['active']) ? 'bg-white/10 text-white' : 'text-white/70 hover:bg-white/10'; ?>" href="<?php echo e((string) $link['href']); ?>"><?php echo e((string) $link['label']); ?></a>
                <?php endforeach; ?>
            </nav>
        </aside>

        <main class="w-full bg-[#05070b] min-h-screen lg:ml-72 lg:w-[calc(100%-18rem)]">
            <header class="preview-hero relative overflow-hidden px-6 md:px-12 lg:px-16 pt-14 pb-24">
                <div class="max-w-5xl mx-auto flex flex-col md:flex-row justify-between items-start md:items-end gap-8">
                    <div class="space-y-4">
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="preview-chip text-white px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wide">Job Preview</span>
                            <span class="text-white/70 text-xs flex items-center gap-1"><span class="material-symbols-outlined" style="font-size:16px;">location_on</span><?php echo e($location); ?></span>
                        </div>
                        <h1 class="text-4xl md:text-5xl font-heading italic leading-tight"><?php echo e($title); ?></h1>
                        <p class="text-white/75 text-lg"><?php echo e($company); ?></p>
                    </div>
                    <?php if ($applyUrl !== ''): ?>
                        <a href="<?php echo e($applyUrl); ?>" target="_blank" rel="noopener" class="liquid-glass-strong rounded-xl px-8 py-4 font-semibold text-base">Apply Now</a>
                    <?php else: ?>
                        <span class="liquid-glass rounded-xl px-8 py-4 font-semibold text-base text-white/60">No Apply Link</span>
                    <?php endif; ?>
                </div>
            </header>

            <div class="sticky top-20 z-40 bg-[#0f141b]/85 backdrop-blur-xl border-y border-white/10">
                <div class="max-w-5xl mx-auto px-6 md:px-12 lg:px-16 py-5 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="liquid-glass rounded-2xl p-4 flex items-center gap-3">
                        <span class="material-symbols-outlined">payments</span>
                        <div>
                            <p class="text-[11px] uppercase tracking-wide text-white/60">Salary</p>
                            <p class="font-semibold"><?php echo e($salary); ?></p>
                        </div>
                    </div>
                    <div class="liquid-glass rounded-2xl p-4 flex items-center gap-3">
                        <span class="material-symbols-outlined">schedule</span>
                        <div>
                            <p class="text-[11px] uppercase tracking-wide text-white/60">Job Type</p>
                            <p class="font-semibold"><?php echo e($jobType); ?></p>
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

            <section class="max-w-5xl mx-auto px-6 md:px-12 lg:px-16 py-12 grid grid-cols-1 lg:grid-cols-3 gap-10">
                <div class="lg:col-span-2 space-y-10">
                    <article class="liquid-glass rounded-3xl p-6 md:p-8">
                        <h2 class="text-2xl font-heading italic mb-4">About the Role</h2>
                        <p class="text-white/80 leading-8 whitespace-pre-line"><?php echo e($description); ?></p>
                    </article>

                    <article class="liquid-glass rounded-3xl p-6 md:p-8">
                        <h2 class="text-2xl font-heading italic mb-5">Responsibilities</h2>
                        <ul class="space-y-4">
                            <?php foreach ($responsibilities as $item): ?>
                                <li class="flex gap-3"><span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1;">check_circle</span><span class="text-white/80"><?php echo e($item); ?></span></li>
                            <?php endforeach; ?>
                        </ul>
                    </article>

                    <article class="liquid-glass rounded-3xl p-6 md:p-8">
                        <h2 class="text-2xl font-heading italic mb-5">Requirements</h2>
                        <ul class="space-y-4">
                            <?php foreach ($requirements as $item): ?>
                                <li class="flex gap-3"><span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1;">verified</span><span class="text-white/80"><?php echo e($item); ?></span></li>
                            <?php endforeach; ?>
                        </ul>
                    </article>
                </div>

                <aside class="space-y-6">
                    <div class="liquid-glass rounded-3xl p-6">
                        <h3 class="font-semibold text-lg mb-2"><?php echo e($company); ?></h3>
                        <p class="text-sm text-white/70 mb-5"><?php echo e($source); ?> listing</p>
                        <div class="space-y-3">
                            <a href="javascript:history.back()" class="block text-center liquid-glass rounded-xl px-4 py-3 text-sm font-semibold">Back</a>
                            <a href="<?php echo e($dashboardLink); ?>" class="block text-center liquid-glass rounded-xl px-4 py-3 text-sm font-semibold"><?php echo e($dashboardLabel); ?></a>
                            <?php if ($applyUrl !== ''): ?>
                                <a href="<?php echo e($applyUrl); ?>" target="_blank" rel="noopener" class="block text-center liquid-glass-strong rounded-xl px-4 py-3 text-sm font-semibold">Apply Now</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </aside>
            </section>

            <section class="px-6 md:px-12 lg:px-16 pb-14">
                <div class="max-w-5xl mx-auto">
                    <div class="flex justify-between items-center mb-8">
                        <h2 class="text-3xl font-heading italic">Similar Jobs</h2>
                        <a href="<?php echo e($dashboardLink); ?>" class="text-sm text-white/70 hover:text-white">View all</a>
                    </div>
                    <div class="hide-scrollbar flex gap-5 overflow-x-auto pb-3">
                        <?php if (!$similarJobs): ?>
                            <article class="job-tile p-6" style="background: linear-gradient(145deg, rgba(255,255,255,0.24), rgba(255,255,255,0.06));">
                                <div class="w-12 h-12 rounded-xl bg-white/85 text-black flex items-center justify-center mb-5"><span class="material-symbols-outlined">work</span></div>
                                <h3 class="text-lg font-semibold mb-2 leading-tight">No similar jobs available right now</h3>
                                <p class="text-sm text-white/75 mb-5">Try refreshing or checking the dashboard feed.</p>
                                <a href="<?php echo e($dashboardLink); ?>" class="inline-block bg-black/70 border border-white/20 rounded-lg px-4 py-2 text-sm">Go to Dashboard</a>
                            </article>
                        <?php endif; ?>
                        <?php foreach ($similarJobs as $idx => $job): ?>
                            <?php
                                $rgb = companyPalettePreview($job, (int) $idx);
                                $logo = resolveCardLogoPreview($job);
                                $roleIcon = getJobIconPreview((string) ($job['title'] ?? ''));
                                $previewUrl = 'job_preview.php?' . http_build_query([
                                    'title' => $job['title'],
                                    'company' => $job['company'],
                                    'location' => $job['location'],
                                    'source' => $job['source'],
                                    'url' => $job['url'],
                                    'description' => $job['description'],
                                ]);
                            ?>
                            <article class="job-tile p-6" style="--brand-rgb:<?php echo e($rgb); ?>; background:linear-gradient(145deg, rgba(var(--brand-rgb),0.34), rgba(var(--brand-rgb),0.14) 45%, rgba(255,255,255,0.06) 100%); border:1px solid rgba(var(--brand-rgb),0.45)">
                                <div class="flex justify-between items-start mb-5">
                                    <div class="w-12 h-12 rounded-xl bg-white/90 text-black flex items-center justify-center shadow-sm">
                                        <?php if ($logo !== ''): ?>
                                            <img src="<?php echo e($logo); ?>" alt="<?php echo e((string) ($job['company'] ?? 'Company')); ?> logo" class="w-8 h-8 object-contain" loading="lazy" decoding="async" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-block';">
                                            <span class="material-symbols-outlined" style="display:none; font-size:28px; color:rgb(<?php echo e((string) $roleIcon['bg']); ?>)"><?php echo e((string) $roleIcon['icon']); ?></span>
                                        <?php else: ?>
                                            <span class="material-symbols-outlined" style="font-size:28px; color:rgb(<?php echo e((string) $roleIcon['bg']); ?>)"><?php echo e((string) $roleIcon['icon']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="preview-chip px-3 py-1 rounded-full text-[10px] uppercase">Open</span>
                                </div>
                                <h3 class="text-lg font-semibold mb-1 leading-tight"><?php echo e($job['title']); ?></h3>
                                <p class="text-sm text-white/75 mb-5"><?php echo e($job['company']); ?></p>
                                <a href="<?php echo e($previewUrl); ?>" class="inline-block bg-black/70 border border-white/20 rounded-lg px-4 py-2 text-sm">View</a>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <footer class="bg-[#070a0f] border-t border-white/10 px-6 md:px-12 lg:px-16 py-10">
                <div class="max-w-5xl mx-auto flex flex-col md:flex-row justify-between items-center gap-6 text-sm text-white/60">
                    <span class="text-white text-lg font-heading italic">TalentSync</span>
                    <div class="flex gap-6">
                        <a href="dashboard.php" class="hover:text-white">Dashboard</a>
                        <a href="notifications.php" class="hover:text-white">Notifications</a>
                        <a href="chat.php" class="hover:text-white">Support</a>
                    </div>
                    <span>TalentSync Pro</span>
                </div>
            </footer>
        </main>
    </div>
</body>
</html>
