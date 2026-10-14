<?php

require_once __DIR__ . '/../config/config.php';

session_name('boomplus');
session_start();

const SPORT_LIST   = ['Pickleball', 'Futsal', 'Badminton'];
const PAYMENT_LIST = ['card' => 'Credit / Debit Card', 'fpx' => 'FPX Online Banking', 'ewallet' => 'E-Wallet', 'cash' => 'Pay at Venue (Cash)'];

function api(string $method, string $path, ?array $data = null, bool $authenticated = true): array
{
    $headers = "Content-Type: application/json\r\nAccept: application/json\r\n"
        . 'X-Forwarded-For: ' . ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1') . "\r\n";

    if ($authenticated && !empty($_SESSION['token'])) {
        $headers .= "Authorization: Bearer {$_SESSION['token']}\r\n";
    }

    $context = stream_context_create([
        'http' => [
            'method'        => $method,
            'header'        => $headers,
            'content'       => $data !== null ? json_encode($data) : '',
            'ignore_errors' => true,
            'timeout'       => 20,
        ],
    ]);

    $raw      = @file_get_contents(API_BASE . $path, false, $context);
    $response = json_decode($raw ?: '', true) ?: ['success' => false, 'message' => 'API unreachable. Start Apache and MySQL in XAMPP.'];

    preg_match('/\s(\d{3})\s/', $http_response_header[0] ?? '', $matches);
    $response['_status'] = (int) ($matches[1] ?? 0);

    if ($response['_status'] === 401 && $authenticated && !empty($_SESSION['token'])) {
        session_destroy();
        redirect('login.php?expired=1');
    }

    return $response;
}

function api_get(string $path, array $query = [], bool $authenticated = true): array
{
    $query = array_filter($query, fn ($value) => $value !== '' && $value !== null);

    return api('GET', $path . ($query ? '?' . http_build_query($query) : ''), null, $authenticated);
}

function api_ok(array $response): bool
{
    return !empty($response['success']);
}

function api_rows(array $response): array
{
    return api_ok($response) ? $response['data'] : [];
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function is_post(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function input(string $key, $default = '')
{
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}

function csrf_token(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(16));
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function csrf_check(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400);
        exit('Bad CSRF token');
    }
}

function user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function login_user(array $data): void
{
    session_regenerate_id(true);

    $_SESSION['token'] = $data['token'];
    $_SESSION['user']  = $data['user'];
}

function flash(?string $message = null, string $type = 'ok'): ?array
{
    if ($message !== null) {
        $_SESSION['flash'] = [$message, $type];
        return null;
    }

    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    return $flash;
}

function money($amount): string
{
    return 'RM ' . number_format((float) $amount, 2);
}

function format_datetime(?string $value): string
{
    return $value ? date('D, j M Y · g:i A', strtotime($value)) : '-';
}

function format_date(?string $value): string
{
    return $value ? date('j M Y', strtotime($value)) : '-';
}

function format_time(?string $value): string
{
    return $value ? date('g:i A', strtotime($value)) : '-';
}

function query_with(array $changes = []): string
{
    $query = array_filter(array_merge($_GET, $changes), fn ($value) => $value !== '' && $value !== null);

    return '?' . http_build_query($query);
}

function home_for(string $role): string
{
    return 'dashboard.php';
}

function require_login(string ...$roles): array
{
    $user = user();

    if (!$user) {
        redirect('login.php');
    }

    if ($roles && !in_array($user['role'], $roles, true)) {
        http_response_code(403);
        render_head('Access denied');
        echo '<div class="card empty"><h2>403 · Access denied</h2><p>Your role (' . e($user['role']) . ') cannot open this page.</p>'
            . '<a class="btn" href="dashboard.php">Back to dashboard</a></div>';
        render_foot();
        exit;
    }

    return $user;
}

function page_exists(string $file): bool
{
    return is_file(dirname($_SERVER['SCRIPT_FILENAME']) . '/' . $file);
}

function require_guest(): void
{
    if (user()) {
        redirect('dashboard.php');
    }
}

function require_page(string $name): void
{
    require __DIR__ . '/pages/' . $name . '.php';
}

require_once __DIR__ . '/components.php';
require_once __DIR__ . '/layout.php';
