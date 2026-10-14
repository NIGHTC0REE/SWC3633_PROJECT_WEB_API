<?php

require __DIR__ . '/app.php';

$user = require_login('admin', 'organiser');

if (is_post()) {
    csrf_check();

    $id       = (int) ($_POST['event_id'] ?? 0);
    $response = match ($_POST['action'] ?? '') {
        'status' => api('PATCH', "/events/$id", ['status' => $_POST['status'] ?? '']),
        'delete' => api('DELETE', "/events/$id"),
        default  => [],
    };

    respond_flash($response, 'Event updated', 'manage_events.php');
}

$events = api_get('/events', manager_scope($user) + [
    'q'      => input('q'),
    'sport'  => input('sport'),
    'status' => input('status'),
    'sort'   => input('sort', '-start_time'),
    'page'   => input('page', 1),
    'limit'  => 8,
]);

$transition = fn (array $event, string $status, string $label, string $class = 'ghost') => '<form method="post">' . csrf_field()
    . '<input type="hidden" name="action" value="status"><input type="hidden" name="event_id" value="' . (int) $event['event_id'] . '">'
    . '<input type="hidden" name="status" value="' . $status . '"><button class="btn sm ' . $class . '">' . $label . '</button></form>';

render_head($user['role'] === 'admin' ? 'All events' : 'My events', 'Create, schedule and publish events');
show_error($events);
?>
<div class="head-actions">
    <form class="toolbar" method="get" style="margin:0;flex:1">
        <label>Search<input name="q" value="<?= e(input('q')) ?>" placeholder="Title or venue"></label>
        <label>Sport<select name="sport"><?= sport_options(input('sport'), 'All sports') ?></select></label>
        <label>Status<select name="status"><?= select_options(['' => 'Any status', 'draft' => 'Draft', 'published' => 'Published', 'cancelled' => 'Cancelled', 'completed' => 'Completed'], input('status')) ?></select></label>
        <label>Sort<select name="sort"><?= select_options(['-start_time' => 'Latest date', 'start_time' => 'Earliest date', 'title' => 'Title A-Z', 'price' => 'Cheapest'], input('sort', '-start_time')) ?></select></label>
        <button class="btn">Filter</button>
    </form>
    <a class="btn" href="event_form.php">+ New event</a>
</div>
<?php
render_event_rows(api_rows($events), function (array $event) use ($transition) {
    $html = '<a class="btn sm" href="event_form.php?id=' . (int) $event['event_id'] . '">Manage</a>';

    if ($event['status'] === 'draft') {
        $html .= $transition($event, 'published', 'Publish', 'good');
    }

    if (in_array($event['status'], ['draft', 'published'], true)) {
        $html .= '<form method="post" onsubmit="return confirm(\'Cancel this event and refund all bookings?\')">' . csrf_field()
            . '<input type="hidden" name="action" value="status"><input type="hidden" name="event_id" value="' . (int) $event['event_id'] . '">'
            . '<input type="hidden" name="status" value="cancelled"><button class="btn sm ghost">Cancel</button></form>';
    }

    return $html . '<form method="post">' . csrf_field() . '<input type="hidden" name="action" value="delete">'
        . '<input type="hidden" name="event_id" value="' . (int) $event['event_id'] . '">' . confirm_button('Delete', 'Delete this event?') . '</form>';
});

render_pagination($events);
render_foot();
