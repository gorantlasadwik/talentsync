<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/seeker_topbar.php';

requireLogin();

$returnTo = (string) ($_GET['return_to'] ?? 'dashboard.php');
$allowedReturns = ['profile.php', 'provider_location.php'];
if (!in_array($returnTo, $allowedReturns, true)) {
    $returnTo = 'dashboard.php';
}

$initialLat = is_numeric($_GET['lat'] ?? null) ? (float) $_GET['lat'] : null;
$initialLng = is_numeric($_GET['lng'] ?? null) ? (float) $_GET['lng'] : null;
$initialCity = trim((string) ($_GET['city'] ?? ''));

$isSeeker = (($_SESSION['role'] ?? '') === 'seeker');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Location</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <style>
        .picker-shell {
            background:
                radial-gradient(700px 280px at 0% -10%, rgba(255, 186, 255, 0.14), transparent 55%),
                radial-gradient(620px 280px at 100% 0%, rgba(132, 255, 217, 0.13), transparent 55%),
                #05070b;
        }
        #pickerMap {
            min-height: 620px;
            height: calc(100vh - 9rem);
            border-radius: 0 24px 24px 0;
        }
        .picker-left {
            width: 360px;
            max-width: 100%;
            background: #121418;
            border-right: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 24px 0 0 24px;
        }
        .suggestion-item:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        .picker-input {
            background: rgba(18, 20, 24, 0.95) !important;
            color: #ffffff !important;
            border: 1px solid rgba(255, 255, 255, 0.16) !important;
        }
        .picker-input::placeholder {
            color: rgba(255, 255, 255, 0.45);
        }
        .picker-input:-webkit-autofill,
        .picker-input:-webkit-autofill:hover,
        .picker-input:-webkit-autofill:focus {
            -webkit-text-fill-color: #ffffff;
            -webkit-box-shadow: 0 0 0px 1000px rgba(18, 20, 24, 0.95) inset;
            transition: background-color 9999s ease-in-out 0s;
            caret-color: #ffffff;
        }
        .pin-marker {
            width: 40px;
            height: 40px;
            border-radius: 999px;
            background: linear-gradient(140deg, #4ef6cf, #1f9f88);
            border: 2px solid rgba(255, 255, 255, 0.95);
            color: #04120f;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 0 10px rgba(78, 246, 207, 0.2), 0 8px 18px rgba(0, 0, 0, 0.4);
            font-size: 11px;
        }
        .leaflet-container {
            background: #1c1f24;
            font-family: var(--font-body);
        }
        @media (max-width: 980px) {
            .picker-left {
                width: 100%;
                border-radius: 24px 24px 0 0;
                border-right: none;
                border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            }
            #pickerMap {
                border-radius: 0 0 24px 24px;
                min-height: 430px;
                height: 430px;
            }
        }
    </style>
</head>
<body class="picker-shell text-white min-h-screen">
    <?php if ($isSeeker) { renderSeekerTopbar('jobs'); } else { ?>
    <header class="bg-neutral-900 text-white flex justify-between items-center px-8 h-20 w-full z-50 fixed top-0">
        <div class="flex items-center gap-10"><a href="dashboard.php" class="text-2xl font-heading italic hover:text-white/90">TalentSync</a><span class="text-sm text-white/70">Location Picker</span></div>
        <a href="<?php echo e($returnTo); ?>" class="text-white/70 hover:text-white">Back</a>
    </header>
    <?php } ?>

    <main class="pt-24 pb-8 px-4 md:px-8">
        <div class="max-w-[1280px] mx-auto rounded-[26px] border border-white/10 bg-[#0f1116] overflow-hidden shadow-[0_20px_65px_rgba(0,0,0,0.45)]">
            <div class="flex flex-col lg:flex-row">
                <section class="picker-left p-5 flex flex-col gap-4">
                    <div>
                        <h1 class="text-2xl font-heading italic">Select Location Using Map</h1>
                        <p class="text-xs text-white/60 mt-2">Search places (example: Hyderabad Railway Station) or click map to drop pin.</p>
                    </div>

                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-white/45">search</span>
                        <input id="placeSearch" type="text" class="picker-input w-full rounded-xl pl-11 pr-3 py-2.5 text-sm" placeholder="Search location name, area, landmark">
                        <div id="searchSuggestions" class="absolute z-[700] left-0 right-0 mt-2 bg-[#121418] border border-white/15 rounded-xl hidden max-h-56 overflow-y-auto"></div>
                    </div>

                    <div class="flex items-center gap-2">
                        <button id="useCurrent" type="button" class="liquid-glass rounded-full px-4 py-2 text-sm flex items-center gap-1"><span class="material-symbols-outlined" style="font-size:16px;">my_location</span>Use Current</button>
                        <button id="clearPin" type="button" class="liquid-glass rounded-full px-4 py-2 text-sm">Clear Pin</button>
                    </div>

                    <div class="liquid-glass rounded-2xl p-4 space-y-2 text-sm">
                        <p class="text-white/60 text-xs uppercase tracking-wide">Selected</p>
                        <p id="pickedAddress" class="text-white/90">No location selected yet.</p>
                        <div class="grid grid-cols-2 gap-3 pt-2">
                            <div>
                                <p class="text-white/55 text-xs">Latitude</p>
                                <p id="pickedLat" class="font-medium">--</p>
                            </div>
                            <div>
                                <p class="text-white/55 text-xs">Longitude</p>
                                <p id="pickedLng" class="font-medium">--</p>
                            </div>
                        </div>
                        <p id="pickedCity" class="text-emerald-300 text-xs">City: --</p>
                    </div>

                    <div class="mt-auto flex gap-3">
                        <a href="<?php echo e($returnTo); ?>" class="flex-1 text-center liquid-glass rounded-full px-4 py-3 text-sm">Cancel</a>
                        <button id="confirmLocation" type="button" class="flex-1 liquid-glass-strong rounded-full px-4 py-3 text-sm">Select This Location</button>
                    </div>
                </section>

                <section class="relative flex-1">
                    <div id="pickerMap"></div>
                    <div class="absolute top-4 left-1/2 -translate-x-1/2 z-[600] w-[90%] max-w-[560px]">
                        <div class="bg-[#121418]/95 border border-white/15 rounded-full px-4 py-2.5 text-sm text-white/80 backdrop-blur-sm">
                            Click on map or choose a suggestion to place the pin.
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </main>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        (function () {
            var returnTo = <?php echo json_encode($returnTo, JSON_UNESCAPED_UNICODE); ?>;
            var initialLat = <?php echo json_encode($initialLat); ?>;
            var initialLng = <?php echo json_encode($initialLng); ?>;
            var initialCity = <?php echo json_encode($initialCity, JSON_UNESCAPED_UNICODE); ?>;

            var mapCenter = [17.3850, 78.4867];
            if (Number.isFinite(Number(initialLat)) && Number.isFinite(Number(initialLng))) {
                mapCenter = [Number(initialLat), Number(initialLng)];
            }

            var map = L.map('pickerMap').setView(mapCenter, Number.isFinite(Number(initialLat)) ? 13 : 11);
            L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
                maxZoom: 20,
                attribution: '&copy; OpenStreetMap &copy; CARTO'
            }).addTo(map);

            var marker = null;
            var selected = {
                lat: Number.isFinite(Number(initialLat)) ? Number(initialLat) : null,
                lng: Number.isFinite(Number(initialLng)) ? Number(initialLng) : null,
                address: initialCity || '',
                city: initialCity || ''
            };

            function renderSelection() {
                document.getElementById('pickedLat').textContent = selected.lat !== null ? selected.lat.toFixed(6) : '--';
                document.getElementById('pickedLng').textContent = selected.lng !== null ? selected.lng.toFixed(6) : '--';
                document.getElementById('pickedAddress').textContent = selected.address || 'No location selected yet.';
                document.getElementById('pickedCity').textContent = 'City: ' + (selected.city || '--');
            }

            function setMarker(lat, lng) {
                if (marker) {
                    marker.setLatLng([lat, lng]);
                } else {
                    marker = L.marker([lat, lng], {
                        icon: L.divIcon({
                            className: '',
                            html: '<div class="pin-marker">PIN</div>',
                            iconSize: [40, 40],
                            iconAnchor: [20, 20]
                        })
                    }).addTo(map);
                }
            }

            function pickBestCity(address) {
                if (!address) {
                    return '';
                }
                return address.city || address.town || address.village || address.county || address.state_district || address.state || '';
            }

            function reverseLookup(lat, lng) {
                var reverseUrl = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=' +
                    encodeURIComponent(lat) + '&lon=' + encodeURIComponent(lng);

                fetch(reverseUrl, { headers: { 'Accept': 'application/json' } })
                    .then(function (res) {
                        if (!res.ok) {
                            throw new Error('Reverse lookup failed');
                        }
                        return res.json();
                    })
                    .then(function (data) {
                        selected.address = data.display_name || selected.address;
                        selected.city = pickBestCity(data.address);
                        renderSelection();
                    })
                    .catch(function () {
                        renderSelection();
                    });
            }

            function chooseLocation(lat, lng, label) {
                selected.lat = Number(lat);
                selected.lng = Number(lng);
                selected.address = label || selected.address;
                setMarker(Number(lat), Number(lng));
                map.setView([Number(lat), Number(lng)], Math.max(map.getZoom(), 13));
                reverseLookup(Number(lat), Number(lng));
                renderSelection();
            }

            map.on('click', function (e) {
                chooseLocation(e.latlng.lat, e.latlng.lng, 'Pinned from map');
            });

            var searchInput = document.getElementById('placeSearch');
            var suggestions = document.getElementById('searchSuggestions');
            var timer = null;

            function hideSuggestions() {
                suggestions.classList.add('hidden');
                suggestions.innerHTML = '';
            }

            function showSuggestions(items) {
                if (!items.length) {
                    suggestions.innerHTML = '<div class="px-3 py-2 text-xs text-white/55">No suggestions found.</div>';
                    suggestions.classList.remove('hidden');
                    return;
                }

                suggestions.innerHTML = items.map(function (item) {
                    return '<button type="button" class="suggestion-item w-full text-left px-3 py-2 text-xs text-white/85 border-b border-white/5" data-lat="' +
                        item.lat + '" data-lng="' + item.lon + '" data-label="' +
                        String(item.display_name).replace(/"/g, '&quot;') + '">' +
                        item.display_name + '</button>';
                }).join('');
                suggestions.classList.remove('hidden');

                Array.prototype.forEach.call(suggestions.querySelectorAll('.suggestion-item'), function (btn) {
                    btn.addEventListener('click', function () {
                        chooseLocation(Number(btn.getAttribute('data-lat')), Number(btn.getAttribute('data-lng')), btn.getAttribute('data-label'));
                        searchInput.value = btn.getAttribute('data-label');
                        hideSuggestions();
                    });
                });
            }

            searchInput.addEventListener('input', function () {
                var query = String(this.value || '').trim();
                if (timer) {
                    clearTimeout(timer);
                }
                if (query.length < 3) {
                    hideSuggestions();
                    return;
                }

                timer = setTimeout(function () {
                    var url = 'https://nominatim.openstreetmap.org/search?format=jsonv2&addressdetails=1&limit=6&q=' + encodeURIComponent(query);
                    fetch(url, { headers: { 'Accept': 'application/json' } })
                        .then(function (res) {
                            if (!res.ok) {
                                throw new Error('Search failed');
                            }
                            return res.json();
                        })
                        .then(function (data) {
                            showSuggestions(Array.isArray(data) ? data : []);
                        })
                        .catch(function () {
                            showSuggestions([]);
                        });
                }, 280);
            });

            document.addEventListener('click', function (event) {
                if (!suggestions.contains(event.target) && event.target !== searchInput) {
                    hideSuggestions();
                }
            });

            document.getElementById('useCurrent').addEventListener('click', function () {
                if (!navigator.geolocation) {
                    alert('Geolocation is not supported in this browser.');
                    return;
                }
                navigator.geolocation.getCurrentPosition(function (pos) {
                    chooseLocation(pos.coords.latitude, pos.coords.longitude, 'Current device location');
                }, function () {
                    alert('Unable to fetch current location.');
                });
            });

            document.getElementById('clearPin').addEventListener('click', function () {
                selected = { lat: null, lng: null, address: '', city: '' };
                if (marker) {
                    map.removeLayer(marker);
                    marker = null;
                }
                renderSelection();
            });

            document.getElementById('confirmLocation').addEventListener('click', function () {
                if (selected.lat === null || selected.lng === null) {
                    alert('Select a location by searching or dropping a pin.');
                    return;
                }

                var params = new URLSearchParams();
                params.set('pick_lat', selected.lat.toFixed(6));
                params.set('pick_lng', selected.lng.toFixed(6));
                params.set('pick_city', selected.city || '');
                window.location.href = returnTo + '?' + params.toString();
            });

            if (selected.lat !== null && selected.lng !== null) {
                setMarker(selected.lat, selected.lng);
                reverseLookup(selected.lat, selected.lng);
            }
            renderSelection();
        })();
    </script>
</body>
</html>
