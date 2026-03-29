<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['message' => 'Method not allowed.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['message' => 'Login required.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SESSION['role'] ?? '') !== 'seeker') {
    http_response_code(403);
    echo json_encode(['message' => 'Only seekers can trigger fetch.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$csrfToken = (string) ($_POST['csrf_token'] ?? '');
if (!verifyCsrf($csrfToken)) {
    http_response_code(419);
    echo json_encode(['message' => 'Invalid CSRF token. Refresh page and try again.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$city = trim((string) ($_POST['city'] ?? ''));
$queryType = trim((string) ($_POST['query_type'] ?? 'auto'));
$field = trim((string) ($_POST['field'] ?? ''));
$jobType = trim((string) ($_POST['job_type'] ?? ''));
$country = trim((string) ($_POST['country'] ?? 'India'));
$sitesRaw = trim((string) ($_POST['sites'] ?? 'indeed,linkedin,naukri'));

if (!in_array($queryType, ['auto', 'location', 'company'], true)) {
    $queryType = 'auto';
}

$treatPrimaryAsCompany = false;
if ($city !== '') {
    if ($queryType === 'company') {
        $treatPrimaryAsCompany = true;
    } elseif ($queryType === 'auto' && $field === '') {
        $treatPrimaryAsCompany = true;
    }
}

$locationForFetch = ($city !== '' && !$treatPrimaryAsCompany) ? $city : $country;
$locationListForFetch = ($city !== '' && !$treatPrimaryAsCompany) ? $city : '';

if ($city !== '' && (mb_strlen($city) < 2 || mb_strlen($city) > 80)) {
    http_response_code(422);
    echo json_encode(['message' => 'Please provide a valid city/place/company value.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($city === '' && $field === '' && $jobType === '') {
    http_response_code(422);
    echo json_encode(['message' => 'Please provide at least a role or city/place/company value.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$validPattern = '/^[a-zA-Z0-9 ,.()\-]+$/';
foreach ([$city, $field, $jobType, $country, $sitesRaw] as $value) {
    if ($value !== '' && !preg_match($validPattern, $value)) {
        http_response_code(422);
        echo json_encode(['message' => 'Input contains unsupported characters.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$searchTermParts = [];
if ($field !== '') {
    $searchTermParts[] = $field;
}
if ($city !== '' && $treatPrimaryAsCompany) {
    $searchTermParts[] = $city;
}
if ($jobType !== '') {
    $searchTermParts[] = $jobType;
}
$searchTerm = trim(implode(' ', $searchTermParts));
if ($searchTerm === '') {
    $searchTerm = 'software engineer';
}

$allowedSites = ['indeed', 'linkedin', 'naukri', 'google', 'zip_recruiter', 'glassdoor', 'bayt', 'bdjobs'];
$siteParts = array_values(array_filter(array_map('trim', explode(',', strtolower($sitesRaw))), static fn($s) => $s !== ''));
$siteParts = array_values(array_intersect($siteParts, $allowedSites));
if (!$siteParts) {
    $siteParts = ['indeed', 'linkedin'];
}
$sites = implode(',', $siteParts);

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    http_response_code(500);
    echo json_encode(['message' => 'Could not resolve project root.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$scriptPath = $root . DIRECTORY_SEPARATOR . 'integrations' . DIRECTORY_SEPARATOR . 'JobSpy' . DIRECTORY_SEPARATOR . 'fetch_jobs_to_json.py';
if (!is_file($scriptPath)) {
    http_response_code(500);
    echo json_encode(['message' => 'JobSpy fetch script not found.'], JSON_UNESCAPED_UNICODE);
    exit;
}

function resolvePythonBins(string $root): array
{
    $localAppData = getenv('LOCALAPPDATA') ?: '';
    $candidates = [
        getenv('JOBSPY_PYTHON') ?: '',
        'C:\\Users\\sadwi\\AppData\\Local\\Programs\\Python\\Python313\\python.exe',
        $localAppData !== ''
            ? $localAppData . DIRECTORY_SEPARATOR . 'Programs' . DIRECTORY_SEPARATOR . 'Python' . DIRECTORY_SEPARATOR . 'Python313' . DIRECTORY_SEPARATOR . 'python.exe'
            : '',
        $root . DIRECTORY_SEPARATOR . '.venv' . DIRECTORY_SEPARATOR . 'Scripts' . DIRECTORY_SEPARATOR . 'python.exe',
        $root . DIRECTORY_SEPARATOR . '.venv' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'python',
        'python',
    ];

    $resolved = [];
    $seen = [];
    foreach ($candidates as $candidate) {
        if ($candidate === '') {
            continue;
        }
        $key = strtolower($candidate);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;

        if ($candidate === 'python' || is_file($candidate)) {
            $resolved[] = $candidate;
        }
    }

    if (!$resolved) {
        return ['python'];
    }

    return $resolved;
}

function runFetchProcess(string $pythonBin, string $scriptPath, string $root): array
{
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $command = '"' . str_replace('"', '\\"', $pythonBin) . '" "' . str_replace('"', '\\"', $scriptPath) . '"';
    $process = proc_open($command, $descriptors, $pipes, $root, null);
    if (!is_resource($process)) {
        return [
            'exit_code' => 999,
            'stdout' => '',
            'stderr' => 'Could not start process for Python: ' . $pythonBin,
        ];
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]) ?: '';
    $stderr = stream_get_contents($pipes[2]) ?: '';
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    return [
        'exit_code' => (int) $exitCode,
        'stdout' => $stdout,
        'stderr' => $stderr,
    ];
}

$pythonBins = resolvePythonBins($root);
$jobspyEnv = [
    'JOBSPY_SEARCH_TERM' => $searchTerm,
    'JOBSPY_LOCATION' => $locationForFetch,
    'JOBSPY_LOCATION_LIST' => $locationListForFetch,
    'JOBSPY_COUNTRY_LIST' => $country,
    'JOBSPY_COUNTRY_INDEED' => $country,
    'JOBSPY_SITES' => $sites,
    'JOBSPY_STRICT_COUNTRY' => 'true',
    'JOBSPY_RESULTS_WANTED' => '40',
    'JOBSPY_BATCHES' => '1',
    'JOBSPY_SKIP_CAPTCHA_SITES' => 'true',
    'JOBSPY_VERBOSE' => '1',
];

foreach ($jobspyEnv as $key => $value) {
    putenv($key . '=' . $value);
    $_ENV[$key] = $value;
}

set_time_limit(300);
$stdout = '';
$stderr = '';
$exitCode = 1;
$usedPython = '';
$attemptErrors = [];

foreach ($pythonBins as $pythonBin) {
    $result = runFetchProcess($pythonBin, $scriptPath, $root);
    $stdout = (string) ($result['stdout'] ?? '');
    $stderr = (string) ($result['stderr'] ?? '');
    $exitCode = (int) ($result['exit_code'] ?? 1);

    if ($exitCode === 0) {
        $usedPython = $pythonBin;
        break;
    }

    $detail = trim($stderr) !== '' ? trim($stderr) : trim($stdout);
    if ($detail === '') {
        $detail = 'Fetcher exited with code ' . $exitCode . '.';
    }
    $attemptErrors[] = $pythonBin . ': ' . $detail;
}

if ($exitCode !== 0) {
    http_response_code(500);
    $details = implode(' | ', $attemptErrors);
    if ($details === '') {
        $details = 'All Python candidates failed.';
    }
    echo json_encode([
        'message' => 'Fetch failed for the requested criteria.',
        'details' => $details,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$fetchedThisRun = null;
if (preg_match('/Fetched this run:\s*(\d+)/i', $stdout, $matches)) {
    $fetchedThisRun = (int) $matches[1];
}

$totalJobs = null;
if (preg_match('/Total cached jobs:\s*(\d+)/i', $stdout, $matches)) {
    $totalJobs = (int) $matches[1];
}

$scopeLabel = 'role ' . $searchTerm . ' in ' . $country;
if ($city !== '' && !$treatPrimaryAsCompany) {
    $scopeLabel = 'city/place ' . $city;
} elseif ($city !== '' && $treatPrimaryAsCompany) {
    $scopeLabel = 'company ' . $city . ' in ' . $country;
}
$msg = 'Fetch completed for ' . $scopeLabel . '.';
if ($fetchedThisRun !== null) {
    $msg .= ' Added ' . $fetchedThisRun . ' jobs this run.';
}
if ($totalJobs !== null) {
    $msg .= ' Total cache: ' . $totalJobs . '.';
}

echo json_encode([
    'message' => $msg . ($usedPython !== '' ? ' Using: ' . $usedPython . '.' : ''),
    'city' => $city,
    'query_type' => $queryType,
    'primary_as_company' => $treatPrimaryAsCompany,
    'location' => $locationForFetch,
    'search_term' => $searchTerm,
    'sites' => $siteParts,
    'fetched_this_run' => $fetchedThisRun,
    'total_jobs' => $totalJobs,
], JSON_UNESCAPED_UNICODE);
