<?php

require __DIR__ . '/app.php';

$user   = require_login('admin', 'organiser');
$id     = (int) input('id', 0);
$errors = [];
$event  = [];

if ($id) {
    $found = api_get("/events/$id");

    if (!api_ok($found)) {
        flash('Event not found', 'bad');
        redirect('manage_events.php');
    }

    $event = $found['data'];
}

function posted_tickets(): array
{
    $tickets = [];

    foreach ($_POST['tt_name'] ?? [] as $index => $name) {
        if (trim($name) === '') {
            continue;
        }

        $tickets[] = [
            'type_name' => $name,
            'price'     => $_POST['tt_price'][$index] ?? '',
            'capacity'  => $_POST['tt_capacity'][$index] ?? '',
        ];
    }

    return $tickets;
}

if (is_post()) {
    csrf_check();

    $section  = $_POST['section'] ?? 'event';
    $ticketId = (int) ($_POST['ticket_id'] ?? 0);

    $eventPayload = [
        'venue_id'    => $_POST['venue_id'] ?? '',
        'sport'       => $_POST['sport'] ?? '',
        'title'       => $_POST['title'] ?? '',
        'description' => $_POST['description'] ?? '',
        'start_time'  => $_POST['start_time'] ?? '',
        'end_time'    => $_POST['end_time'] ?? '',
        'status'      => $_POST['status'] ?? 'draft',
    ];

    $ticketPayload = [
        'type_name' => $_POST['type_name'] ?? '',
        'price'     => $_POST['price'] ?? '',
        'capacity'  => $_POST['capacity'] ?? '',
    ];

    $errors = match ($section) {
        'event'         => $id ? api('PUT', "/events/$id", $eventPayload) : api('POST', '/events', $eventPayload + ['ticket_types' => posted_tickets()]),
        'ticket-add'    => api('POST', "/events/$id/tickets", $ticketPayload),
        'ticket-update' => api('PUT', "/events/$id/tickets/$ticketId", $ticketPayload),
        'ticket-delete' => api('DELETE', "/events/$id/tickets/$ticketId"),
        default         => [],
    };

    if (api_ok($errors)) {
        flash($section === 'event' ? 'Event saved' : 'Ticket capacity saved');
        redirect('event_form.php?id=' . ($id ?: $errors['data']['event_id']));
    }

    if ($section === 'ticket-delete') {
        flash($errors['message'] ?? 'Could not delete ticket type', 'bad');
        redirect("event_form.php?id=$id");
    }
}

$value = fn (string $field, $default = '') => is_post() && ($_POST['section'] ?? 'event') === 'event' ? input($field, $default) : ($event[$field] ?? $default);

$venues = api_rows(api_get('/venues', manager_scope($user) + ['is_active' => 1, 'limit' => 100, 'sort' => 'name']));
$venueOptions = array_column($venues, 'venue_name', 'venue_id');
$locked = $id && in_array($event['status'], ['cancelled', 'completed'], true);

render_head($id ? 'Manage event' : 'New event', $id ? $event['title'] : 'Schedule a new event and set ticket capacity');

if ($locked) {
    echo '<div class="alert bad">This event is ' . e($event['status']) . ' and can no longer be edited.</div>';
}

show_error($errors);
?>
<div class="grid two">
<section class="card">
    <h2>Event details</h2>
    <form method="post" class="form">
        <?= csrf_field() ?>
        <input type="hidden" name="section" value="event">
        <label>Title<input name="title" value="<?= e($value('title')) ?>" required <?= $locked ? 'disabled' : '' ?>><?= field_error($errors, 'title') ?></label>
        <div class="form-row">
            <label>Venue<select name="venue_id" <?= $locked ? 'disabled' : '' ?>><?= select_options($venueOptions, $value('venue_id')) ?></select><?= field_error($errors, 'venue_id') ?></label>
            <label>Sport<select name="sport" <?= $locked ? 'disabled' : '' ?>><?= sport_options($value('sport')) ?></select><?= field_error($errors, 'sport') ?></label>
        </div>
        <div class="form-row">
            <label>Starts<input type="datetime-local" name="start_time" value="<?= e(datetime_local($value('start_time'))) ?>" required <?= $locked ? 'disabled' : '' ?>><?= field_error($errors, 'start_time') ?></label>
            <label>Ends<input type="datetime-local" name="end_time" value="<?= e(datetime_local($value('end_time'))) ?>" required <?= $locked ? 'disabled' : '' ?>><?= field_error($errors, 'end_time') ?></label>
        </div>
        <label>Description<textarea name="description" <?= $locked ? 'disabled' : '' ?>><?= e($value('description')) ?></textarea><?= field_error($errors, 'description') ?></label>
        <label>Status
            <select name="status" <?= $locked ? 'disabled' : '' ?>>
                <?= select_options($id ? ['draft' => 'Draft', 'published' => 'Published', 'cancelled' => 'Cancelled'] : ['draft' => 'Draft', 'published' => 'Published'], $value('status', 'draft')) ?>
            </select>
            <?= field_error($errors, 'status') ?>
        </label>

        <?php if (!$id): ?>
            <h2 style="margin-top:8px">Ticket types</h2>
            <?= field_error($errors, 'ticket_types') ?>
            <?php for ($row = 0; $row < 3; $row++): ?>
                <div class="form-row">
                    <label>Name<input name="tt_name[]" value="<?= e($_POST['tt_name'][$row] ?? ($row === 0 ? 'General' : '')) ?>"></label>
                    <label>Price (RM)<input type="number" step="0.01" min="0" name="tt_price[]" value="<?= e($_POST['tt_price'][$row] ?? '') ?>"></label>
                    <label>Capacity<input type="number" min="1" name="tt_capacity[]" value="<?= e($_POST['tt_capacity'][$row] ?? '') ?>"></label>
                </div>
            <?php endfor; ?>
        <?php endif; ?>

        <?php if (!$locked): ?><div><button class="btn"><?= $id ? 'Save changes' : 'Create event' ?></button> <a class="btn ghost" href="manage_events.php">Back</a></div><?php endif; ?>
    </form>
</section>

<?php if ($id): ?>
<section class="card">
    <h2>Ticket capacity</h2>
    <p><small>Venue capacity: <?= (int) $event['venue_capacity'] ?> · Allocated: <?= (int) $event['total_capacity'] ?></small></p>
    <?php foreach ($event['ticket_types'] as $ticket): ?>
        <div class="tile" style="margin-bottom:12px">
            <form method="post" class="form">
                <?= csrf_field() ?>
                <input type="hidden" name="section" value="ticket-update">
                <input type="hidden" name="ticket_id" value="<?= (int) $ticket['ticket_type_id'] ?>">
                <div class="form-row">
                    <label>Name<input name="type_name" value="<?= e($ticket['type_name']) ?>" required></label>
                    <label>Price (RM)<input type="number" step="0.01" min="0" name="price" value="<?= e($ticket['price']) ?>" required></label>
                    <label>Capacity<input type="number" min="<?= max(1, (int) $ticket['sold']) ?>" name="capacity" value="<?= (int) $ticket['capacity'] ?>" required></label>
                </div>
                <div class="meter"><span style="width:<?= $ticket['capacity'] ? round($ticket['sold'] / $ticket['capacity'] * 100) : 0 ?>%"></span></div>
                <small><?= (int) $ticket['sold'] ?> sold · <?= (int) $ticket['remaining'] ?> remaining</small>
                <div><button class="btn sm">Update</button></div>
            </form>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="section" value="ticket-delete">
                <input type="hidden" name="ticket_id" value="<?= (int) $ticket['ticket_type_id'] ?>">
                <?= confirm_button('Remove ticket type', 'Remove this ticket type?') ?>
            </form>
        </div>
    <?php endforeach; ?>

    <?php if (!$locked): ?>
    <h2>Add ticket type</h2>
    <form method="post" class="form">
        <?= csrf_field() ?>
        <input type="hidden" name="section" value="ticket-add">
        <div class="form-row">
            <label>Name<input name="type_name" required><?= field_error($errors, 'type_name') ?></label>
            <label>Price (RM)<input type="number" step="0.01" min="0" name="price" required><?= field_error($errors, 'price') ?></label>
            <label>Capacity<input type="number" min="1" name="capacity" required><?= field_error($errors, 'capacity') ?></label>
        </div>
        <div><button class="btn">Add ticket type</button></div>
    </form>
    <?php endif; ?>
</section>
<?php endif; ?>
</div>
<?php render_foot();
