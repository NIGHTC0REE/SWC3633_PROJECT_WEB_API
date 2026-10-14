<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/response.php';
require_once __DIR__ . '/lib/query.php';
require_once __DIR__ . '/middleware/error.php';
require_once __DIR__ . '/middleware/security.php';
require_once __DIR__ . '/middleware/logger.php';
require_once __DIR__ . '/middleware/rate_limit.php';
require_once __DIR__ . '/middleware/validation.php';
require_once __DIR__ . '/middleware/auth.php';

const RESOURCES = ['auth', 'users', 'venues', 'events', 'bookings', 'system'];

function request_segments(): array
{
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $path = preg_replace('#^.*?/api/?#', '', $path);

    return array_values(array_filter(explode('/', trim($path, '/')), 'strlen'));
}

register_error_handlers();
apply_security_headers();
start_request_log();

$segments = request_segments();
$method   = $_SERVER['REQUEST_METHOD'];
$resource = $segments[0] ?? '';

apply_rate_limits($resource, $segments[1] ?? '');

if ($resource === '') {
    json_out(200, [
        'success'   => true,
        'name'      => 'BOOM+ API',
        'version'   => '2.0',
        'resources' => RESOURCES,
    ]);
}

if (!in_array($resource, RESOURCES, true)) {
    json_error(404, 'Resource not found');
}

require_once __DIR__ . '/routes/' . $resource . '.php';

('handle_' . $resource)($method, array_slice($segments, 1));

json_error(404, 'Endpoint not found');
