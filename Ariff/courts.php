<?php

require __DIR__ . '/app.php';

$user    = require_login('customer');
$venueId = (int) input('venue', 0);
$date    = input('date', date('Y-m-d', strtotime('+1 day')));
$errors  = [];

if (is_post()) {
    csrf_check();

    $start = $date . ' ' . str_pad((int) ($_POST['hour'] ?? 0), 2, '0', STR_PAD_LEFT) . ':00';
    $end   = date('Y-m-d H:i', strtotime($start) + ((int) ($_POST['hours'] ?? 1)) * 3600);

    $errors = api('POST', '/bookings', [
        'booking_type'   => 'court',
        'court_id'       => (int) ($_POST['court_id'] ?? 0),
        'slot_start'     => $start,
        'slot_end'       => $end,
        'payment_method' => $_POST['payment_method'] ?? '',
    ]);

    if (api_ok($errors)) {
        flash('Court reserved. Your QR ticket is ready.');
        redirect('booking.php?id=' . $errors['data']['booking_id']);
    }
}

$venues   = api_rows(api_get('/venues', ['limit' => 50, 'sort' => 'name']));
$schedule = $venueId ? api_rows(api_get("/venues/$venueId/schedule", ['date' => $date])) : [];

$hours = [];
for ($hour = 7; $hour <= 22; $hour++) {
    $hours[$hour] = date('g:i A', strtotime("$hour:00"));
}

render_head('Book a court', 'Pick a venue, choose a free slot and pay online');
show_error($errors);
?>
<form class="toolbar card" method="get">
    <label>Venue
        <select name="venue" onchange="this.form.submit()">
            <?= select_options(['' => 'Choose a venue'] + array_column($venues, 'venue_name', 'venue_id'), $venueId) ?>
        </select>
    </label>
    <label>Date<input type="date" name="date" min="<?= date('Y-m-d') ?>" value="<?= e($date) ?>"></label>
    <button class="btn">Check availability</button>
</form>

<?php if ($schedule): ?>
<div class="grid two">
    <section class="card">
        <h2><?= e($schedule['venue']) ?> · <?= e(format_date($date)) ?></h2>
        <?php foreach ($schedule['events'] as $event): ?>
            <div class="alert bad">Event "<?= e($event['title']) ?>" uses the venue <?= e(format_time($event['start_time'])) ?> - <?= e(format_time($event['end_time'])) ?></div>
        <?php endforeach; ?>
        <?php foreach ($schedule['courts'] as $court): ?>
            <p style="margin:10px 0 4px"><strong><?= e($court['court_name']) ?></strong> <?= sport_tag($court['sport']) ?> · <?= e(money($court['hourly_rate'])) ?>/hour</p>
            <?php if ($court['booked_slots']): ?>
                <?php foreach ($court['booked_slots'] as $slot): ?>
                    <span class="badge cancelled">Taken <?= e(format_time($slot['slot_start'])) ?> - <?= e(format_time($slot['slot_end'])) ?></span>
                <?php endforeach; ?>
            <?php else: ?>
                <span class="badge confirmed">Free all day</span>
            <?php endif; ?>
        <?php endforeach; ?>
    </section>

    <section class="card">
        <h2>Reserve a slot</h2>
        <form method="post" class="form">
            <?= csrf_field() ?>
            <label>Court
                <select name="court_id">
                    <?php foreach ($schedule['courts'] as $court): ?>
                        <?php if ($court['is_active']): ?>
                            <option value="<?= (int) $court['court_id'] ?>" <?= (int) input('court_id') === (int) $court['court_id'] ? 'selected' : '' ?>><?= e($court['court_name']) ?> (<?= e($court['sport']) ?>)</option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
                <?= field_error($errors, 'court_id') ?>
            </label>
            <div class="form-row">
                <label>Start time<select name="hour"><?= select_options($hours, input('hour', 19)) ?></select><?= field_error($errors, 'slot_start') ?></label>
                <label>Duration<select name="hours"><?= select_options([1 => '1 hour', 2 => '2 hours', 3 => '3 hours', 4 => '4 hours'], input('hours', 1)) ?></select><?= field_error($errors, 'slot_end') ?></label>
            </div>
            <label>Payment method<select name="payment_method"><?= select_options(PAYMENT_LIST, input('payment_method', 'card')) ?></select></label>
            <button class="btn block">Reserve &amp; pay</button>
        </form>
    </section>
</div>
<?php elseif ($venueId): ?>
    <?php render_empty('Venue not found.'); ?>
<?php else: ?>
    <div class="event-grid">
        <?php foreach ($venues as $venue): ?>
            <article class="event-card">
                <div class="event-cover futsal"><span><?= e($venue['city']) ?></span><span><?= (int) $venue['court_count'] ?> courts</span></div>
                <div class="event-body">
                    <h3><?= e($venue['venue_name']) ?></h3>
                    <div class="event-meta"><?= e($venue['address']) ?><br><?= e($venue['sports'] ?: 'No courts yet') ?></div>
                    <div class="event-foot"><span></span><a class="btn sm" href="courts.php?venue=<?= (int) $venue['venue_id'] ?>&date=<?= e($date) ?>">See slots</a></div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php render_foot();
