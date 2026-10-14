<?php

require __DIR__ . '/app.php';

$user = require_login('admin', 'organiser');

if (is_post()) {
    csrf_check();

    $id       = (int) ($_POST['venue_id'] ?? 0);
    $response = match ($_POST['action'] ?? '') {
        'toggle' => api('PATCH', "/venues/$id", ['is_active' => (int) $_POST['is_active']]),
        'delete' => api('DELETE', "/venues/$id"),
        default  => [],
    };

    respond_flash($response, 'Venue saved', 'venues.php');
}

$venues = api_get('/venues', manager_scope($user) + [
    'q'     => input('q'),
    'city'  => input('city'),
    'sport' => input('sport'),
    'sort'  => input('sort', 'name'),
    'page'  => input('page', 1),
    'limit' => 8,
]);

render_head($user['role'] === 'admin' ? 'All venues' : 'My venues', 'Manage venues, courts and schedules');
show_error($venues);
?>
<div class="head-actions">
    <form class="toolbar" method="get" style="margin:0;flex:1">
        <label>Search<input name="q" value="<?= e(input('q')) ?>" placeholder="Name, address or city"></label>
        <label>Sport<select name="sport"><?= sport_options(input('sport'), 'All sports') ?></select></label>
        <label>Sort<select name="sort"><?= select_options(['name' => 'Name A-Z', '-capacity' => 'Largest first', 'city' => 'City', '-created_at' => 'Newest'], input('sort', 'name')) ?></select></label>
        <button class="btn">Filter</button>
    </form>
    <a class="btn" href="venue_form.php">+ New venue</a>
</div>

<div class="table-wrap">
<table>
    <thead><tr><th>Venue</th><th>City</th><th>Sports</th><th>Courts</th><th>Capacity</th><th>Owner</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach (api_rows($venues) as $venue): ?>
        <tr>
            <td><strong><?= e($venue['venue_name']) ?></strong><br><small><?= e($venue['address']) ?></small></td>
            <td><?= e($venue['city']) ?></td>
            <td><?= e($venue['sports'] ?: '-') ?></td>
            <td><?= (int) $venue['court_count'] ?></td>
            <td><?= (int) $venue['capacity'] ?></td>
            <td><?= e($venue['owner_name']) ?></td>
            <td><?= active_badge($venue['is_active']) ?></td>
            <td class="actions">
                <a class="btn sm" href="venue.php?id=<?= (int) $venue['venue_id'] ?>">Courts &amp; schedule</a>
                <a class="btn sm ghost" href="venue_form.php?id=<?= (int) $venue['venue_id'] ?>">Edit</a>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="venue_id" value="<?= (int) $venue['venue_id'] ?>">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="is_active" value="<?= $venue['is_active'] ? 0 : 1 ?>">
                    <button class="btn sm ghost"><?= $venue['is_active'] ? 'Deactivate' : 'Activate' ?></button>
                </form>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="venue_id" value="<?= (int) $venue['venue_id'] ?>">
                    <input type="hidden" name="action" value="delete">
                    <?= confirm_button('Delete', 'Delete this venue?') ?>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php
render_pagination($venues);
render_foot();
