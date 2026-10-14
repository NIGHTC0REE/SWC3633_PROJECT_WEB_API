<?php

require __DIR__ . '/app.php';

require_login('organiser', 'admin');

$ref     = strtoupper(trim((string) input('ref')));
$booking = null;

if (is_post()) {
    csrf_check();

    $id       = (int) ($_POST['booking_id'] ?? 0);
    $response = api('PATCH', "/bookings/$id/status", ['status' => 'completed']);

    respond_flash($response, 'Guest checked in', 'verify.php?ref=' . urlencode($_POST['ref'] ?? ''));
}

if ($ref !== '') {
    $matches = api_rows(api_get('/bookings', ['q' => $ref, 'limit' => 1]));
    $booking = $matches && strcasecmp($matches[0]['booking_ref'], $ref) === 0 ? $matches[0] : null;

    if ($booking) {
        $booking = api_rows(api_get('/bookings/' . $booking['booking_id']));
    }
}

render_head('Ticket check-in', 'Verify a booking reference and admit the guest');
?>
<section class="card">
    <form method="get" class="toolbar" style="margin:0">
        <label>Booking reference<input name="ref" value="<?= e($ref) ?>" placeholder="e.g. BM00000001" autofocus></label>
        <button class="btn">Verify</button>
    </form>
</section>

<?php if ($ref !== '' && !$booking): ?>
    <div class="alert bad">No booking with reference <?= e($ref) ?> was found for your events.</div>
<?php elseif ($booking): ?>
    <?php render_ticket($booking); ?>
    <?php if ($booking['status'] === 'confirmed'): ?>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="booking_id" value="<?= (int) $booking['booking_id'] ?>">
            <input type="hidden" name="ref" value="<?= e($ref) ?>">
            <button class="btn good">Check guest in</button>
        </form>
    <?php else: ?>
        <div class="alert bad">This booking is <?= e($booking['status']) ?> and cannot be checked in.</div>
    <?php endif; ?>
<?php endif; ?>
<?php render_foot();
