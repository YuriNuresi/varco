<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';

if (VARCO_CORE_AUTH) {
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    $next = ltrim((string) ($_GET['next'] ?? 'index.php'), '/');
    header('Location: ' . p3d_login_url($base . '/' . $next));
    exit;
}

auth_start();

$next = $_GET['next'] ?? 'index.php';
$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;
$_SESSION['oauth_next']  = $next;

header('Location: ' . google_auth_url($state));
exit;
