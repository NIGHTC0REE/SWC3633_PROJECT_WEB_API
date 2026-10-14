<?php

require __DIR__ . '/app.php';

require_login('customer');

$response = api_get('/bookings', ['view' => 'active', 'sort' => 'starts_at', 'page' => input('page', 1), 'limit' => 9]);
$tickets  = api_rows($response);

render_head('My tickets', 'Upcoming reservations with your QR codes');
show_error($response);

if (!$tickets) {
    render_empty('You have no upcoming bookings yet.');
    echo '<a class="btn" href="events.php">Browse events</a>';
} else {
?>
<div class="event-grid">
    <?php foreach ($tickets as $ticket): ?>
        <article class="event-card">
            <div class="event-cover <?= e(strtolower($ticket['sport'])) ?>"><span><?= e($ticket['sport']) ?></span><span><?= e(format_date($ticket['starts_at'])) ?></span></div>
            <div class="event-body">
                <h3><?= e($ticket['item_title']) ?></h3>
                <div class="event-meta"><?= e($ticket['venue_name']) ?><br><?= e(format_datetime($ticket['starts_at'])) ?></div>
                <div><?= status_badge($ticket['status']) ?> <code><?= e($ticket['booking_ref']) ?></code></div>
                <div class="event-foot"><span class="price"><?= e(money($ticket['total_amount'])) ?></span><a class="btn sm" href="booking.php?id=<?= (int) $ticket['booking_id'] ?>">Open ticket</a></div>
            </div>
        </article>
    <?php endforeach; ?>
</div>
<?php
}

render_pagination($response);
render_foot();
