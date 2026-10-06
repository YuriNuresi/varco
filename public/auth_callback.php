<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';

auth_start();

$error = $_GET['error'] ?? '';
if ($error !== '') {
    header('Location: index.php?auth_error=' . urlencode($error));
    exit;
}

$code  = $_GET['code']  ?? '';
$state = $_GET['state'] ?? '';
if ($code === '' || $state === '' || $state !== ($_SESSION['oauth_state'] ?? '')) {
    header('Location: index.php?auth_error=invalid_state');
    exit;
}
unset($_SESSION['oauth_state']);

$tokens = google_exchange_code($code);
if (!$tokens || empty($tokens['access_token'])) {
    header('Location: index.php?auth_error=token_exchange_failed');
    exit;
}

$profile = google_fetch_userinfo($tokens['access_token']);
if (!$profile || empty($profile['email'])) {
    header('Location: index.php?auth_error=userinfo_failed');
    exit;
}

auth_login($profile['email'], $profile['name'] ?? '', $profile['picture'] ?? '');

$next = $_SESSION['oauth_next'] ?? 'index.php';
unset($_SESSION['oauth_next']);
header('Location: ' . $next);
exit;
