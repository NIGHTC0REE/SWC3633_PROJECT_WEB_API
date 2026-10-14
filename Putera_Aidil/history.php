<?php

require __DIR__ . '/app.php';

require_login('customer');

$response = api_get('/bookings', [
    'view'   => 'history',
    'status' => input('status'),
    'from'   => input('from'),
    'to'     => input('to'),
    'sort'   => '-starts_at',
    'page'   => input('page', 1),
    'limit'  => 10,
]);

$rows  = api_rows($response);
$spent = array_sum(array_map(fn ($row) => in_array($row['status'], ['completed', 'confirmed'], true) ? $row['total_amount'] : 0, $rows));

render_head('Booking history', 'Past, completed and cancelled reservations');
show_error($response);

render_stat_cards([
    ['Records found', $response['meta']['total'] ?? 0, ''],
    ['Spent on this page', money($spent), 'good'],
]);
?>
<form class="toolbar card" method="get">
    <label>Status<select name="status"><?= select_options(['' => 'All', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'confirmed' => 'Confirmed (past)', 'pending' => 'Pending (past)'], input('status')) ?></select></label>
    <label>Booked from<input type="date" name="from" value="<?= e(input('from')) ?>"></label>
    <label>Booked to<input type="date" name="to" value="<?= e(input('to')) ?>"></label>
    <button class="btn">Filter</button>
</form>
<?php
render_booking_rows($rows, false, fn ($booking) => '<a class="btn sm ghost" href="booking.php?id=' . (int) $booking['booking_id'] . '">Receipt</a>');
render_pagination($response);
render_foot();
