<?php

const APP_NAME = 'Ariff';

// Portal-specific skin: loads shared/assets/ariff.css on top of the shared
// role theme so the Ariff frontend is visually distinct from the other member
// portals while still calling the exact same shared REST API.
const APP_THEME = 'ariff';

const APP_NAV = [
    'admin' => [
        ['Dashboard', 'dashboard.php'],
        ['Events', 'events.php'],
        ['Bookings', 'bookings.php'],
        ['My profile', 'profile.php'],
    ],
    'organiser' => [
        ['Dashboard', 'dashboard.php'],
        ['Events', 'events.php'],
        ['Bookings', 'bookings.php'],
        ['Check-in', 'verify.php'],
        ['My profile', 'profile.php'],
    ],
    'customer' => [
        ['Home', 'dashboard.php'],
        ['Browse events', 'events.php'],
        ['Book a court', 'courts.php'],
        ['My tickets', 'tickets.php'],
        ['My profile', 'profile.php'],
    ],
];

require_once __DIR__ . '/../shared/bootstrap.php';

function find_booking_for_page(int $id): array
{
    $response = api_get("/bookings/$id");

    if (!api_ok($response)) {
        flash('Booking not found', 'bad');
        redirect('dashboard.php');
    }

    return $response['data'];
}
