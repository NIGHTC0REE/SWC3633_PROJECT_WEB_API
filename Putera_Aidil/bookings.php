<?php

require __DIR__ . '/app.php';

$user = require_login();

if ($user['role'] !== 'customer') {
    require_page('bookings_list');
    exit;
}

handle_booking_status_post('customer');

$response = api_get('/bookings', [
    'view'  => 'active',
    'q'     => input('q'),
    'sort'  => 'starts_at',
    'page'  => input('page', 1),
    'limit' => 8,
]);

render_head('Active bookings', 'Upcoming reservations you can still change or cancel');
show_error($response);
?>
<form class="toolbar card" method="get">
    <label>Search<input name="q" value="<?= e(input('q')) ?>" placeholder="Reference or event"></label>
    <button class="btn">Search</button>
</form>
<?php
render_booking_rows(api_rows($response), false, fn ($booking) => '<a class="btn sm" href="booking.php?id=' . (int) $booking['booking_id'] . '">Manage</a>'
    . booking_status_actions($booking, 'customer'));

render_pagination($response);
render_foot();
