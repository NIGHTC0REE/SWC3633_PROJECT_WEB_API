<?php

const APP_NAME = 'Hafiq';

const APP_NAV = [
    'admin' => [
        ['Dashboard', 'dashboard.php'],
        ['Users', 'users.php'],
        ['Audit log', 'audit.php'],
        ['Events', 'events.php'],
        ['Bookings', 'bookings.php'],
        ['My profile', 'profile.php'],
    ],
    'organiser' => [
        ['Dashboard', 'dashboard.php'],
        ['Events', 'events.php'],
        ['Bookings', 'bookings.php'],
        ['My profile', 'profile.php'],
    ],
    'customer' => [
        ['Home', 'dashboard.php'],
        ['Events', 'events.php'],
        ['My bookings', 'bookings.php'],
        ['My profile', 'profile.php'],
    ],
];

require_once __DIR__ . '/../shared/bootstrap.php';
