<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/seeker_topbar.php';
require_once __DIR__ . '/includes/provider_topbar.php';

requireLogin();

$currentUserId = currentUserId();
$role = (string) ($_SESSION['role'] ?? '');
$focusUserId = isset($_GET['focus_user_id']) ? (int) $_GET['focus_user_id'] : 0;
$focusMockId = trim((string) ($_GET['focus_mock_id'] ?? ''));

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

$self = ['lat' => null, 'lng' => null, 'city' => ''];
$rawItems = [];
$mockMapFreelancers = [
    ['mock_key' => 'mock_1', 'name' => 'Aarav Sharma', 'skill' => 'Full Stack Developer', 'city' => 'Jaipur', 'lat' => 26.9124, 'lng' => 75.7873],
    ['mock_key' => 'mock_2', 'name' => 'Sofia Khan', 'skill' => 'UI/UX Designer', 'city' => 'Bengaluru', 'lat' => 12.9716, 'lng' => 77.5946],
    ['mock_key' => 'mock_3', 'name' => 'Noah Patel', 'skill' => 'DevOps Engineer', 'city' => 'Pune', 'lat' => 18.5204, 'lng' => 73.8567],
    ['mock_key' => 'mock_4', 'name' => 'Meera Iyer', 'skill' => 'Content Strategist', 'city' => 'Kochi', 'lat' => 9.9312, 'lng' => 76.2673],
    ['mock_key' => 'mock_5', 'name' => 'Ethan Roy', 'skill' => 'Mobile App Developer', 'city' => 'Hyderabad', 'lat' => 17.3850, 'lng' => 78.4867],
    ['mock_key' => 'mock_6', 'name' => 'Riya Das', 'skill' => 'Data Analyst', 'city' => 'Chennai', 'lat' => 13.0827, 'lng' => 80.2707],
    ['mock_key' => 'mock_7', 'name' => 'Kabir Mehta', 'skill' => 'Backend Developer', 'city' => 'Ahmedabad', 'lat' => 23.0225, 'lng' => 72.5714],
    ['mock_key' => 'mock_8', 'name' => 'Anaya Gupta', 'skill' => 'SEO Specialist', 'city' => 'Mumbai', 'lat' => 19.0760, 'lng' => 72.8777],
    ['mock_key' => 'mock_9', 'name' => 'Vihaan Nair', 'skill' => 'QA Automation Engineer', 'city' => 'Bhopal', 'lat' => 23.2599, 'lng' => 77.4126],
    ['mock_key' => 'mock_10', 'name' => 'Zara Ali', 'skill' => 'Motion Graphic Designer', 'city' => 'Kolkata', 'lat' => 22.5726, 'lng' => 88.3639],
];

function mapCharacterAvatarUrl(string $name, ?string $imagePath, ?string $gender, ?int $age): string
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

if ($role === 'seeker') {
    $selfStmt = $pdo->prepare('SELECT lat, lng, city FROM freelancers WHERE user_id = ? LIMIT 1');
    $selfStmt->execute([$currentUserId]);
    $self = $selfStmt->fetch() ?: ['lat' => null, 'lng' => null, 'city' => ''];

    $itemsStmt = $pdo->prepare(
        'SELECT u.id AS user_id,
                COALESCE(pl.company_name, u.name) AS name,
                COALESCE(j.title, pl.workplace_name, "Hiring Team") AS skill,
                pl.city,
                pl.lat,
                pl.lng,
                NULL AS image_path,
                NULL AS gender,
                NULL AS age
         FROM provider_locations pl
         JOIN users u ON u.id = pl.user_id
         LEFT JOIN jobs j ON j.id = (
             SELECT j2.id FROM jobs j2 WHERE j2.provider_id = u.id ORDER BY j2.created_at DESC LIMIT 1
         )
         WHERE u.role = ? AND pl.lat IS NOT NULL AND pl.lng IS NOT NULL AND u.id <> ?
         ORDER BY name ASC
         LIMIT 150'
    );
    $itemsStmt->execute(['provider', $currentUserId]);
    $rawItems = $itemsStmt->fetchAll();
} else {
    $selfStmt = $pdo->prepare('SELECT lat, lng, city FROM provider_locations WHERE user_id = ? LIMIT 1');
    $selfStmt->execute([$currentUserId]);
    $self = $selfStmt->fetch() ?: ['lat' => null, 'lng' => null, 'city' => ''];

    $itemsStmt = $pdo->prepare(
        'SELECT u.id AS user_id,
                u.name,
                COALESCE(f.skill, "Generalist") AS skill,
                COALESCE(f.city, "Unknown City") AS city,
                f.lat,
                f.lng,
                f.image_path,
                f.gender,
                f.age
         FROM freelancers f
         JOIN users u ON u.id = f.user_id
         WHERE f.lat IS NOT NULL AND f.lng IS NOT NULL AND u.id <> ?
         ORDER BY u.name ASC
         LIMIT 150'
    );
    $itemsStmt->execute([$currentUserId]);
    $rawItems = $itemsStmt->fetchAll();
}

$mapItems = [];
foreach ($rawItems as $f) {
    $itemName = (string) $f['name'];
    $mapItems[] = [
        'user_id' => (int) $f['user_id'],
        'name' => $itemName,
        'skill' => (string) ($f['skill'] ?: 'Generalist'),
        'city' => (string) ($f['city'] ?: 'Unknown City'),
        'lat' => (float) $f['lat'],
        'lng' => (float) $f['lng'],
        'avatar_url' => mapCharacterAvatarUrl(
            $itemName,
            isset($f['image_path']) ? (string) $f['image_path'] : null,
            isset($f['gender']) ? (string) $f['gender'] : null,
            isset($f['age']) ? (int) $f['age'] : null
        ),
        'is_mock' => false,
        'mock_key' => '',
        'chat_url' => 'chat.php?user_id=' . (int) $f['user_id'],
    ];
}

if ($role !== 'seeker') {
    foreach ($mockMapFreelancers as $idx => $m) {
        $mapItems[] = [
            'user_id' => -1000 - $idx,
            'name' => (string) $m['name'],
            'skill' => (string) $m['skill'],
            'city' => (string) $m['city'],
            'lat' => (float) $m['lat'],
            'lng' => (float) $m['lng'],
            'avatar_url' => mapCharacterAvatarUrl((string) $m['name'], null, null, null),
            'is_mock' => true,
            'mock_key' => (string) $m['mock_key'],
            'chat_url' => 'chat.php?mock_id=' . rawurlencode((string) $m['mock_key']),
        ];
    }
}

$entityTitle = $role === 'seeker' ? 'Clients' : 'Talent';
$entityLower = strtolower($entityTitle);
$updatePinHref = $role === 'seeker' ? 'profile.php' : 'provider_location.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Map Discovery</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <style>
        .map-shell {
            background:
                radial-gradient(700px 280px at 0% -10%, rgba(255, 186, 255, 0.14), transparent 55%),
                radial-gradient(620px 280px at 100% 0%, rgba(132, 255, 217, 0.13), transparent 55%),
                #05070b;
        }
        #talentMap {
            height: 100%;
            min-height: 0;
            border-radius: 0 26px 26px 0;
        }
        .map-frame {
            height: clamp(560px, calc(100vh - 9rem), 760px);
        }
        .left-panel {
            width: 290px;
            flex: 0 0 290px;
            max-width: 100%;
            background: #121418;
            border-right: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 26px 0 0 26px;
        }
        .place-card.active {
            border-color: rgba(121, 245, 247, 0.72);
            box-shadow: 0 0 0 1px rgba(121, 245, 247, 0.35), inset 0 0 22px rgba(121, 245, 247, 0.08);
        }
        .client-marker {
            width: 34px;
            height: 34px;
            border-radius: 999px;
            border: 2px solid rgba(255, 255, 255, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(140deg, #2f323a, #1c1e24);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.35);
            overflow: hidden;
        }
        .client-marker-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .person-avatar {
            width: 34px;
            height: 34px;
            border-radius: 999px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            background: rgba(255, 255, 255, 0.1);
            object-fit: cover;
            display: block;
            flex-shrink: 0;
        }
        .self-marker {
            width: 38px;
            height: 38px;
            border-radius: 999px;
            border: 2px solid rgba(255, 255, 255, 0.95);
            background: linear-gradient(140deg, #4ef6cf, #1f9f88);
            box-shadow: 0 0 0 10px rgba(78, 246, 207, 0.18), 0 8px 18px rgba(0, 0, 0, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #04120f;
            font-weight: 700;
            font-size: 11px;
        }
        .leaflet-container {
            background: #1c1f24;
            font-family: var(--font-body);
        }
        .map-detail-card {
            background: rgba(20, 22, 28, 0.96);
            border: 1px solid rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(8px);
        }
        .selected-panel {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.16);
            box-shadow: inset 0 0 20px rgba(78, 246, 207, 0.05);
        }
        .route-chip {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.18);
        }
        @media (max-width: 980px) {
            .map-frame {
                height: auto;
                min-height: 0;
            }
            .left-panel {
                width: 100%;
                flex: 1 1 auto;
                border-radius: 26px 26px 0 0;
                border-right: none;
                border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            }
            #talentMap {
                border-radius: 0 0 26px 26px;
                min-height: 430px;
            }
        }
    </style>
</head>
<body class="map-shell text-white min-h-screen">
    <?php if (($_SESSION['role'] ?? '') === 'seeker') { renderSeekerTopbar('jobs'); } else { ?>
    <?php renderProviderTopbar('hub', false); ?>
    <?php } ?>

    <main class="pt-24 pb-8 px-4 md:px-8">
        <div class="max-w-[1280px] mx-auto rounded-[28px] border border-white/10 bg-[#0f1116] overflow-hidden shadow-[0_20px_65px_rgba(0,0,0,0.45)]">
            <div class="map-frame flex flex-col lg:flex-row">
                <section class="left-panel p-4 md:p-5 flex flex-col gap-4">
                    <div class="flex items-center justify-between px-2">
                        <div>
                            <h1 class="text-xl font-semibold">Discover Nearby <?php echo htmlspecialchars($entityTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
                            <p class="text-xs text-white/55 mt-1">Distance between you and <?php echo htmlspecialchars($entityLower, ENT_QUOTES, 'UTF-8'); ?> is shown live</p>
                        </div>
                        <a href="<?php echo htmlspecialchars($updatePinHref, ENT_QUOTES, 'UTF-8'); ?>" class="text-xs rounded-full px-3 py-1 bg-white/10 border border-white/15">Update Pin</a>
                    </div>

                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-white/45">search</span>
                        <input id="placeSearch" type="text" class="w-full rounded-xl bg-white/6 border border-white/12 pl-11 pr-3 py-2.5 text-sm placeholder-white/35" placeholder="Search by name, city, skill">
                    </div>

                    <div class="flex items-center gap-2">
                        <button id="sortNearest" class="route-chip rounded-lg px-3 py-2 text-xs font-semibold">Nearest</button>
                        <button id="sortName" class="route-chip rounded-lg px-3 py-2 text-xs">A-Z</button>
                        <button id="useMyLocation" class="route-chip rounded-lg px-3 py-2 text-xs flex items-center gap-1"><span class="material-symbols-outlined" style="font-size:15px;">my_location</span>Use GPS</button>
                    </div>

                    <div class="selected-panel rounded-xl p-3 text-sm">
                        <div class="flex items-center justify-between gap-2">
                            <p id="selectedClientName" class="font-semibold text-white/90">No <?php echo htmlspecialchars($entityLower, ENT_QUOTES, 'UTF-8'); ?> selected</p>
                            <span id="selectedClientDistance" class="route-chip rounded-full px-2 py-0.5 text-[10px]">--</span>
                        </div>
                        <p id="selectedClientLocation" class="text-white/65 text-xs mt-2">Select a card to view exact location.</p>
                        <p id="selectedClientRoute" class="text-emerald-300 text-[11px] mt-2">Route details will appear here.</p>
                    </div>

                    <div id="placeList" class="flex-1 overflow-y-auto pr-1 space-y-2">
                        <p class="text-sm text-white/60 px-2">Loading nearby <?php echo htmlspecialchars($entityLower, ENT_QUOTES, 'UTF-8'); ?>...</p>
                    </div>
                </section>

                <section class="relative flex-1">
                    <div id="talentMap"></div>

                    <div class="absolute top-4 left-1/2 -translate-x-1/2 z-[500] w-[90%] max-w-[620px]">
                        <div class="map-detail-card rounded-full px-4 py-2.5 flex items-center gap-3">
                            <span class="material-symbols-outlined text-white/70">route</span>
                            <p id="routeBanner" class="text-sm text-white/85">Select a <?php echo htmlspecialchars($entityLower, ENT_QUOTES, 'UTF-8'); ?> to draw distance route from your location.</p>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </main>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        (function () {
            var mapItems = <?php echo json_encode($mapItems, JSON_UNESCAPED_UNICODE); ?>;
            var entityLabel = <?php echo json_encode($entityLower, JSON_UNESCAPED_UNICODE); ?>;
            var me = {
                lat: <?php echo json_encode(isset($self['lat']) ? (float) $self['lat'] : null); ?>,
                lng: <?php echo json_encode(isset($self['lng']) ? (float) $self['lng'] : null); ?>,
                city: <?php echo json_encode((string) ($self['city'] ?? ''), JSON_UNESCAPED_UNICODE); ?>
            };
            var preferredFocus = {
                userId: <?php echo (int) $focusUserId; ?>,
                mockId: <?php echo json_encode($focusMockId, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
            };

            var mapCenter = [13.0827, 80.2707];
            if (Number.isFinite(Number(me.lat)) && Number.isFinite(Number(me.lng))) {
                mapCenter = [Number(me.lat), Number(me.lng)];
            } else if (mapItems.length) {
                mapCenter = [Number(mapItems[0].lat), Number(mapItems[0].lng)];
            }

            var map = L.map('talentMap', { zoomControl: false }).setView(mapCenter, 12);
            L.control.zoom({ position: 'bottomright' }).addTo(map);

            L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
                maxZoom: 20,
                attribution: '&copy; OpenStreetMap &copy; CARTO'
            }).addTo(map);

            setTimeout(function () {
                map.invalidateSize();
            }, 60);

            window.addEventListener('resize', function () {
                map.invalidateSize();
            });

            function avatarUrlForItem(item) {
                if (!item || typeof item.avatar_url !== 'string') {
                    return '';
                }
                return item.avatar_url;
            }

            function escapeHtml(value) {
                return String(value || '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function haversineKm(lat1, lng1, lat2, lng2) {
                var R = 6371;
                var dLat = (lat2 - lat1) * Math.PI / 180;
                var dLng = (lng2 - lng1) * Math.PI / 180;
                var a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                    Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                    Math.sin(dLng / 2) * Math.sin(dLng / 2);
                return R * (2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a)));
            }

            function hasMyCoords() {
                return Number.isFinite(Number(me.lat)) && Number.isFinite(Number(me.lng));
            }

            mapItems.forEach(function (item) {
                if (hasMyCoords()) {
                    item.distanceKm = haversineKm(Number(me.lat), Number(me.lng), Number(item.lat), Number(item.lng));
                } else {
                    item.distanceKm = null;
                }
            });

            var clientMarkers = {};
            mapItems.forEach(function (item) {
                var marker = L.marker([item.lat, item.lng], {
                    icon: L.divIcon({
                        className: '',
                        html: '<div class="client-marker"><img class="client-marker-img" src="' + escapeHtml(avatarUrlForItem(item)) + '" alt=""></div>',
                        iconSize: [34, 34],
                        iconAnchor: [17, 17]
                    })
                }).addTo(map);
                marker.on('click', function () {
                    selectClient(item.user_id);
                });
                clientMarkers[item.user_id] = marker;
            });

            if (hasMyCoords()) {
                L.marker([Number(me.lat), Number(me.lng)], {
                    icon: L.divIcon({
                        className: '',
                        html: '<div class="self-marker">YOU</div>',
                        iconSize: [38, 38],
                        iconAnchor: [19, 19]
                    })
                }).addTo(map);
            }

            var currentRoute = null;
            var selectedId = 0;
            var routeBanner = document.getElementById('routeBanner');
            var selectedClientName = document.getElementById('selectedClientName');
            var selectedClientDistance = document.getElementById('selectedClientDistance');
            var selectedClientLocation = document.getElementById('selectedClientLocation');
            var selectedClientRoute = document.getElementById('selectedClientRoute');

            function kmText(value) {
                if (value === null || !Number.isFinite(Number(value))) {
                    return 'Unknown';
                }
                return (Math.round(value * 10) / 10) + ' km';
            }

            function minuteText(seconds) {
                if (!Number.isFinite(Number(seconds))) {
                    return 'N/A';
                }
                var mins = Math.round(Number(seconds) / 60);
                return mins + ' min';
            }

            function distanceText(value) {
                return kmText(value);
            }

            function drawFallbackLine(item) {
                currentRoute = L.polyline([
                    [Number(me.lat), Number(me.lng)],
                    [Number(item.lat), Number(item.lng)]
                ], {
                    color: '#4ef6cf',
                    weight: 4,
                    opacity: 0.95,
                    dashArray: '10, 10'
                }).addTo(map);
            }

            function fetchRoadRoute(item) {
                if (!hasMyCoords()) {
                    return Promise.resolve({
                        distanceKm: null,
                        durationSec: null,
                        geometry: null,
                        isFallback: true
                    });
                }

                var url = 'https://router.project-osrm.org/route/v1/driving/' +
                    encodeURIComponent(String(me.lng) + ',' + String(me.lat)) + ';' +
                    encodeURIComponent(String(item.lng) + ',' + String(item.lat)) +
                    '?overview=full&geometries=geojson';

                return fetch(url)
                    .then(function (res) {
                        if (!res.ok) {
                            throw new Error('Routing API failed');
                        }
                        return res.json();
                    })
                    .then(function (data) {
                        if (!data.routes || !data.routes.length) {
                            throw new Error('No route');
                        }
                        var route = data.routes[0];
                        var coords = (route.geometry && route.geometry.coordinates) ? route.geometry.coordinates : [];
                        return {
                            distanceKm: Number(route.distance || 0) / 1000,
                            durationSec: Number(route.duration || 0),
                            geometry: coords.map(function (c) { return [Number(c[1]), Number(c[0])]; }),
                            isFallback: false
                        };
                    })
                    .catch(function () {
                        return {
                            distanceKm: item.distanceKm,
                            durationSec: null,
                            geometry: null,
                            isFallback: true
                        };
                    });
            }

            function renderList(data) {
                var wrap = document.getElementById('placeList');
                if (!data.length) {
                    wrap.innerHTML = '<p class="text-sm text-white/60 px-2">No ' + entityLabel + ' found for your filter.</p>';
                    return;
                }

                var html = '';
                data.forEach(function (item, idx) {
                    var activeClass = item.user_id === selectedId ? 'active' : '';
                    var colorClass = idx % 3 === 0 ? 'border-cyan-300/25' : (idx % 3 === 1 ? 'border-orange-300/25' : 'border-white/20');
                    html += '<div data-user-id="' + item.user_id + '" class="place-card ' + activeClass + ' w-full text-left rounded-xl border ' + colorClass + ' bg-white/5 hover:bg-white/10 p-3 transition-all">';
                    html += '<div class="flex items-center justify-between gap-2">';
                    html += '<img class="person-avatar" src="' + escapeHtml(avatarUrlForItem(item)) + '" alt="">';
                    html += '<div class="flex items-center gap-2">';
                    html += '<span class="text-[10px] route-chip rounded-full px-2 py-1">' + distanceText(item.distanceKm) + '</span>';
                    html += '<a href="' + escapeHtml(String(item.chat_url || '#')) + '" class="text-[11px] rounded-full px-2.5 py-1 bg-white/10 border border-white/20 hover:bg-white/15">Chat</a>';
                    html += '</div>';
                    html += '</div>';
                    html += '<p class="text-xs text-white/60 mt-2 truncate">' + escapeHtml(item.skill) + ' • ' + escapeHtml(item.city) + (item.is_mock ? ' • Mock' : '') + '</p>';
                    html += '</div>';
                });
                wrap.innerHTML = html;

                Array.prototype.forEach.call(wrap.querySelectorAll('.place-card'), function (btn) {
                    btn.addEventListener('click', function () {
                        selectClient(Number(btn.getAttribute('data-user-id')));
                    });
                });

                Array.prototype.forEach.call(wrap.querySelectorAll('.place-card a'), function (link) {
                    link.addEventListener('click', function (event) {
                        event.stopPropagation();
                    });
                });
            }

            function selectClient(userId) {
                var item = mapItems.find(function (x) { return Number(x.user_id) === Number(userId); });
                if (!item) {
                    return;
                }

                selectedId = Number(userId);
                renderVisibleList();

                var marker = clientMarkers[item.user_id];
                if (marker) {
                    map.flyTo(marker.getLatLng(), Math.max(map.getZoom(), 12), { duration: 0.8 });
                }

                if (currentRoute) {
                    map.removeLayer(currentRoute);
                    currentRoute = null;
                }

                selectedClientName.textContent = item.name;
                selectedClientLocation.textContent = item.city + ' • ' + item.skill;
                selectedClientDistance.textContent = distanceText(item.distanceKm);
                selectedClientRoute.textContent = 'Calculating best road route...';
                routeBanner.textContent = item.name + ' • Distance: ' + distanceText(item.distanceKm);

                if (!hasMyCoords()) {
                    routeBanner.textContent = 'Selected ' + item.name + '. Enable GPS or set profile coordinates for route distance.';
                    selectedClientRoute.textContent = 'Route unavailable until your coordinates are set.';
                    return;
                }

                fetchRoadRoute(item).then(function (routeData) {
                    if (selectedId !== Number(item.user_id)) {
                        return;
                    }

                    if (currentRoute) {
                        map.removeLayer(currentRoute);
                        currentRoute = null;
                    }

                    if (routeData.geometry && routeData.geometry.length > 1) {
                        currentRoute = L.polyline(routeData.geometry, {
                            color: '#4ef6cf',
                            weight: 4,
                            opacity: 0.95
                        }).addTo(map);
                        map.fitBounds(L.latLngBounds(routeData.geometry), { padding: [70, 70] });
                    } else {
                        drawFallbackLine(item);
                        var fallbackBounds = L.latLngBounds([
                            [Number(me.lat), Number(me.lng)],
                            [Number(item.lat), Number(item.lng)]
                        ]);
                        map.fitBounds(fallbackBounds, { padding: [70, 70] });
                    }

                    var shownDistance = routeData.distanceKm !== null ? routeData.distanceKm : item.distanceKm;
                    selectedClientDistance.textContent = kmText(shownDistance);
                    if (routeData.durationSec !== null) {
                        selectedClientRoute.textContent = 'Road distance: ' + kmText(shownDistance) + ' • ETA: ' + minuteText(routeData.durationSec);
                        routeBanner.textContent = 'Road route to ' + item.name + ': ' + kmText(shownDistance) + ' (' + minuteText(routeData.durationSec) + ')';
                    } else {
                        selectedClientRoute.textContent = 'Road route unavailable, showing straight-line estimate.';
                        routeBanner.textContent = 'Estimated distance to ' + item.name + ': ' + kmText(shownDistance);
                    }
                });
            }

            var activeSort = 'nearest';
            var query = '';

            function renderVisibleList() {
                var filtered = mapItems.filter(function (item) {
                    var blob = (item.name + ' ' + item.skill + ' ' + item.city).toLowerCase();
                    return !query || blob.indexOf(query) > -1;
                });

                filtered.sort(function (a, b) {
                    if (activeSort === 'name') {
                        return a.name.localeCompare(b.name);
                    }
                    if (a.distanceKm === null && b.distanceKm === null) {
                        return a.name.localeCompare(b.name);
                    }
                    if (a.distanceKm === null) {
                        return 1;
                    }
                    if (b.distanceKm === null) {
                        return -1;
                    }
                    return a.distanceKm - b.distanceKm;
                });

                renderList(filtered);
            }

            document.getElementById('sortNearest').addEventListener('click', function () {
                activeSort = 'nearest';
                this.classList.add('font-semibold');
                document.getElementById('sortName').classList.remove('font-semibold');
                renderVisibleList();
            });

            document.getElementById('sortName').addEventListener('click', function () {
                activeSort = 'name';
                this.classList.add('font-semibold');
                document.getElementById('sortNearest').classList.remove('font-semibold');
                renderVisibleList();
            });

            document.getElementById('placeSearch').addEventListener('input', function () {
                query = String(this.value || '').toLowerCase().trim();
                renderVisibleList();
            });

            document.getElementById('useMyLocation').addEventListener('click', function () {
                if (!navigator.geolocation) {
                    routeBanner.textContent = 'Geolocation is not supported in this browser.';
                    return;
                }

                var btn = this;
                btn.disabled = true;
                routeBanner.textContent = 'Fetching your live location...';

                navigator.geolocation.getCurrentPosition(function (pos) {
                    me.lat = Number(pos.coords.latitude.toFixed(6));
                    me.lng = Number(pos.coords.longitude.toFixed(6));

                    mapItems.forEach(function (item) {
                        item.distanceKm = haversineKm(Number(me.lat), Number(me.lng), Number(item.lat), Number(item.lng));
                    });

                    L.marker([Number(me.lat), Number(me.lng)], {
                        icon: L.divIcon({
                            className: '',
                            html: '<div class="self-marker">YOU</div>',
                            iconSize: [38, 38],
                            iconAnchor: [19, 19]
                        })
                    }).addTo(map);

                    routeBanner.textContent = 'Live location loaded. Distances refreshed.';
                    renderVisibleList();
                    if (selectedId) {
                        selectClient(selectedId);
                    }
                    btn.disabled = false;
                }, function () {
                    routeBanner.textContent = 'Unable to access your live location.';
                    btn.disabled = false;
                });
            });

            renderVisibleList();
            if (mapItems.length) {
                var initialItem = null;
                if (preferredFocus.mockId) {
                    initialItem = mapItems.find(function (item) {
                        return String(item.mock_key || '') === String(preferredFocus.mockId);
                    }) || null;
                }
                if (!initialItem && preferredFocus.userId) {
                    initialItem = mapItems.find(function (item) {
                        return Number(item.user_id) === Number(preferredFocus.userId);
                    }) || null;
                }
                if (!initialItem) {
                    initialItem = mapItems[0];
                }
                selectClient(initialItem.user_id);
                if ((preferredFocus.mockId || preferredFocus.userId) && Number(initialItem.user_id) !== Number(mapItems[0].user_id)) {
                    routeBanner.textContent = 'Showing requested location on the map.';
                }
            } else {
                routeBanner.textContent = 'No ' + entityLabel + ' coordinates available yet. Ask users to set location in profile.';
            }
        })();
    </script>
</body>
</html>
