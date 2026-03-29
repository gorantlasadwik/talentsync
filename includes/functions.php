<?php
declare(strict_types=1);

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function isValidName(string $name): bool
{
    return (bool) preg_match('/^[a-zA-Z ]+$/', $name);
}

function csrfToken(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verifyCsrf(?string $token): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    return !empty($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function avatarUrl(string $name, ?string $avatarPath, ?string $gender = null, ?int $age = null): string
{
    $img = trim((string) ($avatarPath ?? ''));
    if ($img !== '') {
        if (preg_match('~^https?://~i', $img) || str_starts_with($img, 'data:image/')) {
            return $img;
        }

        if (file_exists(__DIR__ . '/../' . ltrim($img, '/'))) {
            return $img;
        }
    }

    $g = strtolower(trim((string) $gender));
    $style = $g === 'female' ? 'adventurer-neutral' : 'adventurer';
    $seed = rawurlencode(strtolower(trim($name)) . '_' . (string) ($age ?? 0) . '_' . $g);
    return 'https://api.dicebear.com/9.x/' . $style . '/svg?seed=' . $seed . '&radius=20&backgroundType=gradientLinear';
}

function appBaseUrl(): string
{
    $configured = trim((string) getenv('APP_BASE_URL'));
    if ($configured !== '') {
        return rtrim($configured, '/');
    }

    $https = (!empty($_SERVER['HTTPS']) && (string) $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
    $scheme = $https ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $scriptDir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '')));
    if ($scriptDir === '/' || $scriptDir === '.') {
        $scriptDir = '';
    }

    return $scheme . '://' . $host . rtrim($scriptDir, '/');
}

function appUrl(string $path = ''): string
{
    $base = appBaseUrl();
    $cleanPath = ltrim($path, '/');
    if ($cleanPath === '') {
        return $base;
    }

    return $base . '/' . $cleanPath;
}

function buildBrandedMailHtml(
    string $headline,
    string $intro,
    string $bodyHtml,
    ?string $ctaLabel = null,
    ?string $ctaUrl = null,
    ?string $metaLine = null
): string {
    $safeHeadline = htmlspecialchars($headline, ENT_QUOTES, 'UTF-8');
    $safeIntro = htmlspecialchars($intro, ENT_QUOTES, 'UTF-8');
    $safeMetaLine = $metaLine !== null ? htmlspecialchars($metaLine, ENT_QUOTES, 'UTF-8') : '';

    $ctaBlock = '';
    if ($ctaLabel !== null && $ctaUrl !== null && trim($ctaLabel) !== '' && trim($ctaUrl) !== '') {
        $safeCtaLabel = htmlspecialchars($ctaLabel, ENT_QUOTES, 'UTF-8');
        $safeCtaUrl = htmlspecialchars($ctaUrl, ENT_QUOTES, 'UTF-8');
        $ctaBlock =
            '<tr>'
            . '<td style="padding: 8px 0 24px 0;">'
            . '<a href="' . $safeCtaUrl . '" target="_blank" rel="noopener" style="display:inline-block;padding:12px 20px;border-radius:9999px;border:1px solid rgba(255,255,255,0.45);color:#ffffff;text-decoration:none;font-family:Barlow,Segoe UI,Arial,sans-serif;font-size:14px;font-weight:600;background:linear-gradient(180deg,rgba(255,255,255,0.23),rgba(255,255,255,0.09));">' . $safeCtaLabel . '</a>'
            . '</td>'
            . '</tr>';
    }

    $metaBlock = '';
    if ($safeMetaLine !== '') {
        $metaBlock = '<tr><td style="padding-top:4px;color:rgba(255,255,255,0.62);font-size:12px;font-family:Barlow,Segoe UI,Arial,sans-serif;">' . $safeMetaLine . '</td></tr>';
    }

    return '<!DOCTYPE html>'
        . '<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">'
        . '<link rel="preconnect" href="https://fonts.googleapis.com">'
        . '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
        . '<link href="https://fonts.googleapis.com/css2?family=Barlow:wght@300;400;500;600&family=Instrument+Serif:ital@1&display=swap" rel="stylesheet">'
        . '<title>TalentSync PRO</title></head>'
        . '<body style="margin:0;padding:0;background:#020202;color:#ffffff;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:radial-gradient(700px 350px at 5% -10%, rgba(255,255,255,0.08), transparent 55%),radial-gradient(600px 350px at 95% 0%, rgba(110,160,255,0.12), transparent 50%),linear-gradient(180deg,#020202 0%, #000 45%, #020202 100%);padding:34px 12px;">'
        . '<tr><td align="center">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:620px;border-radius:26px;overflow:hidden;border:1px solid rgba(255,255,255,0.16);background:rgba(255,255,255,0.04);">'
        . '<tr><td style="padding:28px 28px 14px 28px;">'
        . '<span style="display:inline-block;padding:7px 14px;border-radius:9999px;border:1px solid rgba(255,255,255,0.3);font-size:11px;font-weight:600;letter-spacing:0.06em;color:#ffffff;font-family:Barlow,Segoe UI,Arial,sans-serif;">TALENTSYNC PRO</span>'
        . '</td></tr>'
        . '<tr><td style="padding:2px 28px 8px 28px;font-family:Instrument Serif, Georgia, Times New Roman, serif;font-style:italic;font-size:42px;line-height:1.02;color:#ffffff;">' . $safeHeadline . '</td></tr>'
        . '<tr><td style="padding:0 28px 16px 28px;color:rgba(255,255,255,0.78);font-size:15px;line-height:1.65;font-family:Barlow,Segoe UI,Arial,sans-serif;">' . $safeIntro . '</td></tr>'
        . '<tr><td style="padding:0 28px;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-radius:18px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.12);">'
        . '<tr><td style="padding:18px 18px 16px 18px;color:#f5f7ff;font-size:14px;line-height:1.7;font-family:Barlow,Segoe UI,Arial,sans-serif;">' . $bodyHtml . '</td></tr>'
        . '</table>'
        . '</td></tr>'
        . '<tr><td style="padding:0 28px;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">'
        . $ctaBlock
        . '</table>'
        . '</td></tr>'
        . '<tr><td style="padding:0 28px 24px 28px;color:rgba(255,255,255,0.62);font-size:12px;line-height:1.5;font-family:Barlow,Segoe UI,Arial,sans-serif;">Sent by TalentSync PRO</td></tr>'
        . '</table>'
        . '</td></tr>'
        . '<tr><td align="center">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:620px;">'
        . $metaBlock
        . '</table>'
        . '</td></tr>'
        . '</table>'
        . '</body></html>';
}

function sendAppMail(string $to, string $subject, string $message, ?string $htmlMessage = null): bool
{
    $to = trim($to);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $fromAddress = trim((string) getenv('MAIL_FROM_ADDRESS'));
    if (!filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
        $iniFrom = trim((string) ini_get('sendmail_from'));
        if (filter_var($iniFrom, FILTER_VALIDATE_EMAIL)) {
            $fromAddress = $iniFrom;
        }
    }
    if (!filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
        $fromAddress = 'no-reply@talentsync.local';
    }

    $fromName = trim((string) getenv('MAIL_FROM_NAME'));
    if ($fromName === '') {
        $fromName = 'TalentSync PRO';
    }

    $replyTo = trim((string) getenv('MAIL_REPLY_TO'));
    if (!filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $replyTo = $fromAddress;
    }

    $safeFromName = str_replace(["\r", "\n"], '', $fromName);
    $safeSubject = str_replace(["\r", "\n"], '', $subject);

    $normalizedMessage = str_replace(["\r\n", "\r"], "\n", $message);

    $headers = [
        'MIME-Version: 1.0',
        'From: ' . $safeFromName . ' <' . $fromAddress . '>',
        'Reply-To: ' . $replyTo,
        'X-Mailer: PHP/' . phpversion(),
    ];

    if ($htmlMessage !== null && trim($htmlMessage) !== '') {
        $boundary = 'tspro_' . bin2hex(random_bytes(12));
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

        $htmlNormalized = str_replace(["\r\n", "\r"], "\n", $htmlMessage);
        $body = '';
        $body .= '--' . $boundary . "\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $normalizedMessage . "\r\n\r\n";
        $body .= '--' . $boundary . "\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $htmlNormalized . "\r\n\r\n";
        $body .= '--' . $boundary . "--\r\n";

        return @mail($to, $safeSubject, $body, implode("\r\n", $headers));
    }

    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    return @mail($to, $safeSubject, $normalizedMessage, implode("\r\n", $headers));
}

function ensurePasswordResetTable(PDO $pdo): void
{
    static $isReady = false;
    if ($isReady) {
        return;
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token_hash CHAR(64) NOT NULL UNIQUE,
            expires_at DATETIME NOT NULL,
            used_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_password_resets_user (user_id),
            INDEX idx_password_resets_expiry (expires_at)
        )'
    );

    $isReady = true;
}
