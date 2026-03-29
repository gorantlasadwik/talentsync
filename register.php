<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'seeker';
    $captcha = trim($_POST['captcha'] ?? '');

    if (!isValidName($name)) {
        $error = 'Invalid name format.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } elseif (!in_array($role, ['seeker', 'provider'], true)) {
        $error = 'Invalid role selected.';
    } elseif ($captcha !== ($_SESSION['captcha'] ?? '')) {
        $error = 'Invalid CAPTCHA.';
    } else {
        $check = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $check->execute([$email]);
        if ($check->fetch()) {
            $error = 'Email already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)');
            $stmt->execute([$name, $email, $hash, $role]);

            $userId = (int) $pdo->lastInsertId();
            if ($role === 'seeker') {
                $createFreelancer = $pdo->prepare(
                    'INSERT INTO freelancers (user_id)
                     SELECT ?
                     WHERE NOT EXISTS (
                         SELECT 1 FROM freelancers WHERE user_id = ?
                     )'
                );
                $createFreelancer->execute([$userId, $userId]);
            }

            $welcomeBody = "Hi {$name},\n\n"
                . "Welcome to TalentSync PRO. Your account has been created successfully.\n\n"
                . "You can now explore jobs, chat with professionals, and manage your profile.\n\n"
                . "Open dashboard: " . appUrl('dashboard.php') . "\n\n"
                . "Thanks,\nTalentSync PRO Team";

            $dashboardUrl = appUrl('dashboard.php');
            $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
            $welcomeHtml = buildBrandedMailHtml(
                'Welcome',
                'Your TalentSync PRO account is ready.',
                '<p style="margin:0 0 12px 0;">Hi ' . $safeName . ',</p>'
                . '<p style="margin:0 0 12px 0;">Your account has been created successfully. Start exploring jobs, connecting in chat, and building your profile.</p>'
                . '<p style="margin:0;">Let\'s build something great together.</p>',
                'Open Dashboard',
                $dashboardUrl,
                'If this was not you, contact support immediately.'
            );

            sendAppMail($email, 'Welcome to TalentSync PRO', $welcomeBody, $welcomeHtml);

            $_SESSION['user_id'] = $userId;
            $_SESSION['name'] = $name;
            $_SESSION['role'] = $role;

            setcookie('user', $name, time() + 3600, '/');

            header('Location: dashboard.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | TalentSync PRO</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@300;400;500;600&family=Instrument+Serif:ital@1&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body class="bg-black min-h-screen px-4 py-10">
    <div class="page-bg"></div>
    <div class="page-container">
        <div class="mb-8 flex items-center justify-center md:justify-between gap-3">
            <a href="index.php" class="top-pill liquid-glass text-sm px-4 py-2">TalentSync PRO</a>
            <a href="login.php" class="top-pill liquid-glass text-sm px-4 py-2 hidden md:inline-flex">Sign in</a>
        </div>
    </div>
    <div class="min-h-[75vh] flex items-center justify-center">
    <form method="post" class="liquid-glass rounded-3xl p-8 w-full max-w-md space-y-4 shadow-[0_20px_80px_rgba(0,0,0,0.45)]">
        <h1 class="text-4xl font-heading italic">Create Account</h1>
        <?php if ($error): ?>
            <p class="text-red-300 text-sm"><?php echo e($error); ?></p>
        <?php endif; ?>
        <input class="ui-input" type="text" name="name" placeholder="Full name" required>
        <input class="ui-input" type="email" name="email" placeholder="Email" required>
        <input class="ui-input" type="password" name="password" placeholder="Password" required>
        <select class="ui-select" name="role" required>
            <option class="bg-black text-white" value="seeker">Job Seeker</option>
            <option class="bg-black text-white" value="provider">Job Provider</option>
        </select>
        <div class="flex items-center gap-3">
            <img src="captcha.php" alt="captcha" class="rounded-lg h-[40px] w-[100px] object-cover">
            <input class="ui-input flex-1" type="text" name="captcha" placeholder="Enter CAPTCHA" required>
        </div>
        <button class="w-full liquid-glass-strong rounded-full px-4 py-3" type="submit">Register</button>
        <p class="text-white/60 text-sm">Already have an account? <a href="login.php" class="text-white">Login</a></p>
    </form>
    </div>
</body>
</html>
