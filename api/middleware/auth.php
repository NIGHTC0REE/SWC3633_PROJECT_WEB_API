<?php

require_once __DIR__ . '/../lib/jwt.php';

function authorization_header(): string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';

    if ($header !== '' || !function_exists('apache_request_headers')) {
        return $header;
    }

    foreach (apache_request_headers() as $name => $value) {
        if (strcasecmp($name, 'Authorization') === 0) {
            return $value;
        }
    }

    return '';
}

function bearer_token(): ?string
{
    if (preg_match('/^Bearer\s+(\S+)$/i', authorization_header(), $matches)) {
        return $matches[1];
    }

    return null;
}

function optional_auth(): ?array
{
    $token = bearer_token();

    if (!$token) {
        return null;
    }

    $claims = jwt_decode($token);

    if (!$claims) {
        json_error(401, 'Invalid or expired token');
    }

    $statement = db()->prepare(
        'SELECT user_id, full_name, email, phone, is_active, role FROM users WHERE user_id = ?'
    );
    $statement->execute([$claims['sub']]);
    $user = $statement->fetch();

    if (!$user || !$user['is_active']) {
        json_error(401, 'Account not found or disabled');
    }

    current_user_id((int) $user['user_id']);

    return $user;
}

function require_auth(): array
{
    if (!bearer_token()) {
        json_error(401, 'Missing Authorization: Bearer token');
    }

    return optional_auth();
}

function require_role(string ...$roles): array
{
    $user = require_auth();

    if (!in_array($user['role'], $roles, true)) {
        json_error(403, 'Forbidden: requires role ' . implode(' or ', $roles));
    }

    return $user;
}

function require_owner_or_admin(array $user, int $ownerId): void
{
    if ($user['role'] !== 'admin' && (int) $user['user_id'] !== $ownerId) {
        json_error(403, 'Forbidden: you do not own this resource');
    }
}

function audit(?int $userId, string $action, string $detail = ''): void
{
    db()->prepare('INSERT INTO audit_logs (user_id, action, detail, ip_address) VALUES (?, ?, ?, ?)')
        ->execute([$userId, $action, $detail, client_ip()]);
}
