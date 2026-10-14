<?php

require __DIR__ . '/app.php';

$user = require_login('admin', 'organiser');
$id   = (int) input('id', 0);
$date = input('date', date('Y-m-d'));

$venueResponse = api_get("/venues/$id");

if (!api_ok($venueResponse)) {
    flash('Venue not found', 'bad');
    redirect('venues.php');
}

$venue  = $venueResponse['data'];
$errors = [];
$editId = (int) input('edit', 0);

if (is_post()) {
    csrf_check();

    $courtId = (int) ($_POST['court_id'] ?? 0);
    $payload = [
        'sport'       => $_POST['sport'] ?? '',
        'court_name'  => $_POST['court_name'] ?? '',
        'hourly_rate' => $_POST['hourly_rate'] ?? '',
        'is_active'   => isset($_POST['is_active']) ? 1 : 0,
    ];

    $errors = match ($_POST['action'] ?? '') {
        'add'    => api('POST', "/venues/$id/courts", $payload),
        'update' => api('PUT', "/venues/$id/courts/$courtId", $payload),
        'delete' => api('DELETE', "/venues/$id/courts/$courtId"),
        default  => [],
    };

    if (api_ok($errors)) {
        flash('Court saved');
        redirect("venue.php?id=$id");
    }

    if (($_POST['action'] ?? '') === 'delete') {
        flash($errors['message'] ?? 'Could not delete court', 'bad');
        redirect("venue.php?id=$id");
    }
}

$schedule = api_rows(api_get("/venues/$id/schedule", ['date' => $date]));

render_head($venue['venue_name'], $venue['address'] . ', ' . $venue['city'] . ' · capacity ' . $venue['capacity']);
show_error($errors);
?>
<div class="grid wide-left">
    <section class="card">
        <h2>Courts</h2>
        <?php if ($editId): ?>
            <form id="court-edit" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="court_id" value="<?= $editId ?>">
            </form>
        <?php endif; ?>
        <div class="table-wrap" style="margin:0">
        <table>
            <thead><tr><th>Court</th><th>Sport</th><th>Rate / hour</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($venue['courts'] as $court): ?>
                <?php if ((int) $court['court_id'] === $editId): ?>
                    <tr>
                        <td><input form="court-edit" name="court_name" value="<?= e($court['court_name']) ?>" required></td>
                        <td><select form="court-edit" name="sport"><?= sport_options($court['sport']) ?></select></td>
                        <td><input form="court-edit" type="number" step="0.01" min="0" name="hourly_rate" value="<?= e($court['hourly_rate']) ?>" required></td>
                        <td><label><input form="court-edit" type="checkbox" name="is_active" style="width:auto" <?= $court['is_active'] ? 'checked' : '' ?>> Active</label></td>
                        <td class="actions"><button form="court-edit" class="btn sm">Save</button> <a class="btn sm ghost" href="venue.php?id=<?= $id ?>">Cancel</a></td>
                    </tr>
                <?php else: ?>
                    <tr>
                        <td><strong><?= e($court['court_name']) ?></strong></td>
                        <td><?= sport_tag($court['sport']) ?></td>
                        <td><?= e(money($court['hourly_rate'])) ?></td>
                        <td><?= active_badge($court['is_active']) ?></td>
                        <td class="actions">
                            <a class="btn sm ghost" href="venue.php?id=<?= $id ?>&edit=<?= (int) $court['court_id'] ?>">Edit</a>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="court_id" value="<?= (int) $court['court_id'] ?>">
                                <?= confirm_button('Delete', 'Delete this court?') ?>
                            </form>
                        </td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <h2 style="margin-top:22px">Add a court</h2>
        <form method="post" class="form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <div class="form-row">
                <label>Court name<input name="court_name" value="<?= e(input('court_name')) ?>" required><?= field_error($errors, 'court_name') ?></label>
                <label>Sport<select name="sport"><?= sport_options(input('sport')) ?></select><?= field_error($errors, 'sport') ?></label>
                <label>Rate per hour (RM)<input type="number" step="0.01" min="0" name="hourly_rate" value="<?= e(input('hourly_rate')) ?>" required><?= field_error($errors, 'hourly_rate') ?></label>
            </div>
            <div><button class="btn">Add court</button></div>
        </form>
    </section>

    <section class="card">
        <h2>Daily schedule</h2>
        <form class="toolbar" method="get">
            <input type="hidden" name="id" value="<?= $id ?>">
            <label>Date<input type="date" name="date" value="<?= e($date) ?>"></label>
            <button class="btn">Show</button>
        </form>

        <h3>Events</h3>
        <?php if (!empty($schedule['events'])): ?>
            <ul class="checks">
                <?php foreach ($schedule['events'] as $event): ?>
                    <li><strong><?= e($event['title']) ?></strong> · <?= e(format_time($event['start_time'])) ?> - <?= e(format_time($event['end_time'])) ?> <?= status_badge($event['status']) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p><small>No events on this day.</small></p>
        <?php endif; ?>

        <h3 style="margin-top:18px">Court bookings</h3>
        <?php foreach ($schedule['courts'] ?? [] as $court): ?>
            <p style="margin:6px 0"><strong><?= e($court['court_name']) ?></strong>
            <?php if ($court['booked_slots']): ?>
                <?php foreach ($court['booked_slots'] as $slot): ?>
                    <span class="badge <?= e($slot['status']) ?>"><?= e(format_time($slot['slot_start'])) ?> - <?= e(format_time($slot['slot_end'])) ?></span>
                <?php endforeach; ?>
            <?php else: ?>
                <small>fully available</small>
            <?php endif; ?>
            </p>
        <?php endforeach; ?>
    </section>
</div>
<?php render_foot();
