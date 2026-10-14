<?php

require __DIR__ . '/app.php';

require_login('admin');

$logs = api_get('/system/audit-logs', [
    'q'      => input('q'),
    'action' => input('action'),
    'page'   => input('page', 1),
    'limit'  => 12,
]);

$actions = ['LOGIN', 'LOGIN_FAIL', 'REGISTER', 'PASSWORD_CHANGE', 'PASSWORD_RESET', 'PROFILE_UPDATE', 'ROLE_CHANGE', 'USER_STATUS', 'BOOKING_CREATE', 'BOOKING_CANCELLED', 'EVENT_CREATE'];

render_head('Audit log', 'Security and activity trail recorded by the API');
show_error($logs);
?>
<form class="toolbar" method="get">
    <label>Search<input name="q" value="<?= e(input('q')) ?>" placeholder="User, action or detail"></label>
    <label>Action<select name="action"><?= select_options(['' => 'All actions'] + array_combine($actions, $actions), input('action')) ?></select></label>
    <button class="btn">Filter</button>
</form>

<div class="table-wrap">
<table>
    <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Detail</th><th>IP</th></tr></thead>
    <tbody>
    <?php foreach (api_rows($logs) as $log): ?>
        <tr>
            <td><?= e(format_datetime($log['created_at'])) ?></td>
            <td><?= e($log['user_name'] ?? 'Guest') ?></td>
            <td><code><?= e($log['action']) ?></code></td>
            <td><?= e($log['detail']) ?></td>
            <td><?= e($log['ip_address']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php
render_pagination($logs);
render_foot();
