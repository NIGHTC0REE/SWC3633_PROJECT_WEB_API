<?php

function current_user_id(?int $set = null): ?int
{
    static $userId = null;

    if ($set !== null) {
        $userId = $set;
    }

    return $userId;
}

function start_request_log(): void
{
    $startedAt = microtime(true);
    $method    = $_SERVER['REQUEST_METHOD'];
    $path      = substr(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '', 0, 255);
    $path      = preg_replace('#^.*?/api#', '', $path) ?: '/';

    register_shutdown_function(function () use ($startedAt, $method, $path) {
        try {
            db()->prepare(
                'INSERT INTO request_logs (user_id, method, path, status_code, duration_ms, ip_address)
                 VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([
                current_user_id(),
                $method,
                $path,
                http_response_code() ?: 200,
                (int) round((microtime(true) - $startedAt) * 1000),
                client_ip(),
            ]);
        } catch (Throwable $exception) {
            error_log($exception);
        }
    });
}
