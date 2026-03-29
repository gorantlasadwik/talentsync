<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

ensurePasswordResetTable($pdo);

function findValidReset(PDO $pdo, string $token): ?array
{
    if ($token === '') {
        return null;
    }

    $tokenHash = hash('sha256', $token);
    $stmt = $pdo->prepare(
        'SELECT pr.id, pr.user_id, pr.expires_at, pr.used_at, u.email, u.name
         FROM password_resets pr
         JOIN users u ON u.id = pr.user_id
         WHERE pr.token_hash = ?
         LIMIT 1'
    );
    $stmt->execute([$tokenHash]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    if (!empty($row['used_at'])) {
        return null;
    }

    $expiresAt = strtotime((string) $row['expires_at']);
    if ($expiresAt === false || $expiresAt < time()) {
        return null;
    }

    return $row;
}

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$statusType = '';
$statusMessage = '';
$resetComplete = false;
$validReset = findValidReset($pdo, $token);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if ($token === '' || !$validReset) {
        $statusType = 'error';
        $statusMessage = 'This reset link is invalid or expired.';
    } elseif (strlen($password) < 8) {
        $statusType = 'error';
        $statusMessage = 'Password must be at least 8 characters.';
    } elseif (!hash_equals($password, $confirmPassword)) {
        $statusType = 'error';
        $statusMessage = 'Password and confirm password do not match.';
    } else {
        try {
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);

            $updateUserStmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
            $updateUserStmt->execute([$passwordHash, (int) $validReset['user_id']]);

            $markUsedStmt = $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?');
            $markUsedStmt->execute([(int) $validReset['id']]);

            $closeOthersStmt = $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL');
            $closeOthersStmt->execute([(int) $validReset['user_id']]);

            $mailBody = "Hi " . (string) $validReset['name'] . ",\n\n"
                . "Your TalentSync PRO password has been changed successfully.\n\n"
                . "If you did not perform this action, please reset your password again immediately.\n\n"
                . "Login: " . appUrl('login.php') . "\n\n"
                . "Thanks,\nTalentSync PRO Team";

            $loginUrl = appUrl('login.php');
            $safeName = htmlspecialchars((string) $validReset['name'], ENT_QUOTES, 'UTF-8');
            $changedHtml = buildBrandedMailHtml(
                'Password Updated',
                'Your TalentSync PRO password was changed successfully.',
                '<p style="margin:0 0 12px 0;">Hi ' . $safeName . ',</p>'
                . '<p style="margin:0 0 12px 0;">Your password has been updated successfully.</p>'
                . '<p style="margin:0;">If this wasn\'t you, reset your password again immediately and review account activity.</p>',
                'Login Now',
                $loginUrl,
                'Security notice: this message confirms a credential change.'
            );

            sendAppMail((string) $validReset['email'], 'Password changed on TalentSync PRO', $mailBody, $changedHtml);

            $statusType = 'success';
            $statusMessage = 'Password updated successfully. You can now login.';
            $resetComplete = true;
            $validReset = null;
        } catch (Throwable $e) {
            $statusType = 'error';
            $statusMessage = 'Could not reset password right now. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | TalentSync PRO</title>
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
        <div class="liquid-glass rounded-3xl p-8 w-full max-w-md space-y-4 shadow-[0_20px_80px_rgba(0,0,0,0.45)]">
            <h1 class="text-4xl font-heading italic">Reset Password</h1>

            <?php if ($statusMessage): ?>
                <p class="text-sm <?php echo $statusType === 'error' ? 'text-red-300' : 'text-emerald-300'; ?>"><?php echo e($statusMessage); ?></p>
            <?php endif; ?>

            <?php if (!$resetComplete && $validReset): ?>
                <form method="post" class="space-y-4">
                    <input type="hidden" name="token" value="<?php echo e($token); ?>">
                    <input class="ui-input" type="password" name="password" placeholder="New password" required>
                    <input class="ui-input" type="password" name="confirm_password" placeholder="Confirm new password" required>
                    <button class="w-full liquid-glass-strong rounded-full px-4 py-3" type="submit">Update password</button>
                </form>
            <?php elseif (!$resetComplete): ?>
                <p class="text-white/70 text-sm">This reset link is invalid or expired. Request a new one.</p>
                <a href="forgot_password.php" class="inline-flex w-full justify-center liquid-glass-strong rounded-full px-4 py-3 text-sm">Request new reset link</a>
            <?php else: ?>
                <a href="login.php?reset=1" class="inline-flex w-full justify-center liquid-glass-strong rounded-full px-4 py-3 text-sm">Go to login</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
