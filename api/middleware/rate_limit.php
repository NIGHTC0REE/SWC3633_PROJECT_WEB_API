<?php

function rate_limit(string $bucket, int $max, int $window = RATE_LIMIT_WINDOW): void
{
    $slot = intdiv(time(), $window) * $window;

    db()->prepare(
        'INSERT INTO rate_limits (bucket, window_start, hits) VALUES (?, ?, 1)
         ON DUPLICATE KEY UPDATE hits = hits + 1'
    )->execute([$bucket, $slot]);

    $statement = db()->prepare('SELECT hits FROM rate_limits WHERE bucket = ? AND window_start = ?');
    $statement->execute([$bucket, $slot]);
    $hits = (int) $statement->fetchColumn();

    header('X-RateLimit-Limit: ' . $max);
    header('X-RateLimit-Remaining: ' . max(0, $max - $hits));
    header('X-RateLimit-Reset: ' . ($slot + $window));

    if ($hits > $max) {
        header('Retry-After: ' . max(1, $slot + $window - time()));
        json_error(429, 'Too many requests, slow down');
    }

    if (random_int(1, 100) === 1) {
        db()->prepare('DELETE FROM rate_limits WHERE window_start < ?')->execute([time() - 3600]);
    }
}

function apply_rate_limits(string $resource, string $action): void
{
    $ip = client_ip();

    rate_limit('ip:' . $ip, RATE_LIMIT_GLOBAL);

    if ($resource === 'auth' && in_array($action, ['login', 'register', 'forgot-password', 'reset-password'], true)) {
        rate_limit('auth:' . $action . ':' . $ip, RATE_LIMIT_AUTH);
    }
}
