<?php

$user  = require_login();
$role  = $user['role'];
$stats = api_rows(api_get('/system/stats'));

render_head('Dashboard', 'Welcome back, ' . $user['full_name']);

match ($role) {
    'admin'     => render_stat_cards([
        ['Users', $stats['users'] ?? 0, ''],
        ['Venues', $stats['venues'] ?? 0, ''],
        ['Published events', $stats['published_events'] ?? 0, 'good'],
        ['Bookings', $stats['bookings'] ?? 0, ''],
        ['Revenue', money($stats['revenue'] ?? 0), 'good'],
        ['API requests today', $stats['requests_today'] ?? 0, 'warn'],
        ['Server errors today', $stats['errors_today'] ?? 0, 'bad'],
    ]),
    'organiser' => render_stat_cards([
        ['My venues', $stats['venues'] ?? 0, ''],
        ['My events', $stats['events'] ?? 0, ''],
        ['Upcoming', $stats['upcoming_events'] ?? 0, 'good'],
        ['Tickets sold', $stats['tickets_sold'] ?? 0, ''],
        ['Pending bookings', $stats['pending_bookings'] ?? 0, 'warn'],
        ['Revenue', money($stats['revenue'] ?? 0), 'good'],
    ]),
    default     => render_stat_cards([
        ['Active bookings', $stats['active_bookings'] ?? 0, 'good'],
        ['All bookings', $stats['total_bookings'] ?? 0, ''],
        ['Total spent', money($stats['total_spent'] ?? 0), ''],
        ['Events open', $stats['open_events'] ?? 0, 'good'],
    ]),
};

if ($role === 'customer') {
    echo '<div class="head-actions"><h2>Happening soon</h2></div>';
    render_event_cards(api_rows(api_get('/events', ['upcoming' => 1, 'limit' => 3])), page_exists('event.php') ? 'event.php' : '', 'Book now');
} else {
    $query = $role === 'organiser' ? ['mine' => 1, 'upcoming' => 1, 'limit' => 5] : ['upcoming' => 1, 'limit' => 5];

    echo '<div class="head-actions"><h2>Upcoming events</h2></div>';
    render_event_rows(api_rows(api_get('/events', $query)));

    if (nav_has('bookings.php')) {
        echo '<div class="head-actions"><h2>Latest bookings</h2><a class="btn sm ghost" href="bookings.php">View all</a></div>';
        render_booking_rows(api_rows(api_get('/bookings', ['limit' => 5])), true);
    }
}

render_foot();
