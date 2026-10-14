<?php

require __DIR__ . '/app.php';

require_login('admin');

$logs = api_get('/system/request-logs', [
    'q'           => input('q'),
    'method'      => input('method'),
    'status_code' => input('status_code'),
    'sort'        => input('sort', '-created_at'),
    'page'        => input('page', 1),
    'limit'       => 15,
]);

$rows    = api_rows($logs);
$slow    = count(array_filter($rows, fn ($row) => $row['duration_ms'] > 200));
$failed  = count(array_filter($rows, fn ($row) => $row['status_code'] >= 400));
$average = $rows ? round(array_sum(array_column($rows, 'duration_ms')) / count($rows)) : 0;

render_head('API request log', 'Every request handled by the REST API');
show_error($logs);

render_stat_cards([
    ['Requests logged', $logs['meta']['total'] ?? 0, ''],
    ['Avg time (this page)', $average . ' ms', 'good'],
    ['Slow (>200 ms)', $slow, 'warn'],
    ['Errors (4xx/5xx)', $failed, 'bad'],
]);
?>
<form class="toolbar" method="get">
    <label>Path<input name="q" value="<?= e(input('q')) ?>" placeholder="/bookings"></label>
    <label>Method<select name="method"><?= select_options(['' => 'Any', 'GET' => 'GET', 'POST' => 'POST', 'PUT' => 'PUT', 'PATCH' => 'PATCH', 'DELETE' => 'DELETE'], input('method')) ?></select></label>
    <label>Status<select name="status_code"><?= select_options(['' => 'Any', 200 => '200', 201 => '201', 400 => '400', 401 => '401', 403 => '403', 404 => '404', 409 => '409', 422 => '422', 429 => '429', 500 => '500'], input('status_code')) ?></select></label>
    <label>Sort<select name="sort"><?= select_options(['-created_at' => 'Newest', '-duration' => 'Slowest', 'status' => 'Status'], input('sort', '-created_at')) ?></select></label>
    <button class="btn">Filter</button>
</form>

<div class="table-wrap">
<table>
    <thead><tr><th>Time</th><th>Method</th><th>Path</th><th>Status</th><th>Duration</th><th>User</th><th>IP</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $row): ?>
        <tr>
            <td><?= e(format_datetime($row['created_at'])) ?></td>
            <td><code><?= e($row['method']) ?></code></td>
            <td><?= e($row['path']) ?></td>
            <td><span class="badge <?= $row['status_code'] < 400 ? 'confirmed' : ($row['status_code'] < 500 ? 'pending' : 'cancelled') ?>"><?= (int) $row['status_code'] ?></span></td>
            <td><?= (int) $row['duration_ms'] ?> ms</td>
            <td><?= $row['user_id'] ? '#' . (int) $row['user_id'] : 'guest' ?></td>
            <td><?= e($row['ip_address']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php
render_pagination($logs);
render_foot();
