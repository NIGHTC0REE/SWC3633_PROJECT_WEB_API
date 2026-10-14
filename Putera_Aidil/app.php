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

require_once __DIR__ . '/../shared/bootstrap.php';
