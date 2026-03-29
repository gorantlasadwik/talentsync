<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/seeker_topbar.php';

requireRole('seeker');

$userId = currentUserId();

$stmt = $pdo->prepare('SELECT u.name, f.skill, f.experience, f.city, f.lat, f.lng, f.image_path, f.resume_path, f.gender, f.age FROM users u LEFT JOIN freelancers f ON f.user_id = u.id WHERE u.id = ? LIMIT 1');
$stmt->execute([$userId]);
$profile = $stmt->fetch() ?: ['name' => $_SESSION['name'], 'skill' => '', 'experience' => '', 'image_path' => null, 'resume_path' => null, 'gender' => null, 'age' => null];

$avatar = avatarUrl(
    (string) ($profile['name'] ?? 'Freelancer'),
    isset($profile['image_path']) ? (string) $profile['image_path'] : null,
    isset($profile['gender']) ? (string) $profile['gender'] : null,
    isset($profile['age']) && $profile['age'] !== null ? (int) $profile['age'] : null
);
?>
<!DOCTYPE html>
<html lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>TalentSync PRO - Job Seeker</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Barlow:wght@300;400;500;600;700&family=Instrument+Serif:ital@1&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<script id="tailwind-config">
      tailwind.config = {
        darkMode: "class",
        theme: {
          extend: {
            colors: {
              "on-secondary-container": "#005b5d",
              "primary-container": "#e5e2e1",
              "on-tertiary": "#fff7f5",
              "error-container": "#fa746f",
              "on-error": "#fff7f6",
              "surface-dim": "#d5dbdd",
              "tertiary-dim": "#873e17",
              "error": "#a83836",
              "on-secondary": "#e0ffff",
              "tertiary-fixed": "#f69567",
              "outline-variant": "#adb3b5",
              "surface-container-high": "#e5e9eb",
              "secondary-dim": "#005d5e",
              "on-error-container": "#6e0a12",
              "primary-fixed-dim": "#d7d4d3",
              "background": "#f8f9fa",
              "secondary-fixed-dim": "#69e7e9",
              "on-surface-variant": "#5a6062",
              "surface-container-low": "#f1f4f5",
              "on-primary": "#fbf8f7",
              "surface-variant": "#dee3e6",
              "primary-fixed": "#e5e2e1",
              "on-surface": "#2d3335",
              "secondary-container": "#79f5f7",
              "tertiary-container": "#f69567",
              "surface": "#f8f9fa",
              "on-tertiary-container": "#541e00",
              "surface-container": "#ebeef0",
              "on-tertiary-fixed": "#290b00",
              "on-primary-container": "#525151",
              "secondary": "#006a6c",
              "inverse-surface": "#0c0f10",
              "primary-dim": "#545353",
              "on-secondary-fixed-variant": "#006668",
              "outline": "#767c7e",
              "on-tertiary-fixed-variant": "#622400",
              "error-dim": "#67040d",
              "surface-bright": "#f8f9fa",
              "on-primary-fixed-variant": "#5c5b5b",
              "tertiary-fixed-dim": "#e6895b",
              "secondary-fixed": "#79f5f7",
              "inverse-on-surface": "#9b9d9e",
              "on-background": "#2d3335",
              "inverse-primary": "#ffffff",
              "on-secondary-fixed": "#004748",
              "surface-container-lowest": "#ffffff",
              "primary": "#605f5e",
              "on-primary-fixed": "#403f3f",
              "surface-tint": "#605f5e",
              "tertiary": "#964a22",
              "surface-container-highest": "#dee3e6"
            },
            fontFamily: {
                            "headline": ["Instrument Serif"],
                            "body": ["Barlow"],
                            "label": ["Barlow"]
            },
            borderRadius: {"DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "1.5rem", "full": "9999px"},
          },
        },
      }
    </script>
<style>
      .material-symbols-outlined {
        font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
      }
      body {
                font-family: 'Barlow', sans-serif;
            }
            html, body {
                scrollbar-width: none;
                -ms-overflow-style: none;
            }
            html::-webkit-scrollbar,
            body::-webkit-scrollbar {
                width: 0;
                height: 0;
                display: none;
            }
            .seeker-side-scroll {
                scrollbar-width: none;
                -ms-overflow-style: none;
            }
            .seeker-side-scroll::-webkit-scrollbar {
                width: 0;
                height: 0;
                display: none;
                background: transparent;
            }
            .ts-heading {
                font-family: 'Instrument Serif', serif;
                font-style: italic;
                letter-spacing: -0.02em;
      }
      .sort-select {
                min-width: 220px;
                border-radius: 0.75rem;
                border: 1px solid rgba(255, 255, 255, 0.2);
                background: linear-gradient(145deg, rgba(255, 255, 255, 0.16), rgba(255, 255, 255, 0.05));
                color: #fff;
                font-weight: 600;
                letter-spacing: 0.01em;
                box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.18);
                transition: border-color .2s ease, box-shadow .2s ease, background .2s ease;
            }
            .sort-select:hover {
                border-color: rgba(255, 255, 255, 0.35);
                background: linear-gradient(145deg, rgba(255, 255, 255, 0.2), rgba(255, 255, 255, 0.07));
            }
            .sort-select:focus {
                outline: none;
                border-color: rgba(255, 255, 255, 0.45);
                box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.12);
            }
            .sort-select option {
                background: #0d1117;
                color: #fff;
            }
            .filter-select {
                width: 100%;
                border-radius: 0.9rem;
                border: 1px solid rgba(255, 255, 255, 0.2);
                background: linear-gradient(145deg, rgba(255, 255, 255, 0.14), rgba(255, 255, 255, 0.04));
                color: #fff;
                font-weight: 500;
                box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.15);
                transition: border-color .2s ease, box-shadow .2s ease, background .2s ease;
            }
            .filter-select:hover {
                border-color: rgba(255, 255, 255, 0.33);
                background: linear-gradient(145deg, rgba(255, 255, 255, 0.18), rgba(255, 255, 255, 0.06));
            }
            .filter-select:focus {
                outline: none;
                border-color: rgba(255, 255, 255, 0.45);
                box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.12);
            }
            .filter-select option {
                background: #0d1117;
                color: #fff;
            }
            input,
            select,
            button,
            textarea {
                font-family: 'Barlow', sans-serif;
            }
            input[type="date"] {
                color-scheme: dark;
                background: linear-gradient(145deg, rgba(255, 255, 255, 0.14), rgba(255, 255, 255, 0.04));
                border: 1px solid rgba(255, 255, 255, 0.2);
            }
            input[type="date"]:hover {
                border-color: rgba(255, 255, 255, 0.33);
                background: linear-gradient(145deg, rgba(255, 255, 255, 0.18), rgba(255, 255, 255, 0.06));
            }
            input[type="date"]:focus {
                outline: none;
                border-color: rgba(255, 255, 255, 0.45);
                box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.12);
            }
            input[type="date"]::-webkit-calendar-picker-indicator {
                filter: invert(1) opacity(0.82);
                cursor: pointer;
            }
            input[type="date"]::-webkit-calendar-picker-indicator:hover {
                filter: invert(1) opacity(1);
            }
            input[type="date"]::-webkit-datetime-edit,
            input[type="date"]::-webkit-date-and-time-value {
                color: #ffffff;
            }
            input[type="date"]:invalid::-webkit-datetime-edit {
                color: rgba(255, 255, 255, 0.55);
            }
    </style>
</head>
<body class="bg-[#020202] text-white">
<?php renderSeekerTopbar('jobs'); ?>
<div class="flex pt-20 min-h-screen">
<aside class="seeker-side-scroll fixed left-0 top-20 bottom-0 flex flex-col p-6 overflow-y-auto bg-[#090b0f] h-screen w-72 rounded-r-3xl border-r border-white/10 z-40">
<div class="mb-8">
<div class="flex items-center gap-3 mb-2">
<span class="text-lg font-bold text-white">Filters</span>
<span class="material-symbols-outlined text-white/70" style="font-size: 18px;">tune</span>
</div>
<p class="text-xs text-white/50">Refine your search</p>
</div>
<nav class="flex flex-col gap-1 flex-1 text-sm">
<button class="flex items-center gap-3 px-4 py-3 bg-white/10 text-white rounded-xl font-bold text-left" data-type="all" id="filterAll" type="button">
<span class="material-symbols-outlined">work</span>
                    All Jobs
                </button>
<button class="flex items-center gap-3 px-4 py-3 text-white/70 hover:bg-white/10 rounded-xl transition-all text-left" data-type="remote" type="button">
<span class="material-symbols-outlined">home_work</span>
                    Remote
                </button>
<button class="flex items-center gap-3 px-4 py-3 text-white/70 hover:bg-white/10 rounded-xl transition-all text-left" data-type="freelancer" type="button">
<span class="material-symbols-outlined">handshake</span>
                    Freelancer
                </button>
<button class="flex items-center gap-3 px-4 py-3 text-white/70 hover:bg-white/10 rounded-xl transition-all text-left" data-type="contract" type="button">
<span class="material-symbols-outlined">history_edu</span>
                    Contract
                </button>
<button class="flex items-center gap-3 px-4 py-3 text-white/70 hover:bg-white/10 rounded-xl transition-all text-left" data-type="internship" type="button">
<span class="material-symbols-outlined">school</span>
                    Internship
                </button>
</nav>
<div class="mt-8 pt-6 border-t border-outline-variant/10">
<a href="profile.php" class="w-full py-3 bg-white text-black rounded-lg font-bold text-sm mb-6 block text-center">Edit Profile</a>
<div class="flex flex-col gap-1">
<a class="flex items-center gap-3 px-4 py-3 text-white/70 hover:bg-white/10 rounded-xl transition-all" href="map.php">
<span class="material-symbols-outlined">map</span>
                        Nearby Talent
                    </a>
<a class="flex items-center gap-3 px-4 py-3 text-white/70 hover:bg-white/10 rounded-xl transition-all" href="dashboard.php">
<span class="material-symbols-outlined">dashboard</span>
                        Main Dashboard
                    </a>
</div>
</div>
</aside>
<main class="ml-72 flex-1 p-8 bg-[#05070b]">
<div class="max-w-7xl mx-auto">
<div class="grid grid-cols-12 gap-8 mb-12">
<div class="col-span-12 lg:col-span-4 rounded-xl p-8 flex flex-col justify-between relative overflow-hidden h-full border border-white/15" style="background: linear-gradient(145deg, rgba(255,255,255,0.12), rgba(255,255,255,0.04));">
<div class="relative z-10">
<h1 class="text-4xl ts-heading text-white leading-tight mb-4">Get your best profession</h1>
<p class="text-white/70 text-sm mb-8 max-w-xs">Discover curated live opportunities that match your skills and career goals.</p>
<a href="aggregator.php" class="bg-white text-black px-6 py-3 rounded-lg font-bold text-sm shadow-xl inline-block">Get Started</a>
</div>
<div class="absolute bottom-0 right-0 w-48 h-48 opacity-20">
<span class="material-symbols-outlined" style="font-size: 200px;">rocket_launch</span>
</div>
</div>
<div class="col-span-12 lg:col-span-8 grid grid-cols-2 gap-6">
<div class="rounded-xl p-6 flex flex-col justify-center items-center text-center border border-white/15" style="background: linear-gradient(145deg, rgba(255,255,255,0.10), rgba(255,255,255,0.03));">
<span class="material-symbols-outlined text-white text-4xl mb-4">analytics</span>
<span class="text-3xl font-extrabold text-white" id="jobCountStat">0</span>
<span class="text-sm text-white/70 font-medium">Live Feed Jobs</span>
</div>
<div class="rounded-xl p-6 flex flex-col justify-center items-center text-center border border-white/15" style="background: linear-gradient(145deg, rgba(255,255,255,0.10), rgba(255,255,255,0.03));">
<span class="material-symbols-outlined text-white text-4xl mb-4">bolt</span>
<span class="text-3xl font-extrabold text-white">48h</span>
<span class="text-sm text-white/70 font-medium">Avg. Response Time</span>
</div>
<div class="col-span-2 rounded-xl p-6 flex items-center justify-between shadow-sm border border-white/15" style="background: linear-gradient(145deg, rgba(255,255,255,0.10), rgba(255,255,255,0.03));">
<div class="flex items-center gap-6">
<div>
<p class="text-sm font-bold text-white">Welcome, <?php echo e((string) $profile['name']); ?></p>
<p class="text-xs text-white/70">Skill: <?php echo e((string) ($profile['skill'] ?: 'Not set')); ?> | Experience: <?php echo e((string) ($profile['experience'] ?: 'Not set')); ?></p>
</div>
</div>
<span class="material-symbols-outlined text-white">trending_up</span>
</div>
</div>
</div>
<div class="flex items-center justify-between mb-8">
<h2 class="text-4xl ts-heading text-white">Recommended Jobs <span id="jobCountBadge" class="text-sm text-white/70">(0)</span></h2>
<div class="flex gap-3 items-center">
<label class="text-sm text-white/70" for="sortJobs">Sort by</label>
<select id="sortJobs" class="sort-select text-sm px-3 py-2">
<option value="latest">Last updated</option>
<option value="az">Title A-Z</option>
<option value="payout_high">Payout: High to Low</option>
<option value="payout_low">Payout: Low to High</option>
</select>
</div>
</div>
<section class="mb-8 rounded-2xl border border-white/15 p-4 md:p-5" style="background: linear-gradient(145deg, rgba(255,255,255,0.10), rgba(255,255,255,0.03));">
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-6 gap-3 mb-3">
<div class="xl:col-span-2">
<label class="text-xs text-white/65 mb-1 block" for="globalSearch">Search</label>
<input id="globalSearch" type="text" class="w-full rounded-lg border border-white/20 text-sm px-3 py-2 bg-white/10 text-white placeholder-white/40" placeholder="Title, company, skill, location" />
</div>
<div>
<label class="text-xs text-white/65 mb-1 block" for="payoutFilter">Payout</label>
<select id="payoutFilter" class="filter-select text-sm px-3 py-2">
<option value="all">All payouts</option>
<option value="hourly">Hourly</option>
<option value="fixed">Fixed</option>
<option value="monthly">Monthly</option>
<option value="weekly">Weekly</option>
<option value="yearly">Yearly</option>
</select>
</div>
<div>
<label class="text-xs text-white/65 mb-1 block" for="dateFilter">Posted</label>
<select id="dateFilter" class="filter-select text-sm px-3 py-2">
<option value="all">Any time</option>
<option value="1">Last 24h</option>
<option value="7">Last 7 days</option>
<option value="30">Last 30 days</option>
</select>
</div>
<div>
<label class="text-xs text-white/65 mb-1 block" for="sourceFilter">Platform</label>
<select id="sourceFilter" class="filter-select text-sm px-3 py-2">
<option value="all">All platforms</option>
<option value="talentsync">TalentSync</option>
<option value="linkedin">LinkedIn</option>
<option value="indeed">Indeed</option>
<option value="naukri">Naukri</option>
<option value="zip_recruiter">ZipRecruiter</option>
<option value="google">Google Jobs</option>
<option value="glassdoor">Glassdoor</option>
<option value="bayt">Bayt</option>
<option value="bdjobs">Bdjobs</option>
<option value="freelance">Freelance Platforms</option>
<option value="other">Other platforms</option>
</select>
</div>
<div>
<label class="text-xs text-white/65 mb-1 block" for="modeFilter">Work mode</label>
<select id="modeFilter" class="filter-select text-sm px-3 py-2">
<option value="all">All modes</option>
<option value="remote">Remote</option>
<option value="hybrid">Hybrid</option>
<option value="onsite">On-site</option>
</select>
</div>
</div>
<div class="grid grid-cols-1 md:grid-cols-6 gap-3 items-end">
<div>
<label class="text-xs text-white/65 mb-1 block" for="levelFilter">Experience level</label>
<select id="levelFilter" class="filter-select text-sm px-3 py-2">
<option value="all">All levels</option>
<option value="junior">Junior</option>
<option value="mid">Mid</option>
<option value="senior">Senior</option>
</select>
</div>
<div>
<label class="text-xs text-white/65 mb-1 block" for="salaryFilter">Salary</label>
<select id="salaryFilter" class="filter-select text-sm px-3 py-2">
<option value="all">Any salary info</option>
<option value="specified">Salary specified</option>
<option value="unspecified">Salary not specified</option>
</select>
</div>
<div>
<label class="text-xs text-white/65 mb-1 block" for="jobTypeFilter">Job type</label>
<select id="jobTypeFilter" class="filter-select text-sm px-3 py-2">
<option value="all">All types</option>
<option value="fulltime">Full-time</option>
<option value="parttime">Part-time</option>
<option value="contract">Contract</option>
<option value="internship">Internship</option>
<option value="freelance">Freelance</option>
</select>
</div>
<div>
<label class="text-xs text-white/65 mb-1 block" for="dateFromFilter">From date</label>
<input id="dateFromFilter" type="date" class="w-full rounded-lg border border-white/20 text-sm px-3 py-2 bg-white/10 text-white" />
</div>
<div>
<label class="text-xs text-white/65 mb-1 block" for="dateToFilter">To date</label>
<input id="dateToFilter" type="date" class="w-full rounded-lg border border-white/20 text-sm px-3 py-2 bg-white/10 text-white" />
</div>
<div class="md:text-right">
<button id="clearFilters" type="button" class="px-4 py-2 rounded-lg border border-white/20 text-sm bg-white/10 text-white hover:bg-white/15">Clear Filters</button>
</div>
</div>
<div class="mt-3 text-xs text-white/60">Tip: combine platform + date range + salary to quickly find better matches.</div>
</section>
<section class="mb-8 rounded-2xl border border-white/15 p-4 md:p-5" style="background: linear-gradient(145deg, rgba(255,255,255,0.10), rgba(255,255,255,0.03));">
<div class="flex items-center justify-between gap-3 mb-3">
<div>
<h3 class="text-lg font-bold text-white">Need More Jobs By City or Company?</h3>
<p class="text-xs text-white/65">Use city/place or company name to fetch new jobs. Results are merged into JSON and shown here.</p>
</div>
<span class="material-symbols-outlined text-white/70">travel_explore</span>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-7 gap-3 items-end">
<div class="xl:col-span-2">
<label class="text-xs text-white/65 mb-1 block" for="fetchCity">City, place, or company (optional)</label>
<input id="fetchCity" type="text" class="w-full rounded-lg border border-white/20 text-sm px-3 py-2 bg-white/10 text-white placeholder-white/40" placeholder="e.g., Jaipur or Adobe" />
</div>
<div>
<label class="text-xs text-white/65 mb-1 block" for="fetchPrimaryType">First box is</label>
<select id="fetchPrimaryType" class="filter-select text-sm px-3 py-2">
<option value="auto">Auto detect</option>
<option value="location">City / place</option>
<option value="company">Company name</option>
</select>
</div>
<div>
<label class="text-xs text-white/65 mb-1 block" for="fetchField">Field / role</label>
<input id="fetchField" type="text" class="w-full rounded-lg border border-white/20 text-sm px-3 py-2 bg-white/10 text-white placeholder-white/40" placeholder="software engineer" />
</div>
<div>
<label class="text-xs text-white/65 mb-1 block" for="fetchJobType">Job type</label>
<select id="fetchJobType" class="filter-select text-sm px-3 py-2">
<option value="">Any</option>
<option value="full time">Full-time</option>
<option value="part time">Part-time</option>
<option value="contract">Contract</option>
<option value="internship">Internship</option>
<option value="freelance">Freelance</option>
</select>
</div>
<div>
<label class="text-xs text-white/65 mb-1 block" for="fetchCountry">Country</label>
<select id="fetchCountry" class="filter-select text-sm px-3 py-2">
<option value="India">India</option>
<option value="USA">USA</option>
<option value="UK">UK</option>
<option value="United Arab Emirates">UAE</option>
<option value="Singapore">Singapore</option>
</select>
</div>
<div>
<label class="text-xs text-white/65 mb-1 block" for="fetchSites">Sources</label>
<select id="fetchSites" class="filter-select text-sm px-3 py-2">
<option value="indeed,linkedin,naukri">Indeed + LinkedIn + Naukri</option>
<option value="indeed,linkedin">Indeed + LinkedIn</option>
<option value="linkedin">LinkedIn only</option>
<option value="indeed">Indeed only</option>
<option value="naukri">Naukri only</option>
</select>
</div>
</div>
<div class="mt-3 flex flex-wrap items-center gap-3">
<button id="fetchByCityBtn" type="button" class="px-4 py-2 rounded-lg border border-white/20 text-sm bg-white text-black font-bold hover:bg-white/90">Fetch Jobs</button>
<span id="fetchByCityStatus" class="text-xs text-white/65"></span>
</div>
</section>
<div id="jobCards" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-8">
<p class="text-white/70 text-sm">Loading cached jobs...</p>
</div>
</div>
</main>
</div>
<script>
    var jobs = [];
    var activeType = 'all';
    var seekerLocation = {
        city: <?php echo json_encode((string) ($profile['city'] ?? ''), JSON_UNESCAPED_UNICODE); ?>,
        lat: <?php echo json_encode(isset($profile['lat']) ? (float) $profile['lat'] : null); ?>,
        lng: <?php echo json_encode(isset($profile['lng']) ? (float) $profile['lng'] : null); ?>
    };
    var fetchCsrfToken = <?php echo json_encode(csrfToken(), JSON_UNESCAPED_UNICODE); ?>;
    var logoColorCache = {};
    var logoFailureCache = {};

    function companyPalette(job, index) {
        var companyName = String((job && job.company) || '').toLowerCase();
        var domain = companyDomainFromName(companyName);
        if (!domain) {
            domain = extractDomain((job && job.url) || '');
        }

        var brandByDomain = {
            'cisco.com': '30,98,193',
            'discord.com': '88,101,242',
            'nasdaq.com': '0,168,181',
            'google.com': '66,133,244',
            'microsoft.com': '0,120,215',
            'amazon.com': '255,153,0',
            'meta.com': '24,119,242',
            'apple.com': '148,163,184',
            'netflix.com': '229,9,20',
            'spotify.com': '30,215,96',
            'airbnb.com': '255,90,95',
            'uber.com': '46,46,46',
            'adobe.com': '255,0,0',
            'salesforce.com': '0,161,224',
            'linkedin.com': '10,102,194'
        };

        if (domain && brandByDomain[domain]) {
            return brandByDomain[domain];
        }

        return colorFromText(companyName || ((job && job.title) || ''), index);
    }

    function hslToRgb(h, s, l) {
        var c = (1 - Math.abs(2 * l - 1)) * s;
        var x = c * (1 - Math.abs((h / 60) % 2 - 1));
        var m = l - c / 2;
        var r = 0;
        var g = 0;
        var b = 0;

        if (h < 60) {
            r = c; g = x; b = 0;
        } else if (h < 120) {
            r = x; g = c; b = 0;
        } else if (h < 180) {
            r = 0; g = c; b = x;
        } else if (h < 240) {
            r = 0; g = x; b = c;
        } else if (h < 300) {
            r = x; g = 0; b = c;
        } else {
            r = c; g = 0; b = x;
        }

        return [
            Math.round((r + m) * 255),
            Math.round((g + m) * 255),
            Math.round((b + m) * 255)
        ];
    }

    function colorFromText(text, index) {
        var seed = hashValue(String(text || '') + '|' + String(index || 0));
        var hue = seed % 360;
        var saturation = 0.58;
        var lightness = 0.50;
        var rgb = hslToRgb(hue, saturation, lightness);
        return rgb.join(',');
    }

    function applyCardTheme(cardEl, rgbText) {
        if (!cardEl || !rgbText) {
            return;
        }
        cardEl.style.setProperty('--brand-rgb', rgbText);
    }

    function detectLogoColor(imgEl) {
        if (!imgEl || !imgEl.naturalWidth || !imgEl.naturalHeight) {
            return '';
        }

        try {
            var canvas = document.createElement('canvas');
            var ctx = canvas.getContext('2d');
            if (!ctx) {
                return '';
            }

            var sampleSize = 28;
            canvas.width = sampleSize;
            canvas.height = sampleSize;
            ctx.drawImage(imgEl, 0, 0, sampleSize, sampleSize);

            var data = ctx.getImageData(0, 0, sampleSize, sampleSize).data;
            var r = 0;
            var g = 0;
            var b = 0;
            var count = 0;

            for (var i = 0; i < data.length; i += 4) {
                var alpha = data[i + 3];
                if (alpha < 140) {
                    continue;
                }

                var rr = data[i];
                var gg = data[i + 1];
                var bb = data[i + 2];
                var max = Math.max(rr, gg, bb);
                var min = Math.min(rr, gg, bb);

                // Skip near-white/near-black neutral pixels to capture accent color.
                if (max > 245 && min > 245) {
                    continue;
                }
                if (max < 22) {
                    continue;
                }

                r += rr;
                g += gg;
                b += bb;
                count += 1;
            }

            if (!count) {
                return '';
            }

            var outR = Math.round(r / count);
            var outG = Math.round(g / count);
            var outB = Math.round(b / count);
            return outR + ',' + outG + ',' + outB;
        } catch (e) {
            return '';
        }
    }

    function sanitize(value) {
        return String(value || '').replace(/[&<>'"]/g, function (char) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                "'": '&#39;',
                '"': '&quot;'
            }[char];
        });
    }

    function randomSalary(index) {
        var min = 80 + (index * 8);
        var max = min + 35;
        return '$' + min + 'k - $' + max + 'k';
    }

    function hashValue(text) {
        var str = String(text || '');
        var hash = 0;
        for (var i = 0; i < str.length; i++) {
            hash = ((hash << 5) - hash) + str.charCodeAt(i);
            hash |= 0;
        }
        return Math.abs(hash);
    }

    function inferWorkMode(job) {
        var savedMode = normalizeText(job.work_mode || '');
        if (savedMode === 'remote' || savedMode === 'hybrid' || savedMode === 'onsite') {
            return savedMode;
        }

        var loc = String(job.location || '').toLowerCase();
        if (loc.indexOf('remote') > -1) {
            return 'remote';
        }
        if (loc.indexOf('hybrid') > -1) {
            return 'hybrid';
        }
        return 'onsite';
    }

    function inferLevel(job) {
        var savedLevel = normalizeText(job.experience_level || '');
        if (savedLevel === 'junior' || savedLevel === 'mid' || savedLevel === 'senior') {
            return savedLevel;
        }

        var text = ((job.title || '') + ' ' + (job.description || '')).toLowerCase();
        if (/(senior|lead|principal|architect|head)/.test(text)) {
            return 'senior';
        }
        if (/(junior|intern|trainee|entry)/.test(text)) {
            return 'junior';
        }
        return 'mid';
    }

    function normalizePayoutType(value) {
        var type = normalizeText(value || '');
        if (type === 'year' || type === 'annual' || type === 'annually') {
            return 'yearly';
        }
        if (type === 'month') {
            return 'monthly';
        }
        if (type === 'week') {
            return 'weekly';
        }
        if (type === 'hour') {
            return 'hourly';
        }
        if (type === 'hourly' || type === 'fixed' || type === 'monthly' || type === 'weekly' || type === 'yearly') {
            return type;
        }
        return 'unspecified';
    }

    function normalizeJobType(value) {
        var raw = normalizeText(value || '').replace(/[_\-]/g, ' ').replace(/\s+/g, ' ').trim();
        if (!raw) {
            return 'unspecified';
        }
        if (raw === 'fulltime' || raw === 'full time' || raw === 'full-time') {
            return 'fulltime';
        }
        if (raw === 'parttime' || raw === 'part time' || raw === 'part-time') {
            return 'parttime';
        }
        if (raw.indexOf('contract') > -1) {
            return 'contract';
        }
        if (raw.indexOf('intern') > -1) {
            return 'internship';
        }
        if (raw.indexOf('freelance') > -1) {
            return 'freelance';
        }
        return raw;
    }

    function parseBudgetAmount(job) {
        var text = String(job.budget || '').trim();
        if (!text) {
            return null;
        }

        var matches = text.match(/\d[\d,]*(?:\.\d+)?\s*[kK]?/g);
        if (!matches || !matches.length) {
            return null;
        }

        var values = matches.map(function (chunk) {
            var cleaned = String(chunk || '').trim();
            var isK = /[kK]$/.test(cleaned);
            cleaned = cleaned.replace(/[kK]$/, '');
            var num = Number(cleaned.replace(/,/g, ''));
            if (!Number.isFinite(num)) {
                return null;
            }
            return isK ? (num * 1000) : num;
        }).filter(function (n) {
            return Number.isFinite(n);
        });

        if (!values.length) {
            return null;
        }

        return Math.max.apply(null, values);
    }

    function payoutLabel(job) {
        var rawBudget = String(job.budget || '').trim();
        if (rawBudget) {
            return rawBudget;
        }
        return 'Not specified';
    }

    function postedDaysAgo(job, index) {
        if (job.created_at) {
            var published = new Date(job.created_at);
            if (!isNaN(published.getTime())) {
                var diffMs = Date.now() - published.getTime();
                var diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));
                if (diffDays < 0) {
                    return 0;
                }
                return diffDays;
            }
        }

        var source = String(job.source || '').toLowerCase();
        if (source.indexOf('talentsync') > -1) {
            return index % 8;
        }
        return hashValue((job.title || '') + (job.company || '') + source) % 36;
    }

    function postedLabel(daysAgo) {
        if (daysAgo <= 0) {
            return 'Today';
        }
        if (daysAgo === 1) {
            return '1 day ago';
        }
        return daysAgo + ' days ago';
    }

    function sourceKey(source) {
        var s = String(source || '').toLowerCase();
        if (s.indexOf('talentsync') > -1) {
            return 'talentsync';
        }
        if (s.indexOf('linkedin') > -1) {
            return 'linkedin';
        }
        if (s.indexOf('indeed') > -1) {
            return 'indeed';
        }
        if (s.indexOf('naukri') > -1) {
            return 'naukri';
        }
        if (s.indexOf('zip_recruiter') > -1 || s.indexOf('ziprecruiter') > -1) {
            return 'zip_recruiter';
        }
        if (s.indexOf('google') > -1) {
            return 'google';
        }
        if (s.indexOf('glassdoor') > -1) {
            return 'glassdoor';
        }
        if (s.indexOf('bayt') > -1) {
            return 'bayt';
        }
        if (s.indexOf('bdjobs') > -1) {
            return 'bdjobs';
        }
        if (/(upwork|fiverr|freelancer|guru|peopleperhour|toptal)/.test(s)) {
            return 'freelance';
        }
        return 'other';
    }

    function isFreelanceJob(job) {
        var text = (
            (job.title || '') + ' ' +
            (job.description || '') + ' ' +
            (job.source || '') + ' ' +
            (job.job_type || '') + ' ' +
            (job.payout_type || '') + ' ' +
            (job.work_mode || '')
        ).toLowerCase();

        return /(freelance|gig|contract|hourly|project based|project-based|part time|part-time|consultant|independent)/.test(text);
    }

    function toNumberOrNull(value) {
        var n = Number(value);
        return Number.isFinite(n) ? n : null;
    }

    function normalizeText(value) {
        return String(value || '').trim().toLowerCase();
    }

    function haversineKm(lat1, lng1, lat2, lng2) {
        var r = 6371;
        var dLat = (lat2 - lat1) * Math.PI / 180;
        var dLng = (lng2 - lng1) * Math.PI / 180;
        var a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
            Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
            Math.sin(dLng / 2) * Math.sin(dLng / 2);
        var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        return r * c;
    }

    function locationBoost(job, query) {
        var score = 0;
        var loc = normalizeText(job.location || '');
        var city = normalizeText(seekerLocation.city);
        var q = normalizeText(query);

        if (city && loc.indexOf(city) > -1) {
            score += 120;
        }

        if (q && loc.indexOf(q) > -1) {
            score += 80;
        }

        var seekerLat = toNumberOrNull(seekerLocation.lat);
        var seekerLng = toNumberOrNull(seekerLocation.lng);
        var jobLat = toNumberOrNull(job.lat);
        var jobLng = toNumberOrNull(job.lng);

        if (seekerLat !== null && seekerLng !== null && jobLat !== null && jobLng !== null) {
            var distanceKm = haversineKm(seekerLat, seekerLng, jobLat, jobLng);
            if (distanceKm <= 25) {
                score += 110;
            } else if (distanceKm <= 75) {
                score += 70;
            } else if (distanceKm <= 150) {
                score += 40;
            }
            job._distanceKm = distanceKm;
        } else {
            job._distanceKm = null;
        }

        if (loc.indexOf('remote') > -1) {
            score += 10;
        }

        return score;
    }

    function getJobIcon(title) {
        var t = String(title || '').toLowerCase();

        if (/(ui|ux|design|designer|graphic|artist|illustrator|brand)/.test(t)) {
            return { icon: 'palette', bg: '255,145,102' };
        }
        if (/(editor|content|writer|copy|proofread|review)/.test(t)) {
            return { icon: 'edit_note', bg: '255,173,79' };
        }
        if (/(developer|engineer|software|full stack|frontend|backend|devops|qa|sre)/.test(t)) {
            return { icon: 'laptop_mac', bg: '96,165,250' };
        }
        if (/(data|analyst|analytics|scientist|ml|ai)/.test(t)) {
            return { icon: 'query_stats', bg: '56,189,248' };
        }
        if (/(support|customer|service|helpdesk)/.test(t)) {
            return { icon: 'support_agent', bg: '167,139,250' };
        }
        if (/(marketing|seo|growth|social|community)/.test(t)) {
            return { icon: 'campaign', bg: '251,113,133' };
        }
        if (/(manager|lead|director|coordinator|operations)/.test(t)) {
            return { icon: 'manage_accounts', bg: '52,211,153' };
        }

        return { icon: 'work', bg: '148,163,184' };
    }

    function extractDomain(rawUrl) {
        try {
            var parsed = new URL(String(rawUrl || ''));
            return (parsed.hostname || '').replace(/^www\./i, '').trim();
        } catch (e) {
            return '';
        }
    }

    function companyDomainFromName(companyName) {
        var name = String(companyName || '').trim().toLowerCase();
        if (!name) {
            return '';
        }

        var known = {
            'cisco': 'cisco.com',
            'discord': 'discord.com',
            'nasdaq': 'nasdaq.com',
            'google': 'google.com',
            'microsoft': 'microsoft.com',
            'amazon': 'amazon.com',
            'meta': 'meta.com',
            'apple': 'apple.com',
            'netflix': 'netflix.com',
            'spotify': 'spotify.com',
            'uber': 'uber.com',
            'adobe': 'adobe.com',
            'oracle': 'oracle.com',
            'ibm': 'ibm.com',
            'nvidia': 'nvidia.com',
            'tesla': 'tesla.com'
        };

        var keys = Object.keys(known);
        for (var i = 0; i < keys.length; i++) {
            if (name.indexOf(keys[i]) > -1) {
                return known[keys[i]];
            }
        }

        var slug = name.replace(/[^a-z0-9]+/g, '');
        if (!slug) {
            return '';
        }

        return slug + '.com';
    }

    function resolveCardLogo(job) {
        // Primary logo source: DuckDuckGo favicon API using company/domain.
        var domain = companyDomainFromName(job.company || '');
        if (!domain) {
            domain = extractDomain(job.url || '');
        }
        if (!domain) {
            return '';
        }

        // Route through same-origin proxy so canvas color sampling is allowed.
        return 'api/company_logo.php?domain=' + encodeURIComponent(domain);
    }

    function resolveCardLogoFallback(job) {
        // No secondary logo source by design: fallback is job-type icon.
        return '';
    }

    function debounce(fn, waitMs) {
        var timer = null;
        return function () {
            var ctx = this;
            var args = arguments;
            if (timer) {
                clearTimeout(timer);
            }
            timer = setTimeout(function () {
                fn.apply(ctx, args);
            }, waitMs);
        };
    }

    function applyFilters() {
        var q = ($('#globalSearch').val() || '').toLowerCase();
        var payoutFilter = $('#payoutFilter').val() || 'all';
        var dateFilterDays = Number($('#dateFilter').val() || '0');
        var dateFromFilter = ($('#dateFromFilter').val() || '').trim();
        var dateToFilter = ($('#dateToFilter').val() || '').trim();
        var sourceFilter = $('#sourceFilter').val() || 'all';
        var modeFilter = $('#modeFilter').val() || 'all';
        var levelFilter = $('#levelFilter').val() || 'all';
        var salaryFilter = $('#salaryFilter').val() || 'all';
        var jobTypeFilter = $('#jobTypeFilter').val() || 'all';

        var fromDateObj = null;
        var toDateObj = null;
        if (dateFromFilter) {
            fromDateObj = new Date(dateFromFilter + 'T00:00:00');
        }
        if (dateToFilter) {
            toDateObj = new Date(dateToFilter + 'T23:59:59');
        }

        var filtered = jobs.filter(function (job) {
            var blob = ((job.title || '') + ' ' + (job.company || '') + ' ' + (job.location || '') + ' ' + (job.source || '')).toLowerCase();
            var textMatch = !q || blob.indexOf(q) > -1;
            var typeMatch = true;
            if (activeType === 'remote') {
                typeMatch = (job.location || '').toLowerCase().indexOf('remote') > -1;
            } else if (activeType === 'freelancer') {
                typeMatch = isFreelanceJob(job);
            } else if (activeType !== 'all') {
                typeMatch = blob.indexOf(activeType) > -1;
            }

            var payoutModel = normalizePayoutType(job.payout_type || '');
            var jobType = normalizeJobType(job.job_type || '');
            var workMode = inferWorkMode(job);
            var level = inferLevel(job);
            var daysAgo = postedDaysAgo(job, Number(job._listIndex || 0));
            var source = sourceKey(job.source || '');
            var createdAt = job.created_at ? new Date(job.created_at) : null;
            var hasValidCreatedAt = createdAt && !isNaN(createdAt.getTime());
            var hasSalary = String(job.budget || '').trim() !== '';

            var payoutMatch = payoutFilter === 'all' ? true : payoutModel === payoutFilter;
            var dateMatch = dateFilterDays > 0 ? daysAgo <= dateFilterDays : true;
            if (fromDateObj && toDateObj && fromDateObj.getTime() > toDateObj.getTime()) {
                var tmpDate = fromDateObj;
                fromDateObj = toDateObj;
                toDateObj = tmpDate;
            }
            if (fromDateObj) {
                dateMatch = dateMatch && hasValidCreatedAt && createdAt.getTime() >= fromDateObj.getTime();
            }
            if (toDateObj) {
                dateMatch = dateMatch && hasValidCreatedAt && createdAt.getTime() <= toDateObj.getTime();
            }
            var sourceMatch = sourceFilter === 'all' ? true : source === sourceFilter;
            var modeMatch = modeFilter === 'all' ? true : workMode === modeFilter;
            var levelMatch = levelFilter === 'all' ? true : level === levelFilter;
            var salaryMatch = salaryFilter === 'all' ? true : (salaryFilter === 'specified' ? hasSalary : !hasSalary);
            var jobTypeMatch = jobTypeFilter === 'all' ? true : jobType === jobTypeFilter;

            var locScore = locationBoost(job, q);
            job._searchScore = (textMatch ? 100 : 0) + locScore;
            job._payoutModel = payoutModel;
            job._jobType = jobType;
            job._workMode = workMode;
            job._level = level;
            job._daysAgo = daysAgo;
            job._payoutAmount = parseBudgetAmount(job);

            if (!q) {
                return typeMatch && payoutMatch && dateMatch && sourceMatch && modeMatch && levelMatch && salaryMatch && jobTypeMatch;
            }

            // Keep textual matching as baseline, but allow nearby coordinate hits.
            var nearbyHit = locScore >= 70;
            return (textMatch || nearbyHit) && typeMatch && payoutMatch && dateMatch && sourceMatch && modeMatch && levelMatch && salaryMatch && jobTypeMatch;
        });

        if ($('#sortJobs').val() === 'az') {
            filtered.sort(function (a, b) {
                return (a.title || '').localeCompare(b.title || '');
            });
        } else if ($('#sortJobs').val() === 'payout_high') {
            filtered.sort(function (a, b) {
                return Number(b._payoutAmount || 0) - Number(a._payoutAmount || 0);
            });
        } else if ($('#sortJobs').val() === 'payout_low') {
            filtered.sort(function (a, b) {
                return Number(a._payoutAmount || 0) - Number(b._payoutAmount || 0);
            });
        } else {
            filtered.sort(function (a, b) {
                var aScore = Number(a._searchScore || 0);
                var bScore = Number(b._searchScore || 0);
                return bScore - aScore;
            });
        }

        renderJobs(filtered);
    }

    function renderJobs(data) {
        $('#jobCountStat').text(data.length);
        $('#jobCountBadge').text('(' + data.length + ')');

        if (!data.length) {
            $('#jobCards').html('<p class="text-white/70 text-sm">No API jobs found right now.</p>');
            return;
        }

        var html = '';
        data.forEach(function (job, index) {
            var titleRaw = job.title || 'Untitled';
            var companyRaw = job.company || 'Unknown company';
            var locationRaw = job.location || 'Remote';
            var sourceRaw = job.source || 'API';
            var urlRaw = job.url || '';
            var descRaw = job.description || '';
            var daysAgo = Number(job._daysAgo || postedDaysAgo(job, index));
            var payoutModelRaw = (job._payoutModel || normalizePayoutType(job.payout_type || ''));
            var payoutModel = sanitize(payoutModelRaw === 'unspecified' ? 'payout n/a' : payoutModelRaw);
            var payoutText = sanitize(payoutLabel(job));
            var workMode = sanitize((job._workMode || inferWorkMode(job)).replace('onsite', 'on-site'));
            var level = sanitize(job._level || inferLevel(job));
            var deadlineText = '';

            if (job.application_deadline) {
                var deadlineDate = new Date(job.application_deadline);
                if (!isNaN(deadlineDate.getTime())) {
                    deadlineText = sanitize(deadlineDate.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }));
                }
            }

            var title = sanitize(titleRaw);
            var company = sanitize(companyRaw);
            var location = sanitize(locationRaw);
            var source = sanitize(sourceRaw);
            var url = sanitize(urlRaw);
            var posted = sanitize(postedLabel(daysAgo));
            var rgb = companyPalette(job, index);
            var chipStyle = 'style="background:rgba(var(--brand-rgb),0.24); border:1px solid rgba(var(--brand-rgb),0.42); color:#fff;"';
            var roleIcon = getJobIcon(title);
            var roleIconColor = 'rgb(' + roleIcon.bg + ')';
            var logoUrl = resolveCardLogo(job);
            if (logoUrl && logoFailureCache[logoUrl]) {
                logoUrl = '';
            }
            var logo = sanitize(logoUrl);
            var fallbackLogo = sanitize(resolveCardLogoFallback(job));
            var logoAlt = sanitize(companyRaw + ' logo');
            var effectiveRgb = rgb;
            if (logoUrl && logoColorCache[logoUrl]) {
                effectiveRgb = logoColorCache[logoUrl];
            }
            var distanceChip = '';
            var detailsUrl = 'job_preview.php?' +
                'title=' + encodeURIComponent(String(titleRaw)) +
                '&company=' + encodeURIComponent(String(companyRaw)) +
                '&location=' + encodeURIComponent(String(locationRaw)) +
                '&source=' + encodeURIComponent(String(sourceRaw)) +
                '&url=' + encodeURIComponent(String(urlRaw)) +
                '&description=' + encodeURIComponent(String(descRaw));

            if (job._distanceKm !== null && Number.isFinite(job._distanceKm)) {
                distanceChip = '<span class="px-3 py-1 rounded-full text-[11px] font-medium" ' + chipStyle + '>' + Math.round(job._distanceKm) + ' km away</span>';
            } else if (seekerLocation.city && location.toLowerCase().indexOf(String(seekerLocation.city).toLowerCase()) > -1) {
                distanceChip = '<span class="px-3 py-1 rounded-full text-[11px] font-medium" ' + chipStyle + '>Same city</span>';
            }

            html += '<div class="rounded-xl p-6 flex flex-col transition-all hover:translate-y-[-4px] hover:shadow-xl" data-job-card="1" style="--brand-rgb:' + effectiveRgb + '; background:linear-gradient(145deg, rgba(var(--brand-rgb),0.34), rgba(var(--brand-rgb),0.14) 45%, rgba(255,255,255,0.06) 100%); border:1px solid rgba(var(--brand-rgb),0.45)">';
            html += '<div class="flex justify-between items-start mb-6">';
            html += '<div class="w-12 h-12 bg-white/90 rounded-xl flex items-center justify-center shadow-sm">';
            if (logo) {
                html += '<img src="' + logo + '" alt="' + logoAlt + '" class="w-8 h-8 object-contain" loading="lazy" decoding="async" data-company-logo="1" data-fallback-src="' + fallbackLogo + '">';
                html += '<span class="material-symbols-outlined" style="display:none; font-size: 28px; color:' + roleIconColor + ';">' + roleIcon.icon + '</span>';
            } else {
                html += '<span class="material-symbols-outlined" style="font-size: 28px; color:' + roleIconColor + ';">' + roleIcon.icon + '</span>';
            }
            html += '</div>';
            html += '<span class="px-3 py-1 bg-white/30 backdrop-blur-sm rounded-full text-[10px] font-bold uppercase tracking-wider text-white">' + posted + '</span>';
            html += '</div>';
            html += '<div class="mb-4">';
            html += '<h3 class="text-title-md font-bold text-white mb-1 leading-tight">' + title + '</h3>';
            html += '<p class="text-body-md text-white/75">' + company + ' • ' + location + '</p>';
            html += '</div>';
            html += '<div class="flex flex-wrap gap-2 mb-8">';
            html += '<span class="px-3 py-1 rounded-full text-[11px] font-medium" ' + chipStyle + '>Live Feed</span>';
            html += '<span class="px-3 py-1 rounded-full text-[11px] font-medium" ' + chipStyle + '>' + source + '</span>';
            html += '<span class="px-3 py-1 rounded-full text-[11px] font-medium" ' + chipStyle + '>' + payoutModel + '</span>';
            html += '<span class="px-3 py-1 rounded-full text-[11px] font-medium" ' + chipStyle + '>' + workMode + '</span>';
            html += '<span class="px-3 py-1 rounded-full text-[11px] font-medium" ' + chipStyle + '>' + level + '</span>';
            if (deadlineText) {
                html += '<span class="px-3 py-1 rounded-full text-[11px] font-medium" ' + chipStyle + '>Deadline: ' + deadlineText + '</span>';
            }
            html += distanceChip;
            html += '</div>';
            html += '<div class="mt-auto flex items-center justify-between">';
            html += '<div class="font-bold text-lg text-white">' + payoutText + '</div>';
            html += '<a href="' + detailsUrl + '" class="bg-black text-white px-5 py-2 rounded-lg font-bold text-sm shadow-sm border border-white/20">Details</a>';
            html += '</div>';
            html += '</div>';
        });

        $('#jobCards').html(html);

        $('#jobCards img[data-company-logo]').on('load', function () {
            var card = this.closest('[data-job-card]');
            var sensed = detectLogoColor(this);
            var src = String(this.currentSrc || this.src || '').trim();
            if (card && sensed) {
                if (src) {
                    logoColorCache[src] = sensed;
                }
                applyCardTheme(card, sensed);
            }
        });

        $('#jobCards img[data-company-logo]').on('error', function () {
            var img = this;
            var currentSrc = String(img.currentSrc || img.src || '').trim();
            var fallbackSrc = String(img.getAttribute('data-fallback-src') || '');
            var fallbackTried = img.getAttribute('data-fallback-tried') === '1';

            if (!fallbackTried && fallbackSrc) {
                img.setAttribute('data-fallback-tried', '1');
                img.src = fallbackSrc;
                return;
            }

            if (currentSrc) {
                logoFailureCache[currentSrc] = true;
            }

            img.style.display = 'none';
            if (img.nextElementSibling) {
                img.nextElementSibling.style.display = 'inline-block';
            }
        });
    }

    function loadLiveJobs() {
        $.getJSON('api/live_jobs.php', function (res) {
            jobs = res.jobs || [];
            jobs.forEach(function (job, idx) {
                job._listIndex = idx;
            });
            applyFilters();
        }).fail(function (xhr) {
            var message = 'Unable to load jobs right now.';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                message = xhr.responseJSON.message;
            }
            $('#jobCards').html('<p class="text-white/70 text-sm">' + sanitize(message) + '</p>');
        });
    }

    loadLiveJobs();
    function resetAllFilters() {
        $('#globalSearch').val('');
        $('#payoutFilter').val('all');
        $('#dateFilter').val('all');
        $('#dateFromFilter').val('');
        $('#dateToFilter').val('');
        $('#sourceFilter').val('all');
        $('#modeFilter').val('all');
        $('#levelFilter').val('all');
        $('#salaryFilter').val('all');
        $('#jobTypeFilter').val('all');
        $('#sortJobs').val('latest');
        activeType = 'all';
        $('aside button[data-type]').removeClass('bg-white/10 text-white font-bold').addClass('text-white/70');
        $('#filterAll').addClass('bg-white/10 text-white font-bold').removeClass('text-white/70');
        applyFilters();
    }


    var debouncedApplyFilters = debounce(applyFilters, 180);

    $('#globalSearch').on('input', function () {
        debouncedApplyFilters();
    });

    $('#sortJobs').on('change', function () {
        applyFilters();
    });

    $('#payoutFilter, #dateFilter, #dateFromFilter, #dateToFilter, #sourceFilter, #modeFilter, #levelFilter, #salaryFilter, #jobTypeFilter').on('change', function () {
        applyFilters();
    });

    $('#clearFilters').on('click', function () {
        resetAllFilters();
    });

    $('aside button[data-type]').on('click', function () {
        $('aside button[data-type]').removeClass('bg-white/10 text-white font-bold').addClass('text-white/70');
        $(this).addClass('bg-white/10 text-white font-bold').removeClass('text-white/70');
        activeType = $(this).data('type');
        applyFilters();
    });

    $('#fetchByCityBtn').on('click', function () {
        var city = ($('#fetchCity').val() || '').trim();
        var primaryType = ($('#fetchPrimaryType').val() || 'auto').trim();
        var field = ($('#fetchField').val() || '').trim();
        var jobType = ($('#fetchJobType').val() || '').trim();
        var country = ($('#fetchCountry').val() || 'India').trim();
        var sites = ($('#fetchSites').val() || 'indeed,linkedin,naukri').trim();

        if (!city && !field) {
            $('#fetchByCityStatus').text('Please enter a role or city/place/company.').removeClass('text-emerald-300').addClass('text-rose-300');
            return;
        }

        $('#fetchByCityBtn').prop('disabled', true).addClass('opacity-60 cursor-not-allowed');
        var fetchScope;
        if (primaryType === 'company' && city) {
            fetchScope = 'for company ' + city + ' in ' + country;
        } else if (city) {
            fetchScope = 'for ' + city;
        } else {
            fetchScope = 'for ' + (field || 'selected role') + ' in ' + country;
        }
        $('#fetchByCityStatus').text('Fetching jobs ' + fetchScope + '... this can take up to 1-2 minutes.').removeClass('text-rose-300 text-emerald-300').addClass('text-white/70');

        $.ajax({
            url: 'api/fetch_jobs.php',
            method: 'POST',
            dataType: 'json',
            data: {
                csrf_token: fetchCsrfToken,
                city: city,
                query_type: primaryType,
                field: field,
                job_type: jobType,
                country: country,
                sites: sites
            }
        }).done(function (res) {
            var msg = (res && res.message) ? res.message : 'Fetch completed.';
            resetAllFilters();
            $('#fetchByCityStatus').text(msg + ' Filters reset so new jobs are visible.').removeClass('text-rose-300 text-white/70').addClass('text-emerald-300');
            loadLiveJobs();
        }).fail(function (xhr) {
            var message = 'Fetch failed. Please try again.';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                message = xhr.responseJSON.message;
            }
            if (xhr.responseJSON && xhr.responseJSON.details) {
                message += ' ' + xhr.responseJSON.details;
            }
            $('#fetchByCityStatus').text(message).removeClass('text-emerald-300 text-white/70').addClass('text-rose-300');
        }).always(function () {
            $('#fetchByCityBtn').prop('disabled', false).removeClass('opacity-60 cursor-not-allowed');
        });
    });
</script>
</body></html>
