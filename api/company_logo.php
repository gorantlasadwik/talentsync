<?php
declare(strict_types=1);

header('Access-Control-Allow-Origin: *');

$domain = trim((string) ($_GET['domain'] ?? ''));
$domain = strtolower($domain);
$domain = preg_replace('/^www\./', '', $domain);

if ($domain === '' || !preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $domain)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['message' => 'Invalid domain'], JSON_UNESCAPED_UNICODE);
    exit;
}

$source = 'https://icons.duckduckgo.com/ip3/' . rawurlencode($domain) . '.ico';

$context = stream_context_create([
    'http' => [
        'timeout' => 8,
        'follow_location' => 1,
        'max_redirects' => 3,
        'user_agent' => 'TalentSyncPro/1.0',
    ],
    'ssl' => [
        'verify_peer' => true,
        'verify_peer_name' => true,
    ],
]);

$data = @file_get_contents($source, false, $context);
if ($data === false || strlen($data) < 64) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['message' => 'Logo not found'], JSON_UNESCAPED_UNICODE);
    exit;
}

header('Content-Type: image/x-icon');
header('Cache-Control: public, max-age=86400');
echo $data;
