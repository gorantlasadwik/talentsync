<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$error = '';
$notice = '';

if (isset($_GET['reset']) && $_GET['reset'] === '1') {
    $notice = 'Password updated successfully. Please login with your new password.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $captcha = trim($_POST['captcha'] ?? '');

    if ($captcha !== ($_SESSION['captcha'] ?? '')) {
        $error = 'Invalid CAPTCHA.';
    } else {
        $stmt = $pdo->prepare('SELECT id, name, email, password, role FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            $error = 'Invalid credentials.';
        } else {
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'];

            setcookie('user', $user['name'], time() + 3600, '/');

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
    <title>Login | TalentSync PRO</title>
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
            <a href="register.php" class="top-pill liquid-glass text-sm px-4 py-2 hidden md:inline-flex">Create account</a>
        </div>
    </div>
    <div class="min-h-[75vh] flex items-center justify-center">
    <form method="post" class="liquid-glass rounded-3xl p-8 w-full max-w-md space-y-4 shadow-[0_20px_80px_rgba(0,0,0,0.45)]">
        <h1 class="text-4xl font-heading italic">Welcome Back</h1>
        <?php if ($notice): ?>
            <p class="text-emerald-300 text-sm"><?php echo e($notice); ?></p>
        <?php endif; ?>
        <?php if ($error): ?>
            <p class="text-red-300 text-sm"><?php echo e($error); ?></p>
        <?php endif; ?>
        <input class="ui-input" type="email" name="email" placeholder="Email" required>
        <input class="ui-input" type="password" name="password" placeholder="Password" required>
        <div class="flex items-center gap-3">
            <img src="captcha.php" alt="captcha" class="rounded-lg h-[40px] w-[100px] object-cover">
            <input class="ui-input flex-1" type="text" name="captcha" placeholder="Enter CAPTCHA" required>
        </div>
        <div class="text-right -mt-1">
            <a href="forgot_password.php" class="text-xs text-white/75 hover:text-white">Forgot password?</a>
        </div>
        <button class="w-full liquid-glass-strong rounded-full px-4 py-3" type="submit">Login</button>
        <p class="text-white/60 text-sm">No account? <a href="register.php" class="text-white">Create one</a></p>
    </form>
    </div>
</body>
</html>
