<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TalentSync PRO</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        heading: ['Instrument Serif', 'serif'],
                        body: ['Barlow', 'sans-serif']
                    }
                }
            }
        };
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@300;400;500;600&family=Instrument+Serif:ital@1&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body class="bg-black overflow-visible">
    <nav class="fixed top-4 left-0 right-0 z-50 px-4 md:px-8">
        <div class="max-w-7xl mx-auto flex items-center justify-between">
            <div class="rounded-full liquid-glass px-4 py-2 flex items-center justify-center text-xs font-medium">TalentSync</div>
            <div class="liquid-glass rounded-full px-2 py-2 flex items-center gap-2 text-sm">
                <a class="px-3 py-1 text-white/90" href="#">Home</a>
                <a class="px-3 py-1 text-white/90" href="#roles">Roles</a>
                <a class="px-3 py-1 text-white/90" href="#modules">Modules</a>
                <a class="px-3 py-1 text-white/90" href="#architecture">Architecture</a>
                <a class="px-3 py-1 text-white/90" href="#syllabus">Syllabus</a>
                <a class="px-4 py-2 rounded-full bg-white text-black font-medium" href="<?php echo isset($_SESSION['user_id']) ? 'dashboard.php' : 'register.php'; ?>">Launch App</a>
            </div>
        </div>
    </nav>

    <section class="relative h-[960px] bg-black overflow-visible">
        <video class="absolute inset-0 w-full h-full object-cover z-0" autoplay loop muted playsinline poster="images/hero_bg.jpeg">
            <source src="https://videos.pexels.com/video-files/3130182/3130182-hd_1920_1080_30fps.mp4" type="video/mp4">
        </video>
        <div class="absolute inset-0 bg-black/5 z-0"></div>
        <div class="absolute bottom-0 left-0 right-0 z-[1] h-[300px]" style="background: linear-gradient(to bottom, transparent, black);"></div>

        <div class="relative z-10 max-w-6xl mx-auto px-6 pt-[150px] min-h-[960px] flex flex-col items-center text-center">
            <div class="liquid-glass rounded-full p-1 inline-flex items-center gap-3 mb-8">
                <span class="bg-white text-black rounded-full text-xs px-3 py-1">New</span>
                <span class="text-white/90 text-sm pr-3">TalentSync PRO: Freelance + Job Aggregation Platform</span>
            </div>

            <h1 data-blur-text="One Hub for Jobs Freelancers and Hiring" class="text-5xl md:text-7xl lg:text-[5.2rem] font-heading italic text-white leading-[0.8] tracking-[-3px]"></h1>
            <p class="mt-8 text-white/70 text-sm md:text-base max-w-2xl font-body font-light">
                TalentSync PRO unifies opportunities from API feeds, RSS, JSON simulations, and internal MySQL data. Job Seekers build profiles, upload resumes, and chat in real time. Job Providers post jobs, search candidates, and hire faster with role-based secure workflows.
            </p>

            <div class="mt-10 flex flex-wrap items-center justify-center gap-4">
                <a href="register.php" class="liquid-glass-strong rounded-full px-6 py-3 inline-flex items-center gap-2">Create Account <span>↗</span></a>
                <a href="login.php" class="liquid-glass rounded-full px-6 py-3 inline-flex items-center gap-2">Login <span>↗</span></a>
                <a href="aggregator.php" class="text-white/80 inline-flex items-center gap-2">View Unified Feed <span>▶</span></a>
            </div>

            <div class="mt-auto pb-8 pt-16 w-full">
                <div class="text-center max-w-4xl mx-auto liquid-glass rounded-3xl p-6 grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div><p class="text-3xl font-heading italic">2</p><p class="text-white/60 text-xs">User Roles</p></div>
                    <div><p class="text-3xl font-heading italic">13</p><p class="text-white/60 text-xs">Core Modules</p></div>
                    <div><p class="text-3xl font-heading italic">4</p><p class="text-white/60 text-xs">Data Layers</p></div>
                    <div><p class="text-3xl font-heading italic">100%</p><p class="text-white/60 text-xs">Module 3-6 Coverage</p></div>
                </div>
            </div>
        </div>
    </section>

    <section id="roles" class="py-24 px-6 md:px-16 lg:px-24">
        <div class="max-w-7xl mx-auto">
            <span class="section-badge liquid-glass">Dual Login System</span>
            <h2 class="text-4xl md:text-5xl lg:text-6xl font-heading italic text-white tracking-tight leading-[0.9]">Two roles. One connected workflow.</h2>
            <div class="mt-12 grid grid-cols-1 md:grid-cols-2 gap-6">
                <article class="liquid-glass rounded-3xl p-8">
                    <p class="text-xs text-white/60 mb-2">Job Seeker (Freelancer)</p>
                    <h3 class="text-3xl font-heading italic">Build profile. Apply. Chat.</h3>
                    <ul class="mt-4 space-y-2 text-white/70 text-sm">
                        <li>Create detailed skill profile</li>
                        <li>Upload resume PDF and avatar/image</li>
                        <li>Use smart search to find opportunities</li>
                        <li>Message providers in real time</li>
                    </ul>
                </article>
                <article class="liquid-glass rounded-3xl p-8">
                    <p class="text-xs text-white/60 mb-2">Job Provider (Client)</p>
                    <h3 class="text-3xl font-heading italic">Post jobs. Find talent. Hire.</h3>
                    <ul class="mt-4 space-y-2 text-white/70 text-sm">
                        <li>Publish requirements with budget/location</li>
                        <li>Filter freelancers by skill and experience</li>
                        <li>Chat directly for fast hiring decisions</li>
                        <li>Manage opportunities from one dashboard</li>
                    </ul>
                </article>
            </div>
        </div>
    </section>

    <section id="modules" class="relative py-24 px-6 md:px-16 lg:px-24">
        <video class="absolute inset-0 w-full h-full object-cover z-0" autoplay loop muted playsinline>
            <source src="https://videos.pexels.com/video-files/3255275/3255275-hd_1920_1080_25fps.mp4" type="video/mp4">
        </video>
        <div class="absolute top-0 left-0 right-0 h-[200px] z-[1] fade-top"></div>
        <div class="absolute bottom-0 left-0 right-0 h-[200px] z-[1] fade-bottom"></div>
        <div class="relative z-10 max-w-7xl mx-auto">
            <span class="section-badge liquid-glass">Core System Modules</span>
            <h2 class="text-4xl md:text-5xl lg:text-6xl font-heading italic text-white tracking-tight leading-[0.9]">Everything needed for the complete app.</h2>
            <div class="mt-12 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <article class="liquid-glass rounded-2xl p-5"><h3 class="font-heading italic text-2xl">A. Auth + CAPTCHA</h3><p class="text-white/60 text-sm mt-2">Session login, role-based access, bot prevention.</p></article>
                <article class="liquid-glass rounded-2xl p-5"><h3 class="font-heading italic text-2xl">B. Avatar API</h3><p class="text-white/60 text-sm mt-2">DiceBear fallback for instant user identity visuals.</p></article>
                <article class="liquid-glass rounded-2xl p-5"><h3 class="font-heading italic text-2xl">C. Profile System</h3><p class="text-white/60 text-sm mt-2">Skills, experience, bio, image upload, processing.</p></article>
                <article class="liquid-glass rounded-2xl p-5"><h3 class="font-heading italic text-2xl">D. Resume Upload</h3><p class="text-white/60 text-sm mt-2">Secure PDF handling and profile linkage.</p></article>
                <article class="liquid-glass rounded-2xl p-5"><h3 class="font-heading italic text-2xl">E. Real-Time Chat</h3><p class="text-white/60 text-sm mt-2">AJAX polling with persistent message history.</p></article>
                <article class="liquid-glass rounded-2xl p-5"><h3 class="font-heading italic text-2xl">F. Aggregator Core</h3><p class="text-white/60 text-sm mt-2">Merge JSON, DB, API, and RSS into one feed.</p></article>
                <article class="liquid-glass rounded-2xl p-5"><h3 class="font-heading italic text-2xl">G. Smart Search</h3><p class="text-white/60 text-sm mt-2">Instant jQuery filters + backend SQL search.</p></article>
                <article class="liquid-glass rounded-2xl p-5"><h3 class="font-heading italic text-2xl">H. Map Discovery</h3><p class="text-white/60 text-sm mt-2">Latitude/longitude capture for nearby matching.</p></article>
                <article class="liquid-glass rounded-2xl p-5"><h3 class="font-heading italic text-2xl">I. Email Alerts</h3><p class="text-white/60 text-sm mt-2">Registration and activity notification support.</p></article>
                <article class="liquid-glass rounded-2xl p-5"><h3 class="font-heading italic text-2xl">J. Cookies</h3><p class="text-white/60 text-sm mt-2">Preference and short persistence support.</p></article>
                <article class="liquid-glass rounded-2xl p-5"><h3 class="font-heading italic text-2xl">K. Error Handling</h3><p class="text-white/60 text-sm mt-2">Defensive flows and safe failures.</p></article>
                <article class="liquid-glass rounded-2xl p-5"><h3 class="font-heading italic text-2xl">L. Regex Validation</h3><p class="text-white/60 text-sm mt-2">Strict input validation on forms and fields.</p></article>
                <article class="liquid-glass rounded-2xl p-5 md:col-span-2 lg:col-span-1"><h3 class="font-heading italic text-2xl">M. MySQL Layer</h3><p class="text-white/60 text-sm mt-2">Users, freelancers, jobs, messages, reports, notifications.</p></article>
            </div>
        </div>
    </section>

    <section id="architecture" class="py-24 px-6 md:px-16 lg:px-24">
        <div class="max-w-7xl mx-auto">
            <span class="section-badge liquid-glass">Data + Tech Flow</span>
            <h2 class="text-4xl md:text-5xl lg:text-6xl font-heading italic text-white tracking-tight leading-[0.9]">Aggregation architecture that actually works.</h2>
            <div class="mt-12 grid grid-cols-1 lg:grid-cols-2 gap-6">
                <article class="liquid-glass rounded-3xl p-8">
                    <h3 class="text-3xl font-heading italic">Input Sources</h3>
                    <ul class="mt-4 space-y-2 text-white/70 text-sm">
                        <li>Public Job APIs: Remotive, RapidAPI options</li>
                        <li>RSS Feeds: remote job feed ingestion</li>
                        <li>JSON Simulations: LinkedIn, Naukri, Fiverr</li>
                        <li>MySQL: internal platform users and jobs</li>
                    </ul>
                </article>
                <article class="liquid-glass rounded-3xl p-8">
                    <h3 class="text-3xl font-heading italic">Processing Pipeline</h3>
                    <p class="mt-4 text-white/70 text-sm">Real APIs + RSS + JSON + MySQL</p>
                    <p class="text-white/60 text-sm mt-2">→ api/aggregate.php</p>
                    <p class="text-white/60 text-sm mt-2">→ Unified JSON response</p>
                    <p class="text-white/60 text-sm mt-2">→ jQuery AJAX rendering and filtering</p>
                    <div class="mt-6 flex flex-wrap gap-2 text-xs text-white/70">
                        <span class="liquid-glass rounded-full px-3 py-1">PHP</span>
                        <span class="liquid-glass rounded-full px-3 py-1">jQuery</span>
                        <span class="liquid-glass rounded-full px-3 py-1">MySQL</span>
                        <span class="liquid-glass rounded-full px-3 py-1">JSON</span>
                        <span class="liquid-glass rounded-full px-3 py-1">AJAX</span>
                        <span class="liquid-glass rounded-full px-3 py-1">RSS</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="syllabus" class="relative py-24 px-6 md:px-16 lg:px-24">
        <video class="absolute inset-0 w-full h-full object-cover z-0" style="filter: saturate(0);" autoplay loop muted playsinline>
            <source src="https://videos.pexels.com/video-files/6963744/6963744-hd_1920_1080_25fps.mp4" type="video/mp4">
        </video>
        <div class="absolute top-0 left-0 right-0 h-[200px] z-[1] fade-top"></div>
        <div class="absolute bottom-0 left-0 right-0 h-[200px] z-[1] fade-bottom"></div>
        <div class="relative z-10 max-w-6xl mx-auto liquid-glass rounded-3xl p-8 md:p-12">
            <span class="section-badge liquid-glass">Academic Coverage</span>
            <h2 class="text-4xl md:text-5xl lg:text-6xl font-heading italic text-white tracking-tight leading-[0.9]">Module 3 to 6 fully mapped.</h2>
            <div class="mt-10 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
                <article class="liquid-glass rounded-2xl p-5"><h3 class="font-heading italic text-2xl">Module 3</h3><p class="text-white/60 mt-2">jQuery selectors, effects, events, DOM, traversal, JSON, AJAX.</p></article>
                <article class="liquid-glass rounded-2xl p-5"><h3 class="font-heading italic text-2xl">Module 4</h3><p class="text-white/60 mt-2">PHP structure, control flow, functions, arrays, forms, regex, validation, errors.</p></article>
                <article class="liquid-glass rounded-2xl p-5"><h3 class="font-heading italic text-2xl">Module 5</h3><p class="text-white/60 mt-2">File upload, sessions, cookies, graphics, image processing, mail function.</p></article>
                <article class="liquid-glass rounded-2xl p-5"><h3 class="font-heading italic text-2xl">Module 6</h3><p class="text-white/60 mt-2">MySQL CRUD, indexes, functions, PHP integration, form data persistence.</p></article>
            </div>
        </div>
    </section>

    <section class="py-24 px-6 md:px-16 lg:px-24">
        <div class="max-w-7xl mx-auto">
            <span class="section-badge liquid-glass">Extra Power Features</span>
            <h2 class="text-4xl md:text-5xl lg:text-6xl font-heading italic text-white tracking-tight leading-[0.9]">Advanced features ready for next phase.</h2>

            <div class="mt-12 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <article class="liquid-glass rounded-2xl p-6"><div class="liquid-glass-strong rounded-full w-10 h-10 flex items-center justify-center">🧠</div><h3 class="mt-4 text-lg font-heading italic text-white">AI Skill Matching</h3><p class="mt-2 text-white/60 text-sm">Model-assisted candidate ranking by skill relevance.</p></article>
                <article class="liquid-glass rounded-2xl p-6"><div class="liquid-glass-strong rounded-full w-10 h-10 flex items-center justify-center">⭐</div><h3 class="mt-4 text-lg font-heading italic text-white">Rating System</h3><p class="mt-2 text-white/60 text-sm">Quality and trust indicators for hiring decisions.</p></article>
                <article class="liquid-glass rounded-2xl p-6"><div class="liquid-glass-strong rounded-full w-10 h-10 flex items-center justify-center">🚩</div><h3 class="mt-4 text-lg font-heading italic text-white">Report Fake Users</h3><p class="mt-2 text-white/60 text-sm">Community moderation and admin review workflows.</p></article>
                <article class="liquid-glass rounded-2xl p-6"><div class="liquid-glass-strong rounded-full w-10 h-10 flex items-center justify-center">🔔</div><h3 class="mt-4 text-lg font-heading italic text-white">Notifications</h3><p class="mt-2 text-white/60 text-sm">Important events surfaced in one timeline.</p></article>
                <article class="liquid-glass rounded-2xl p-6"><div class="liquid-glass-strong rounded-full w-10 h-10 flex items-center justify-center">📌</div><h3 class="mt-4 text-lg font-heading italic text-white">Bookmarks</h3><p class="mt-2 text-white/60 text-sm">Save valuable freelancer profiles instantly.</p></article>
                <article class="liquid-glass rounded-2xl p-6"><div class="liquid-glass-strong rounded-full w-10 h-10 flex items-center justify-center">📄</div><h3 class="mt-4 text-lg font-heading italic text-white">Resume Parser</h3><p class="mt-2 text-white/60 text-sm">Extract structured data from uploaded CVs.</p></article>
                <article class="liquid-glass rounded-2xl p-6"><div class="liquid-glass-strong rounded-full w-10 h-10 flex items-center justify-center">🛡️</div><h3 class="mt-4 text-lg font-heading italic text-white">Admin Console</h3><p class="mt-2 text-white/60 text-sm">Live oversight on reports, jobs, and user health.</p></article>
                <article class="liquid-glass rounded-2xl p-6"><div class="liquid-glass-strong rounded-full w-10 h-10 flex items-center justify-center">🌙</div><h3 class="mt-4 text-lg font-heading italic text-white">Dark Premium UI</h3><p class="mt-2 text-white/60 text-sm">Consistent liquid-glass experience across modules.</p></article>
            </div>
        </div>
    </section>

    <section class="relative py-28 px-6 md:px-16 lg:px-24">
        <video class="absolute inset-0 w-full h-full object-cover z-0" autoplay loop muted playsinline>
            <source src="https://videos.pexels.com/video-files/854122/854122-hd_1920_1080_25fps.mp4" type="video/mp4">
        </video>
        <div class="absolute top-0 left-0 right-0 h-[200px] z-[1] fade-top"></div>
        <div class="absolute bottom-0 left-0 right-0 h-[200px] z-[1] fade-bottom"></div>

        <div class="relative z-10 max-w-5xl mx-auto text-center">
            <h2 class="text-5xl md:text-6xl lg:text-7xl font-heading italic">Build, search, and hire from one place.</h2>
            <p class="mt-5 text-white/60 font-body font-light text-sm">Start with role-based onboarding and unlock the full TalentSync PRO workflow.</p>
            <div class="mt-8 flex items-center justify-center gap-4 flex-wrap">
                <a href="register.php" class="liquid-glass-strong rounded-full px-6 py-3">Create Free Account</a>
                <a href="login.php" class="bg-white text-black rounded-full px-6 py-3">Login Now</a>
                <a href="dashboard.php" class="liquid-glass rounded-full px-6 py-3">Open Dashboard</a>
            </div>

            <footer class="mt-24 pt-8 border-t border-white/10 flex flex-col md:flex-row items-center justify-between gap-3 text-xs text-white/40">
                <span>© 2026 TalentSync PRO</span>
                <div class="flex items-center gap-4">
                    <a href="README.md" target="_blank">Docs</a><a href="aggregator.php">Aggregator</a><a href="chat.php">Chat</a>
                </div>
            </div>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>
