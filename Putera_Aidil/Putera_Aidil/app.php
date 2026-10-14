<?php

const APP_NAME = 'Putera Aidil';

const APP_NAV = [
    'admin' => [
        ['Dashboard', 'dashboard.php'],
        ['Bookings', 'bookings.php'],
        ['API request log', 'logs.php'],
        ['My profile', 'profile.php'],
    ],
    'organiser' => [
        ['Dashboard', 'dashboard.php'],
        ['Bookings', 'bookings.php'],
        ['Events', 'events.php'],
        ['My profile', 'profile.php'],
    ],
    'customer' => [
        ['Overview', 'dashboard.php'],
        ['Active bookings', 'bookings.php'],
        ['History', 'history.php'],
        ['My profile', 'profile.php'],
    ],
];

// Apply this portal's own theme on every page without touching other members' files:
// adds class "pa" to <body> and loads shared/assets/putera.css after the shared stylesheets.
ob_start(static function (string $html): string {
    $html = preg_replace_callback('/<body([^>]*)>/i', static function (array $m): string {
        $attrs = $m[1];
        return preg_match('/class\s*=\s*"/i', $attrs)
            ? '<body' . preg_replace('/class\s*=\s*"/i', 'class="pa ', $attrs, 1) . '>'
            : '<body class="pa"' . $attrs . '>';
    }, $html, 1);

    return str_replace('</head>', '<link rel="stylesheet" href="' . SHARED_URL . '/putera.css?v=2"></head>', $html);
});

require_once __DIR__ . '/../shared/bootstrap.php';
