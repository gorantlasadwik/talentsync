<?php
require_once __DIR__ . '/includes/auth.php';

requireLogin();

if (($_SESSION['role'] ?? '') === 'admin') {
    header('Location: admin_dashboard.php');
    exit;
}

if (($_SESSION['role'] ?? '') === 'provider') {
    header('Location: provider_dashboard.php');
    exit;
}

header('Location: seeker_dashboard.php');
exit;
