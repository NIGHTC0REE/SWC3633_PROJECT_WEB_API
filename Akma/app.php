<?php

const APP_NAME = 'Akma';

const APP_NAV = [
    'admin' => [
        ['Dashboard', 'dashboard.php'],
        ['Venues', 'venues.php'],
        ['Events', 'manage_events.php'],
        ['Bookings', 'bookings.php'],
        ['My profile', 'profile.php'],
    ],
    'organiser' => [
        ['Dashboard', 'dashboard.php'],
        ['My venues', 'venues.php'],
        ['My events', 'manage_events.php'],
        ['Bookings', 'bookings.php'],
        ['My profile', 'profile.php'],
    ],
    'customer' => [
        ['Home', 'dashboard.php'],
        ['Browse events', 'events.php'],
        ['My profile', 'profile.php'],
    ],
];

require_once __DIR__ . '/../shared/bootstrap.php';

function manager_scope(array $user): array
{
    return $user['role'] === 'organiser' ? ['mine' => 1] : [];
}

function datetime_local(?string $value): string
{
    return $value ? date('Y-m-d\TH:i', strtotime($value)) : '';
}
