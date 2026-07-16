<?php
declare(strict_types=1);

function auth_start(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function current_user(): ?array
{
    auth_start();
    return $_SESSION['varco_user'] ?? null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function is_admin(): bool
{
    $user = current_user();
    if (!$user) return false;
    $admins = array_map('trim', explode(',', env('ADMIN_EMAILS', '')));
    return in_array($user['email'], $admins, true);
}

function auth_login(string $email, string $name, string $picture): void
{
    auth_start();
    $_SESSION['varco_user'] = [
        'email'   => $email,
        'name'    => $name,
        'picture' => $picture,
    ];
}

function auth_logout(): void
{
    auth_start();
    unset($_SESSION['varco_user']);
}

function auth_require_admin(): void
{
    if (!is_admin()) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Accesso riservato agli admin']);
        exit;
    }
}

function google_auth_url(string $state = ''): string
{
    $params = [
        'client_id'     => env('GOOGLE_CLIENT_ID', ''),
        'redirect_uri'  => env('GOOGLE_REDIRECT_URI_VARCO', env('GOOGLE_REDIRECT_URI', '')),
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'access_type'   => 'online',
        'prompt'        => 'select_account',
    ];
    if ($state !== '') $params['state'] = $state;
    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
}

function google_exchange_code(string $code): ?array
{
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'code'          => $code,
            'client_id'     => env('GOOGLE_CLIENT_ID', ''),
            'client_secret' => env('GOOGLE_CLIENT_SECRET', ''),
            'redirect_uri'  => env('GOOGLE_REDIRECT_URI_VARCO', env('GOOGLE_REDIRECT_URI', '')),
            'grant_type'    => 'authorization_code',
        ]),
    ]);
    $body = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($http !== 200 || !$body) return null;
    return json_decode($body, true) ?: null;
}

function google_fetch_userinfo(string $accessToken): ?array
{
    $ch = curl_init('https://www.googleapis.com/oauth2/v2/userinfo');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ["Authorization: Bearer $accessToken"],
    ]);
    $body = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($http !== 200 || !$body) return null;
    return json_decode($body, true) ?: null;
}
