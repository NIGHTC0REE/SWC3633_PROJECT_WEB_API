<?php

require __DIR__ . '/app.php';

require_login();

$booking = find_booking_for_page((int) input('id', 0));

render_head('Booking ' . $booking['booking_ref'], 'Your reservation details and e-ticket');
?>

<?php render_ticket($booking); ?>

<p>
    <button class="btn" onclick="window.print()">Print ticket</button>
    <a class="btn ghost" href="<?= user()['role'] === 'customer' ? 'tickets.php' : 'bookings.php' ?>">Back to list</a>
</p>
<?php render_foot();
