<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/seeker_topbar.php';
require_once __DIR__ . '/includes/provider_topbar.php';

requireLogin();

$userId = currentUserId();

function chatCharacterAvatarUrl(string $name, ?string $imagePath, ?string $gender = null, ?int $age = null): string
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

$usersStmt = $pdo->prepare(
    'SELECT u.id, u.name, u.role,
            j.title AS position_title,
            j.location AS position_location,
            j.budget AS position_budget,
            f.image_path AS avatar_path,
            f.gender,
            f.age
     FROM users u
     LEFT JOIN freelancers f ON f.user_id = u.id
     LEFT JOIN jobs j ON j.id = (
         SELECT j2.id
         FROM jobs j2
         WHERE j2.provider_id = u.id
         ORDER BY j2.created_at DESC
         LIMIT 1
     )
     WHERE u.id != ?
     ORDER BY u.name ASC'
);
$usersStmt->execute([$userId]);
$users = $usersStmt->fetchAll();

foreach ($users as &$u) {
    $name = (string) ($u['name'] ?? 'Freelancer');
    $u['avatar_url'] = chatCharacterAvatarUrl(
        $name,
        isset($u['avatar_path']) ? (string) $u['avatar_path'] : null,
        isset($u['gender']) ? (string) $u['gender'] : null,
        isset($u['age']) ? (int) $u['age'] : null
    );
    $u['portfolio_url'] = ((string) ($u['role'] ?? '') === 'seeker')
        ? 'freelancer_portfolio.php?user_id=' . (int) $u['id']
        : '';
}
unset($u);

$mockChatProfiles = [
    ['mock_key' => 'mock_1', 'id' => -1001, 'name' => 'Aarav Sharma (Mock)', 'skill' => 'Full Stack Developer', 'location' => 'Jaipur', 'budget' => 'Revenue-share / Negotiable', 'suggestions' => ['Hi', 'What is your tech stack?', 'What timeline can you commit?']],
    ['mock_key' => 'mock_2', 'id' => -1002, 'name' => 'Sofia Khan (Mock)', 'skill' => 'UI/UX Designer', 'location' => 'Bengaluru', 'budget' => 'Fixed per milestone', 'suggestions' => ['Show your portfolio highlights', 'Can you do Figma to code?', 'What is your hourly rate?']],
    ['mock_key' => 'mock_3', 'id' => -1003, 'name' => 'Noah Patel (Mock)', 'skill' => 'DevOps Engineer', 'location' => 'Pune', 'budget' => 'Monthly retainer', 'suggestions' => ['Can you set up CI/CD?', 'How quickly can you start?', 'What cloud platforms do you use?']],
    ['mock_key' => 'mock_4', 'id' => -1004, 'name' => 'Meera Iyer (Mock)', 'skill' => 'Content Strategist', 'location' => 'Kochi', 'budget' => 'Project based', 'suggestions' => ['Can you write landing page copy?', 'Do you handle SEO?', 'What is your process?']],
    ['mock_key' => 'mock_5', 'id' => -1005, 'name' => 'Ethan Roy (Mock)', 'skill' => 'Mobile App Developer', 'location' => 'Hyderabad', 'budget' => 'Fixed + support', 'suggestions' => ['Native or cross-platform?', 'Can you share estimated timeline?', 'Do you provide post-launch support?']],
];

foreach ($mockChatProfiles as $mock) {
    $mockKey = (string) $mock['mock_key'];
    $mockPortfolioUrl = 'freelancer_portfolio.php?' . http_build_query([
        'mock' => 1,
        'mock_id' => $mockKey,
        'name' => (string) $mock['name'],
        'skill' => (string) $mock['skill'],
        'city' => (string) $mock['location'],
    ]);
    $users[] = [
        'id' => (int) $mock['id'],
        'name' => (string) $mock['name'],
        'role' => 'mock',
        'position_title' => (string) $mock['skill'],
        'position_location' => (string) $mock['location'],
        'position_budget' => (string) $mock['budget'],
        'avatar_url' => chatCharacterAvatarUrl((string) $mock['name'], null, null, null),
        'portfolio_url' => $mockPortfolioUrl,
        'mock_key' => $mockKey,
    ];
}

$defaultUser = $users[0] ?? null;
$preselectedUserId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
$preselectedMockId = trim((string) ($_GET['mock_id'] ?? ''));

function roleLabel(string $role): string
{
    if ($role === 'provider') {
        return 'Hiring Company';
    }
    if ($role === 'admin') {
        return 'Platform Team';
    }
    if ($role === 'mock') {
        return 'Mock Freelancer';
    }
    return 'Talent Member';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        html,
        body {
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        html::-webkit-scrollbar,
        body::-webkit-scrollbar {
            width: 0;
            height: 0;
            background: transparent;
            display: none;
        }

        .chat-scroll,
        #chatBox {
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .chat-scroll::-webkit-scrollbar,
        #chatBox::-webkit-scrollbar {
            width: 0;
            height: 0;
            background: transparent;
            display: none;
        }

        .chat-shell {
            background: radial-gradient(500px 240px at 95% 0%, rgba(130, 178, 255, 0.13), transparent 55%), #05070b;
        }
        .chat-user-item.active {
            background: rgba(255, 255, 255, 0.16);
            border-color: rgba(255, 255, 255, 0.35);
        }
        #chatBox .text-right span {
            background: #f8fafc;
            color: #111827;
            border-radius: 16px 16px 4px 16px;
            max-width: 86%;
        }
        #chatBox .text-left span {
            background: rgba(255, 255, 255, 0.12);
            color: #fff;
            border-radius: 16px 16px 16px 4px;
            max-width: 86%;
        }
        #chatBox .msg-bubble {
            max-width: 86%;
        }
        #chatBox .text-right,
        #chatBox .text-left {
            margin-bottom: 10px;
        }
    </style>
</head>
<body class="chat-shell text-white min-h-screen">
    <?php if (($_SESSION['role'] ?? '') === 'seeker') { renderSeekerTopbar('messages'); } else { renderProviderTopbar('messages', true, 'Search freelancers, skills, city'); } ?>
    <main class="pt-24 px-4 md:px-6 lg:px-8 pb-8 xl:pb-4 xl:h-[calc(100vh-5rem)] xl:overflow-hidden">
        <div class="max-w-[1500px] mx-auto grid grid-cols-1 xl:grid-cols-12 gap-5 xl:h-full min-h-0">
            <aside class="xl:col-span-3 liquid-glass rounded-3xl p-5 md:p-6 flex flex-col xl:min-h-0 xl:overflow-y-auto">
                <h2 class="text-xl font-heading italic mb-4">Company Details</h2>
                <div class="liquid-glass rounded-2xl p-4">
                    <div class="w-16 h-16 rounded-xl bg-white/12 flex items-center justify-center mb-3">
                        <span class="material-symbols-outlined" style="font-size:34px;">apartment</span>
                    </div>
                    <p id="companyName" class="font-semibold text-lg">TalentSync Network</p>
                    <p id="companyType" class="text-xs text-white/60 mt-1">Hiring Company</p>
                    <a id="companyAction" href="hiring_board.php" class="mt-4 inline-block liquid-glass rounded-xl px-4 py-2 text-sm">Open Hiring Board</a>
                </div>

                <div class="mt-5 liquid-glass rounded-2xl p-4">
                    <h3 class="text-sm uppercase tracking-wide text-white/65 mb-3">Position Details</h3>
                    <div class="space-y-3 text-sm">
                        <div>
                            <p class="text-white/55 text-xs">Role</p>
                            <p id="positionTitle" class="font-medium"><?php echo e((string) (($defaultUser['position_title'] ?? '') ?: 'No role selected')); ?></p>
                        </div>
                        <div>
                            <p class="text-white/55 text-xs">Location</p>
                            <p id="positionLocation" class="font-medium"><?php echo e((string) (($defaultUser['position_location'] ?? '') ?: 'Not specified')); ?></p>
                        </div>
                        <div>
                            <p class="text-white/55 text-xs">Budget</p>
                            <p id="positionBudget" class="font-medium"><?php echo e((string) (($defaultUser['position_budget'] ?? '') ?: 'Negotiable')); ?></p>
                        </div>
                    </div>
                </div>

                <a href="notifications.php" class="mt-auto liquid-glass rounded-xl px-4 py-3 text-sm text-center">Notifications</a>
            </aside>

            <section class="xl:col-span-3 liquid-glass rounded-3xl p-4 md:p-5 flex flex-col xl:min-h-0">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-2xl font-heading italic">Messages</h2>
                </div>
                <input id="chatSearch" class="ui-input mb-3" placeholder="Search people">

                <div class="chat-scroll flex-1 overflow-y-auto space-y-2 pr-1">
                    <?php if (!$users): ?>
                        <p class="text-sm text-white/60">No users available.</p>
                    <?php endif; ?>
                    <?php foreach ($users as $u): ?>
                        <button
                            class="chat-user-item w-full text-left px-3 py-3 rounded-xl border border-white/10 hover:bg-white/10 transition-all"
                            data-id="<?php echo (int) $u['id']; ?>"
                            data-name="<?php echo e((string) $u['name']); ?>"
                            data-role="<?php echo e((string) $u['role']); ?>"
                            data-position="<?php echo e((string) (($u['position_title'] ?? '') ?: 'Not available')); ?>"
                            data-location="<?php echo e((string) (($u['position_location'] ?? '') ?: 'Not specified')); ?>"
                            data-budget="<?php echo e((string) (($u['position_budget'] ?? '') ?: 'Negotiable')); ?>"
                            data-avatar="<?php echo e((string) (($u['avatar_url'] ?? '') ?: '')); ?>"
                            data-portfolio="<?php echo e((string) (($u['portfolio_url'] ?? '') ?: '')); ?>"
                            data-is-mock="<?php echo e((string) (($u['role'] ?? '') === 'mock' ? '1' : '0')); ?>"
                            data-mock-key="<?php echo e((string) ($u['mock_key'] ?? '')); ?>"
                            type="button"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <p class="font-semibold truncate"><?php echo e((string) $u['name']); ?></p>
                                <span class="text-[11px] text-white/60"><?php echo e((string) $u['role']); ?></span>
                            </div>
                            <p class="text-xs text-white/55 truncate mt-1"><?php echo e((string) (($u['position_title'] ?? '') ?: roleLabel((string) $u['role']))); ?></p>
                        </button>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="xl:col-span-6 liquid-glass rounded-3xl p-0 flex flex-col overflow-hidden min-h-[620px] xl:min-h-0 xl:h-full">
                <div class="px-5 md:px-7 h-20 flex items-center justify-between bg-white/5 border-b border-white/10">
                    <div class="flex items-center gap-3 min-w-0">
                        <img
                            id="chatHeaderAvatar"
                            src="<?php echo e((string) (($defaultUser['avatar_url'] ?? '') ?: chatCharacterAvatarUrl('Freelancer', null, null, null))); ?>"
                            alt=""
                            class="w-11 h-11 rounded-full object-cover border border-white/20 bg-white/10"
                        >
                        <div class="min-w-0">
                            <a
                                id="chatHeaderProfileLink"
                                href="<?php echo e((string) (($defaultUser['portfolio_url'] ?? '') ?: '#')); ?>"
                                class="inline-block max-w-full <?php echo !empty($defaultUser['portfolio_url']) ? '' : 'opacity-60 pointer-events-none'; ?>"
                            >
                                <h2 id="chatHeaderName" class="text-lg font-semibold truncate hover:underline"><?php echo e((string) (($defaultUser['name'] ?? 'Select a user'))); ?></h2>
                            </a>
                            <p id="chatHeaderMeta" class="text-xs text-white/60 truncate"><?php echo e((string) (($defaultUser['position_title'] ?? '') ?: 'Start conversation')); ?></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <a id="chatHeaderMapBtn" href="map.php" class="p-2 rounded-full hover:bg-white/10" title="Open map">
                            <span class="material-symbols-outlined">map</span>
                        </a>
                        <button class="p-2 rounded-full hover:bg-white/10"><span class="material-symbols-outlined">call</span></button>
                        <button class="p-2 rounded-full hover:bg-white/10"><span class="material-symbols-outlined">videocam</span></button>
                    </div>
                </div>

                <div id="chatBox" class="flex-1 min-h-0 overflow-y-auto px-5 md:px-7 py-6 text-sm"></div>

                <div id="mockSuggestions" class="hidden px-5 md:px-7 pb-3 border-t border-white/10 bg-white/5">
                    <p class="text-[11px] text-white/60 pt-3">Quick suggestions for mock chat</p>
                    <div id="mockSuggestionList" class="mt-2 flex flex-wrap gap-2"></div>
                </div>

                <form id="chatForm" class="px-5 md:px-7 py-5 border-t border-white/10 bg-white/5 flex flex-col gap-2" enctype="multipart/form-data">
                    <div class="flex items-center gap-3 w-full">
                        <button id="attachBtn" type="button" class="material-symbols-outlined text-white/65 hover:text-white">add_circle</button>
                        <input id="chatFile" type="file" class="hidden" accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.zip,.rar,.7z,.csv,.json,.fig,.psd,.ai">
                        <input id="chatMessage" class="ui-input flex-1" placeholder="Type a message...">
                        <button class="w-11 h-11 rounded-full liquid-glass-strong flex items-center justify-center" type="submit">
                            <span class="material-symbols-outlined">send</span>
                        </button>
                    </div>
                    <p id="chatAttachmentMeta" class="text-xs text-white/65 hidden"></p>
                </form>
            </section>
        </div>
    </main>

    <script>
        var receiverId = 0;
        var selfId = <?php echo (int) $userId; ?>;
        var preselectedUserId = <?php echo (int) $preselectedUserId; ?>;
        var preselectedMockId = <?php echo json_encode($preselectedMockId, JSON_UNESCAPED_UNICODE); ?>;
        var isMockConversation = false;
        var activeMockKey = '';
        var mockProfiles = <?php echo json_encode($mockChatProfiles, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

        function getMockProfile(mockKey) {
            for (var i = 0; i < mockProfiles.length; i++) {
                if (String(mockProfiles[i].mock_key) === String(mockKey)) {
                    return mockProfiles[i];
                }
            }
            return null;
        }

        function mockStorageKey(mockKey) {
            return 'ts_mock_chat_' + String(selfId) + '_' + String(mockKey || 'default');
        }

        function readMockMessages(mockKey) {
            try {
                var raw = localStorage.getItem(mockStorageKey(mockKey));
                if (!raw) {
                    return [];
                }
                var parsed = JSON.parse(raw);
                return Array.isArray(parsed) ? parsed : [];
            } catch (e) {
                return [];
            }
        }

        function saveMockMessages(mockKey, list) {
            try {
                localStorage.setItem(mockStorageKey(mockKey), JSON.stringify(list || []));
            } catch (e) {
                // Ignore localStorage errors.
            }
        }

        function escapeHtml(value) {
            return String(value || '').replace(/[&<>"']/g, function (char) {
                return {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;'
                }[char];
            });
        }

        function renderMockMessages(messages) {
            var html = '';
            messages.forEach(function (m) {
                var mine = m.from === 'me';
                html += '<div class="' + (mine ? 'text-right' : 'text-left') + '">';
                html += '<div class="msg-bubble inline-block px-3 py-2 rounded-2xl ' + (mine ? 'bg-white text-black' : 'bg-white/10 text-white') + '">';
                html += '<p class="whitespace-pre-line break-words">' + escapeHtml(m.text) + '</p>';
                html += '</div>';
                html += '</div>';
            });
            $('#chatBox').html(html);
            var box = $('#chatBox')[0];
            box.scrollTop = box.scrollHeight;
        }

        function renderMockSuggestions(mockKey) {
            var profile = getMockProfile(mockKey);
            var list = $('#mockSuggestionList');
            list.empty();
            if (!profile || !Array.isArray(profile.suggestions)) {
                return;
            }
            profile.suggestions.forEach(function (s) {
                var btn = $('<button type="button" class="px-3 py-1.5 rounded-full border border-white/20 bg-white/10 text-xs hover:bg-white/15"></button>').text(s);
                btn.on('click', function () {
                    $('#chatMessage').val(String(s));
                    $('#chatMessage').trigger('focus');
                });
                list.append(btn);
            });
        }

        function generateMockReply(text, mockKey) {
            var t = String(text || '').toLowerCase().trim();
            var profile = getMockProfile(mockKey);
            if (!profile) {
                return 'Thanks for reaching out. Please share your project details.';
            }

            if (/^(hi|hello|hey)\b/.test(t)) {
                return 'Hi, I am ' + profile.name.replace(' (Mock)', '') + '. Happy to help. What would you like to build?';
            }
            if (t.indexOf('skill') > -1 || t.indexOf('stack') > -1 || t.indexOf('tech') > -1) {
                return 'My primary skill is ' + profile.skill + '. I can share a quick plan after hearing your requirements.';
            }
            if (t.indexOf('budget') > -1 || t.indexOf('cost') > -1 || t.indexOf('price') > -1) {
                return 'Budget wise, ' + profile.budget + '. If you share scope, I can suggest milestone-wise costing.';
            }
            if (t.indexOf('timeline') > -1 || t.indexOf('deadline') > -1 || t.indexOf('when') > -1) {
                return 'For most tasks, I can start immediately and deliver the first milestone in 2-4 days.';
            }
            if (t.indexOf('location') > -1 || t.indexOf('city') > -1 || t.indexOf('india') > -1) {
                return 'I am currently operating from ' + profile.location + ', India, and available remotely.';
            }

            return 'Got it. Please share goals, timeline, and expected deliverables. I will propose a simple execution plan.';
        }

        function showAttachmentMeta(file) {
            var meta = $('#chatAttachmentMeta');
            if (!file) {
                meta.addClass('hidden').text('');
                return;
            }

            var sizeText = file.size >= 1024 * 1024
                ? (file.size / (1024 * 1024)).toFixed(2) + ' MB'
                : (file.size / 1024).toFixed(1) + ' KB';
            meta.removeClass('hidden').text('Attached: ' + file.name + ' (' + sizeText + ')');
        }

        function setContextFromButton(button) {
            var selectedId = Number(button.data('id') || 0);
            var name = button.data('name') || 'Contact';
            var role = button.data('role') || 'seeker';
            var position = button.data('position') || 'Not available';
            var location = button.data('location') || 'Not specified';
            var budget = button.data('budget') || 'Negotiable';
            var avatar = String(button.data('avatar') || '');
            var portfolioUrl = String(button.data('portfolio') || '');
            isMockConversation = String(button.data('is-mock') || '') === '1';
            activeMockKey = String(button.data('mock-key') || '');
            var mapUrl = 'map.php';
            if (isMockConversation && activeMockKey) {
                mapUrl += '?focus_mock_id=' + encodeURIComponent(activeMockKey);
            } else if (selectedId > 0) {
                mapUrl += '?focus_user_id=' + encodeURIComponent(String(selectedId));
            }

            $('#chatHeaderName').text(name);
            $('#chatHeaderMeta').text(position + ' • ' + location);
            if (avatar) {
                $('#chatHeaderAvatar').attr('src', avatar);
            }
            $('#chatHeaderMapBtn').attr('href', mapUrl);

            var profileLink = $('#chatHeaderProfileLink');
            if (portfolioUrl && portfolioUrl !== '#') {
                profileLink.attr('href', portfolioUrl);
                profileLink.removeClass('opacity-60 pointer-events-none');
                $('#chatHeaderName').addClass('hover:underline cursor-pointer');
            } else {
                profileLink.attr('href', '#');
                profileLink.addClass('opacity-60 pointer-events-none');
                $('#chatHeaderName').removeClass('hover:underline cursor-pointer');
            }

            $('#companyName').text(role === 'provider' ? name : 'TalentSync Network');
            if (isMockConversation) {
                $('#companyName').text(name);
                $('#companyType').text('Mock Freelancer Bot');
            } else {
                $('#companyType').text(role === 'provider' ? 'Hiring Company' : (role === 'admin' ? 'Platform Team' : 'Talent Member'));
            }
            $('#positionTitle').text(position);
            $('#positionLocation').text(location);
            $('#positionBudget').text(budget);

            if (isMockConversation) {
                $('#mockSuggestions').removeClass('hidden');
                renderMockSuggestions(activeMockKey);
            } else {
                $('#mockSuggestions').addClass('hidden');
            }

            var action = $('#companyAction');
            if (role === 'provider' && selectedId > 0) {
                action.attr('href', 'hiring_board.php?provider_id=' + selectedId);
                action.removeClass('opacity-60 pointer-events-none');
                action.text('Open Hiring Board');
            } else if (isMockConversation) {
                action.attr('href', '#');
                action.addClass('opacity-60 pointer-events-none');
                action.text('Mock Bot Profile');
            } else {
                action.attr('href', '#');
                action.addClass('opacity-60 pointer-events-none');
                action.text('Hiring Board Unavailable');
            }
        }

        function loadMessages() {
            if (!receiverId) {
                return;
            }

            if (isMockConversation) {
                var history = readMockMessages(activeMockKey);
                if (!history.length) {
                    history = [{ from: 'bot', text: 'Hi! I am a mock freelancer bot. Ask about skills, budget, or timeline.' }];
                    saveMockMessages(activeMockKey, history);
                }
                renderMockMessages(history);
                return;
            }

            $.get('api/get_messages.php', { receiver_id: receiverId }, function (html) {
                $('#chatBox').html(html);
                var box = $('#chatBox')[0];
                box.scrollTop = box.scrollHeight;
            });
        }

        $('.chat-user-item').on('click', function () {
            receiverId = $(this).data('id');
            $('.chat-user-item').removeClass('active');
            $(this).addClass('active');
            setContextFromButton($(this));
            loadMessages();
        });

        $('#chatSearch').on('input', function () {
            var q = ($(this).val() || '').toLowerCase();
            $('.chat-user-item').each(function () {
                var text = $(this).text().toLowerCase();
                $(this).toggle(text.indexOf(q) > -1);
            });
        });

        $('#attachBtn').on('click', function () {
            $('#chatFile').trigger('click');
        });

        $('#chatFile').on('change', function () {
            showAttachmentMeta(this.files && this.files[0] ? this.files[0] : null);
        });

        $('#chatForm').on('submit', function (e) {
            e.preventDefault();
            if (!receiverId) {
                alert('Select a user first.');
                return;
            }
            var msg = $('#chatMessage').val();
            var file = $('#chatFile')[0].files[0];

            if (isMockConversation) {
                if (!msg || !String(msg).trim()) {
                    alert('Type a message for mock chat.');
                    return;
                }

                var history = readMockMessages(activeMockKey);
                history.push({ from: 'me', text: String(msg).trim() });
                saveMockMessages(activeMockKey, history);
                renderMockMessages(history);

                $('#chatMessage').val('');
                $('#chatFile').val('');
                showAttachmentMeta(null);

                setTimeout(function () {
                    var latestHistory = readMockMessages(activeMockKey);
                    latestHistory.push({ from: 'bot', text: generateMockReply(msg, activeMockKey) });
                    saveMockMessages(activeMockKey, latestHistory);
                    renderMockMessages(latestHistory);
                }, 450);
                return;
            }

            if ((!msg || !msg.trim()) && !file) {
                alert('Type a message or attach a file.');
                return;
            }

            var formData = new FormData();
            formData.append('receiver_id', receiverId);
            formData.append('message', msg || '');
            if (file) {
                formData.append('attachment', file);
            }

            $.ajax({
                url: 'api/send_message.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function () {
                    $('#chatMessage').val('');
                    $('#chatFile').val('');
                    showAttachmentMeta(null);
                    loadMessages();
                },
                error: function (xhr) {
                    var message = 'Could not send message.';
                    try {
                        var payload = JSON.parse(xhr.responseText || '{}');
                        if (payload.error) {
                            message = payload.error;
                        }
                    } catch (err) {
                        // Keep fallback message.
                    }
                    alert(message);
                }
            });
        });

        if ($('.chat-user-item').length) {
            var target = $();
            if (preselectedMockId) {
                target = $('.chat-user-item[data-mock-key="' + preselectedMockId + '"]');
            }
            if (!target.length && preselectedUserId) {
                target = $('.chat-user-item[data-id="' + preselectedUserId + '"]');
            }
            if (target.length) {
                target.first().trigger('click');
            } else {
                $('.chat-user-item').first().trigger('click');
            }
        }

        setInterval(loadMessages, 2000);
    </script>
</body>
</html>
