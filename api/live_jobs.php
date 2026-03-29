<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

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

function snippet(?string $text, int $max = 360): string
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

function isIndiaText(string $text, array $indiaKeywords): bool
{
    $hay = strtolower(trim($text));
    if ($hay === '') {
        return false;
    }

    foreach ($indiaKeywords as $keyword) {
        if (str_contains($hay, $keyword)) {
            return true;
        }
    }

    return false;
}

function buildClearbitLogo(string $jobUrl): string
{
    $host = parse_url($jobUrl, PHP_URL_HOST);
    if (!is_string($host) || $host === '') {
        return '';
    }

    $host = preg_replace('/^www\./i', '', $host);
    if (!$host) {
        return '';
    }

    return 'https://logo.clearbit.com/' . rawurlencode($host);
}

function companyDomainFromName(string $companyName): string
{
    $name = strtolower(trim($companyName));
    if ($name === '') {
        return '';
    }

    $known = [
        'google' => 'google.com',
        'youtube' => 'youtube.com',
        'microsoft' => 'microsoft.com',
        'amazon' => 'amazon.com',
        'meta' => 'meta.com',
        'facebook' => 'facebook.com',
        'apple' => 'apple.com',
        'netflix' => 'netflix.com',
        'spotify' => 'spotify.com',
        'airbnb' => 'airbnb.com',
        'uber' => 'uber.com',
        'linkedin' => 'linkedin.com',
        'adobe' => 'adobe.com',
        'oracle' => 'oracle.com',
        'ibm' => 'ibm.com',
        'nvidia' => 'nvidia.com',
        'tesla' => 'tesla.com',
    ];

    foreach ($known as $needle => $domain) {
        if (str_contains($name, $needle)) {
            return $domain;
        }
    }

    $slug = preg_replace('/[^a-z0-9]+/', '', $name);
    if (!$slug) {
        return '';
    }

    return $slug . '.com';
}

function buildCompanyLogo(string $companyName): string
{
    $domain = companyDomainFromName($companyName);
    if ($domain === '') {
        return '';
    }

    return 'https://logo.clearbit.com/' . rawurlencode($domain);
}

function resolveLogo(?string $apiLogo, string $jobUrl, string $companyName): string
{
    // Remotive logo endpoints often return a generic icon. Prefer company-based logo in that case.
    if (!empty($apiLogo) && !str_contains($apiLogo, 'remotive.com/job/')) {
        return $apiLogo;
    }

    $companyLogo = buildCompanyLogo($companyName);
    if ($companyLogo !== '') {
        return $companyLogo;
    }

    return buildClearbitLogo($jobUrl);
}

$jobs = [];

$internalJobs = $pdo->query('SELECT id, title, location, budget, payout_type, work_mode, experience_level, application_deadline, created_at FROM jobs ORDER BY created_at DESC LIMIT 20')->fetchAll();
foreach ($internalJobs as $job) {
    $jobs[] = [
        'id' => (int) $job['id'],
        'title' => $job['title'] ?? 'Untitled',
        'source' => 'TalentSync',
        'location' => $job['location'] ?? 'On-site',
        'company' => 'TalentSync',
        'url' => 'job_details.php?id=' . (int) $job['id'],
        'company_logo' => '',
        'description' => '',
        'budget' => $job['budget'] ?? '',
        'payout_type' => $job['payout_type'] ?? '',
        'work_mode' => $job['work_mode'] ?? '',
        'experience_level' => $job['experience_level'] ?? '',
        'application_deadline' => $job['application_deadline'] ?? null,
        'created_at' => $job['created_at'] ?? null,
    ];
}

// Preferred ingestion: per-source JobSpy exports.
$sourceJobs = loadJobsBySourceDir(__DIR__ . '/../data/jobs_by_source');

// Backward-compatible fallback combined file.
if (!$sourceJobs) {
    $sourceJobs = loadLocalJsonArray(__DIR__ . '/../data/custom_scraped_jobs.json');
}

if ($sourceJobs) {
    foreach ($sourceJobs as $job) {
        if (!is_array($job)) {
            continue;
        }

        $title = trim((string) ($job['title'] ?? ''));
        $url = trim((string) ($job['url'] ?? ''));
        if ($title === '' || $url === '') {
            continue;
        }

        $company = trim((string) ($job['company'] ?? 'N/A'));
        $apiLogo = trim((string) ($job['company_logo'] ?? ''));

        $jobs[] = [
            'title' => $title,
            'source' => trim((string) ($job['source'] ?? 'Custom Scraper')),
            'location' => trim((string) ($job['location'] ?? 'Remote')),
            'company' => $company !== '' ? $company : 'N/A',
            'url' => $url,
            'company_logo' => resolveLogo($apiLogo, $url, $company),
            'description' => snippet((string) ($job['description'] ?? '')),
            'budget' => trim((string) ($job['budget'] ?? '')),
            'payout_type' => trim((string) ($job['payout_type'] ?? '')),
            'work_mode' => trim((string) ($job['work_mode'] ?? '')),
            'experience_level' => trim((string) ($job['experience_level'] ?? '')),
            'created_at' => $job['created_at'] ?? null,
        ];
    }
}

if (!$jobs) {
    http_response_code(503);
    echo json_encode([
        'jobs' => [],
        'message' => 'No jobs available. Run integrations/JobSpy/fetch_jobs_to_json.py to refresh JobSpy listings.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$indiaKeywords = [
    'india',
    'bangalore',
    'bengaluru',
    'hyderabad',
    'pune',
    'chennai',
    'mumbai',
    'delhi',
    'gurgaon',
    'gurugram',
    'noida',
    'kolkata',
    'kochi',
    'ahmedabad',
    'remote india',
    'india remote',
];

$deduped = [];
foreach ($jobs as $job) {
    $urlKey = strtolower(trim((string) ($job['url'] ?? '')));
    if ($urlKey !== '') {
        $key = 'url|' . $urlKey;
    } else {
        $key = 'meta|' .
            strtolower(trim((string) ($job['title'] ?? ''))) . '|' .
            strtolower(trim((string) ($job['company'] ?? ''))) . '|' .
            strtolower(trim((string) ($job['location'] ?? ''))) . '|' .
            strtolower(trim((string) ($job['source'] ?? '')));
    }

    if ($key === 'meta||||' || isset($deduped[$key])) {
        continue;
    }

    $deduped[$key] = $job;
}
$jobs = array_values($deduped);

usort($jobs, static function (array $a, array $b) use ($indiaKeywords): int {
    $aLocation = (string) ($a['location'] ?? '');
    $bLocation = (string) ($b['location'] ?? '');
    $aTitle = (string) ($a['title'] ?? '');
    $bTitle = (string) ($b['title'] ?? '');
    $aDescription = (string) ($a['description'] ?? '');
    $bDescription = (string) ($b['description'] ?? '');
    $aSource = strtolower((string) ($a['source'] ?? ''));
    $bSource = strtolower((string) ($b['source'] ?? ''));

    $aText = $aLocation . ' ' . $aTitle . ' ' . $aDescription;
    $bText = $bLocation . ' ' . $bTitle . ' ' . $bDescription;

    $aIndiaScore = 0;
    $bIndiaScore = 0;

    if (isIndiaText($aText, $indiaKeywords)) {
        $aIndiaScore += 100;
    }
    if (isIndiaText($bText, $indiaKeywords)) {
        $bIndiaScore += 100;
    }

    if (str_contains($aSource, 'naukri')) {
        $aIndiaScore += 50;
    }
    if (str_contains($bSource, 'naukri')) {
        $bIndiaScore += 50;
    }

    if (str_contains(strtolower($aLocation), 'remote')) {
        $aIndiaScore += 8;
    }
    if (str_contains(strtolower($bLocation), 'remote')) {
        $bIndiaScore += 8;
    }

    if ($aIndiaScore !== $bIndiaScore) {
        return $bIndiaScore <=> $aIndiaScore;
    }

    $aTime = strtotime((string) ($a['created_at'] ?? '')) ?: 0;
    $bTime = strtotime((string) ($b['created_at'] ?? '')) ?: 0;
    return $bTime <=> $aTime;
});

echo json_encode(['jobs' => $jobs], JSON_UNESCAPED_UNICODE);
