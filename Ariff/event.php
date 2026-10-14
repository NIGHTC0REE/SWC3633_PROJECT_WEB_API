<?php

require __DIR__ . '/app.php';

$user = require_login();
$id   = (int) input('id', 0);
$found = api_get("/events/$id");

if (!api_ok($found)) {
    flash('Event not found', 'bad');
    redirect('events.php');
}

$event        = $found['data'];
$availability = api_rows(api_get("/events/$id/availability"));
$errors       = [];

if (is_post() && $user['role'] === 'customer') {
    csrf_check();

    $errors = api('POST', '/bookings', [
        'booking_type'   => 'event_ticket',
        'ticket_type_id' => (int) ($_POST['ticket_type_id'] ?? 0),
        'quantity'       => (int) ($_POST['quantity'] ?? 1),
        'payment_method' => $_POST['payment_method'] ?? '',
    ]);

    if (api_ok($errors)) {
        flash('Booking confirmed. Show the QR code at the gate.');
        redirect('booking.php?id=' . $errors['data']['booking_id']);
    }
}

$bookable = !empty($availability['bookable']);

render_head($event['title'], $event['venue_name'] . ', ' . $event['city']);
show_error($errors);
?>
<div class="grid two">
    <section class="card">
        <p><?= sport_tag($event['sport']) ?> <?= status_badge($event['status']) ?></p>
        <h2><?= e(format_datetime($event['start_time'])) ?></h2>
        <p>Ends <?= e(format_datetime($event['end_time'])) ?></p>
        <p><?= nl2br(e($event['description'])) ?></p>
        <p><small>Organised by <?= e($event['organiser_name']) ?></small></p>
        <a class="btn ghost" href="events.php">← All events</a>
    </section>

    <section class="card">
        <h2>Tickets <small>· live availability</small></h2>
        <?php if ($user['role'] !== 'customer'): ?>
            <div class="table-wrap"><table>
                <thead><tr><th>Ticket</th><th>Price</th><th>Sold</th><th>Remaining</th></tr></thead>
                <tbody>
                <?php foreach ($availability['ticket_types'] ?? [] as $ticket): ?>
                    <tr><td><?= e($ticket['type_name']) ?></td><td><?= e(money($ticket['price'])) ?></td><td><?= (int) $ticket['sold'] ?></td><td><?= (int) $ticket['remaining'] ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php elseif (!$bookable): ?>
            <div class="alert bad">Bookings are closed for this event or tickets are sold out.</div>
        <?php else: ?>
            <form method="post" class="form" id="checkout">
                <?= csrf_field() ?>
                <label>Ticket type
                    <select name="ticket_type_id" id="ticket">
                        <?php foreach ($availability['ticket_types'] as $ticket): ?>
                            <option value="<?= (int) $ticket['ticket_type_id'] ?>" data-price="<?= e($ticket['price']) ?>" data-left="<?= (int) $ticket['remaining'] ?>" <?= $ticket['remaining'] < 1 ? 'disabled' : '' ?>>
                                <?= e($ticket['type_name']) ?> · <?= e(money($ticket['price'])) ?> · <?= $ticket['remaining'] > 0 ? (int) $ticket['remaining'] . ' left' : 'sold out' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?= field_error($errors, 'ticket_type_id') ?>
                </label>
                <label>Quantity
                    <input type="number" name="quantity" id="quantity" min="1" max="10" value="<?= e(input('quantity', 1)) ?>" required>
                    <?= field_error($errors, 'quantity') ?>
                </label>
                <label>Payment method
                    <select name="payment_method"><?= select_options(PAYMENT_LIST, input('payment_method', 'card')) ?></select>
                    <?= field_error($errors, 'payment_method') ?>
                </label>
                <div class="alert ok" style="margin:0">Total: <strong id="total">RM 0.00</strong></div>
                <button class="btn block">Confirm &amp; pay</button>
            </form>
            <script>
                const ticket = document.getElementById('ticket');
                const quantity = document.getElementById('quantity');
                const total = document.getElementById('total');
                function recalc() {
                    const option = ticket.options[ticket.selectedIndex];
                    quantity.max = Math.min(10, option.dataset.left);
                    total.textContent = 'RM ' + (option.dataset.price * quantity.value).toFixed(2);
                }
                ticket.addEventListener('change', recalc);
                quantity.addEventListener('input', recalc);
                recalc();
            </script>
        <?php endif; ?>
    </section>
</div>
<?php render_foot();
