<?php

require __DIR__ . '/app.php';

$user = require_login('customer');
$id   = (int) input('id', 0);

$found = api_get("/bookings/$id");

if (!api_ok($found)) {
    flash('Booking not found', 'bad');
    redirect('bookings.php');
}

$booking = $found['data'];
$errors  = [];

if (is_post()) {
    csrf_check();

    $payload = $booking['booking_type'] === 'court'
        ? [
            'slot_start' => ($_POST['date'] ?? '') . ' ' . str_pad((int) ($_POST['hour'] ?? 0), 2, '0', STR_PAD_LEFT) . ':00',
            'slot_end'   => date('Y-m-d H:i', strtotime(($_POST['date'] ?? '') . ' ' . (int) ($_POST['hour'] ?? 0) . ':00') + ((int) ($_POST['hours'] ?? 1)) * 3600),
        ]
        : ['quantity' => (int) ($_POST['quantity'] ?? 1)];

    $errors = api('PUT', "/bookings/$id", $payload);

    if (api_ok($errors)) {
        flash('Booking updated');
        redirect("booking.php?id=$id");
    }
}

handle_booking_status_post('customer');

$changeable = in_array($booking['status'], ['pending', 'confirmed'], true) && $booking['starts_at'] > date('Y-m-d H:i:s');
$hours      = [];

for ($hour = 7; $hour <= 22; $hour++) {
    $hours[$hour] = date('g:i A', strtotime("$hour:00"));
}

$slotHours = $booking['slot_start'] ? (strtotime($booking['slot_end']) - strtotime($booking['slot_start'])) / 3600 : 1;

render_head('Booking ' . $booking['booking_ref'], 'View, modify or cancel this reservation');
show_error($errors);

render_ticket($booking);
?>
<div class="grid two">
<?php if ($changeable): ?>
    <section class="card">
        <h2>Modify booking</h2>
        <form method="post" class="form">
            <?= csrf_field() ?>
            <?php if ($booking['booking_type'] === 'court'): ?>
                <div class="form-row">
                    <label>Date<input type="date" name="date" min="<?= date('Y-m-d') ?>" value="<?= e(date('Y-m-d', strtotime($booking['slot_start']))) ?>" required></label>
                    <label>Start<select name="hour"><?= select_options($hours, (int) date('G', strtotime($booking['slot_start']))) ?></select></label>
                    <label>Hours<select name="hours"><?= select_options([1 => '1', 2 => '2', 3 => '3', 4 => '4'], $slotHours) ?></select></label>
                </div>
                <?= field_error($errors, 'slot_end') ?><?= field_error($errors, 'slot_start') ?>
            <?php else: ?>
                <label>Number of tickets
                    <input type="number" name="quantity" min="1" max="10" value="<?= (int) $booking['quantity'] ?>" required>
                    <?= field_error($errors, 'quantity') ?>
                </label>
            <?php endif; ?>
            <div><button class="btn">Save changes</button></div>
        </form>
    </section>

    <section class="card">
        <h2>Cancel booking</h2>
        <p>Seats are released immediately and any online payment is marked as refunded.</p>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="booking_id" value="<?= $id ?>">
            <input type="hidden" name="status" value="cancelled">
            <?= confirm_button('Cancel this booking', 'Cancel this booking?', 'btn danger') ?>
        </form>
    </section>
<?php else: ?>
    <section class="card">
        <h2>No changes possible</h2>
        <p>This booking is <?= e($booking['status']) ?> or has already started, so it can no longer be modified.</p>
    </section>
<?php endif; ?>
</div>
<p><button class="btn ghost" onclick="window.print()">Print receipt</button> <a class="btn ghost" href="bookings.php">← Active bookings</a></p>
<?php render_foot();
