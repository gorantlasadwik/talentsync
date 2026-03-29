<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

function readJsonFile(string $path): array
{
    if (!file_exists($path)) {
        return [];
    }
    $decoded = json_decode((string) file_get_contents($path), true);
    return is_array($decoded) ? $decoded : [];
}

$localSources = array_merge(
    readJsonFile(__DIR__ . '/../data/linkedin.json'),
    readJsonFile(__DIR__ . '/../data/fiverr.json'),
    readJsonFile(__DIR__ . '/../data/naukri.json')
);

$dbRows = $pdo->query('SELECT id, title, source, location, description, created_at, "TalentSync" AS company FROM jobs ORDER BY created_at DESC LIMIT 30')->fetchAll();
$dbJobs = [];
foreach ($dbRows as $row) {
    $dbJobs[] = [
        'id' => (int) $row['id'],
        'title' => $row['title'],
        'source' => 'TalentSync',
        'location' => $row['location'],
        'description' => $row['description'],
        'company' => $row['company'],
        'url' => 'job_details.php?id=' . (int) $row['id'],
        'created_at' => $row['created_at'],
    ];
}

$apiJobs = [];
$remoteResponse = @file_get_contents('https://remotive.com/api/remote-jobs');
if ($remoteResponse !== false) {
    $remotive = json_decode($remoteResponse, true);
    if (!empty($remotive['jobs']) && is_array($remotive['jobs'])) {
        foreach (array_slice($remotive['jobs'], 0, 15) as $job) {
            $apiJobs[] = [
                'title' => $job['title'] ?? 'Untitled',
                'company' => $job['company_name'] ?? 'N/A',
                'source' => 'Remotive',
                'location' => $job['candidate_required_location'] ?? 'Remote',
                'url' => $job['url'] ?? null,
            ];
        }
    }
}

$rssJobs = [];
$rss = @simplexml_load_file('https://weworkremotely.com/remote-jobs.rss');
if ($rss && isset($rss->channel->item)) {
    foreach (array_slice(iterator_to_array($rss->channel->item), 0, 10) as $item) {
        $rssJobs[] = [
            'title' => (string) $item->title,
            'company' => 'WeWorkRemotely',
            'source' => 'RSS',
            'location' => 'Remote',
            'url' => (string) $item->link,
        ];
    }
}

$jobs = array_merge($dbJobs, $localSources, $apiJobs, $rssJobs);

echo json_encode(['jobs' => $jobs], JSON_UNESCAPED_UNICODE);
