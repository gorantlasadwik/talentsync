<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$statusType = '';
$statusMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $statusType = 'error';
        $statusMessage = 'Enter a valid email address.';
    } else {
        try {
            $userStmt = $pdo->prepare('SELECT id, name, email FROM users WHERE email = ? LIMIT 1');
            $userStmt->execute([$email]);
            $user = $userStmt->fetch();

            if (!$user) {
                $statusType = 'error';
                $statusMessage = 'No account found with this email.';
            } else {
                ensurePasswordResetTable($pdo);

                $invalidateStmt = $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL');
                $invalidateStmt->execute([(int) $user['id']]);

                $token = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $token);
                $expiresAt = date('Y-m-d H:i:s', time() + 3600);

                $insertStmt = $pdo->prepare(
                    'INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)'
                );
                $insertStmt->execute([(int) $user['id'], $tokenHash, $expiresAt]);

                $resetUrl = appUrl('reset_password.php?token=' . urlencode($token));
                $mailBody = "Hi " . (string) $user['name'] . ",\n\n"
                    . "We received a request to reset your TalentSync PRO password.\n\n"
                    . "Reset password: {$resetUrl}\n\n"
                    . "This link expires in 60 minutes. If you did not request this, ignore this email.\n\n"
                    . "Thanks,\nTalentSync PRO Team";

                $safeName = htmlspecialchars((string) $user['name'], ENT_QUOTES, 'UTF-8');
                $resetHtml = buildBrandedMailHtml(
                    'Reset Password',
                    'A password reset request was received for your account.',
                    '<p style="margin:0 0 12px 0;">Hi ' . $safeName . ',</p>'
                    . '<p style="margin:0 0 12px 0;">Use the button below to securely reset your TalentSync PRO password.</p>'
                    . '<p style="margin:0;">This link expires in <strong>60 minutes</strong>. If this request was not made by you, you can safely ignore this email.</p>',
                    'Reset Password',
                    $resetUrl,
                    'For security reasons, this reset link works only once.'
                );

                $sent = sendAppMail((string) $user['email'], 'Reset your TalentSync PRO password', $mailBody, $resetHtml);
                if ($sent) {
                    $statusType = 'success';
                    $statusMessage = 'Password reset email sent. Check inbox/spam/promotions.';
                } else {
                    $statusType = 'error';
                    $statusMessage = 'Mail sending failed. Check XAMPP sendmail settings and restart Apache.';
                }
            }
        } catch (Throwable $e) {
            $statusType = 'error';
            $statusMessage = 'Forgot password failed. Please retry in a moment.';
            error_log('Forgot password error: ' . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | TalentSync PRO</title>
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
            <a href="login.php" class="top-pill liquid-glass text-sm px-4 py-2 hidden md:inline-flex">Back to login</a>
        </div>
    </div>
    <div class="min-h-[75vh] flex items-center justify-center">
        <form method="post" class="liquid-glass rounded-3xl p-8 w-full max-w-md space-y-4 shadow-[0_20px_80px_rgba(0,0,0,0.45)]">
            <h1 class="text-4xl font-heading italic">Forgot Password</h1>
            <p class="text-white/60 text-sm">Enter your account email and we will send a reset link.</p>

            <?php if ($statusMessage): ?>
                <p class="text-sm <?php echo $statusType === 'error' ? 'text-red-300' : 'text-emerald-300'; ?>"><?php echo e($statusMessage); ?></p>
            <?php endif; ?>

            <input class="ui-input" type="email" name="email" placeholder="Email" required>
            <button class="w-full liquid-glass-strong rounded-full px-4 py-3" type="submit">Send reset link</button>
            <p class="text-white/60 text-sm">Remembered your password? <a href="login.php" class="text-white">Login</a></p>
        </form>
    </div>
</body>
</html>
