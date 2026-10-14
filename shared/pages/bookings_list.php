<?php

$user = require_login();
$role = $user['role'];

handle_booking_status_post($role);

$response = api_get('/bookings', [
    'q'      => input('q'),
    'status' => input('status'),
    'sort'   => '-created_at',
    'page'   => input('page', 1),
    'limit'  => 10,
]);

render_head($role === 'customer' ? 'My bookings' : 'Bookings', 'Search, filter and manage reservations');
show_error($response);
?>
<form class="toolbar" method="get">
    <label>Search<input name="q" value="<?= e(input('q')) ?>" placeholder="Reference, customer or event"></label>
    <label>Status<select name="status"><?= select_options(['' => 'All statuses', 'pending' => 'Pending', 'confirmed' => 'Confirmed', 'completed' => 'Completed', 'cancelled' => 'Cancelled'], input('status')) ?></select></label>
    <button class="btn">Filter</button>
</form>
<?php
render_booking_rows(api_rows($response), $role !== 'customer', fn ($booking) => booking_status_actions($booking, $role));
render_pagination($response);
render_foot();
