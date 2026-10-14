<?php

require __DIR__ . '/app.php';

$admin    = require_login('admin');
$response = [];

if (is_post()) {
    csrf_check();

    $id     = (int) ($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    $response = match ($action) {
        'create' => api('POST', '/users', [
            'full_name' => $_POST['full_name'] ?? '',
            'email'     => $_POST['email'] ?? '',
            'phone'     => $_POST['phone'] ?? '',
            'password'  => $_POST['password'] ?? '',
            'role'      => $_POST['role'] ?? '',
        ]),
        'role'   => api('PUT', "/users/$id/role", ['role' => $_POST['role'] ?? '']),
        'status' => api('PUT', "/users/$id/status", ['is_active' => (int) ($_POST['is_active'] ?? 0)]),
        'delete' => api('DELETE', "/users/$id"),
        default  => [],
    };

    if (api_ok($response)) {
        flash('Saved successfully');
        redirect('users.php');
    }

    if ($action !== 'create') {
        flash($response['message'] ?? 'Request failed', 'bad');
        redirect('users.php');
    }
}

$users = api_get('/users', [
    'q'     => input('q'),
    'role'  => input('role'),
    'sort'  => input('sort', 'id'),
    'page'  => input('page', 1),
    'limit' => 8,
]);

$roles = ['admin' => 'Admin', 'organiser' => 'Organiser', 'customer' => 'Customer'];

render_head('User management', 'Create accounts, assign roles and control access');
show_error($response);
?>
<details class="card" <?= $response ? 'open' : '' ?>>
    <summary><strong>Create a user</strong></summary>
    <form method="post" class="form" style="margin-top:14px">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">
        <div class="form-row">
            <label>Full name<input name="full_name" value="<?= e(input('full_name')) ?>" required><?= field_error($response, 'full_name') ?></label>
            <label>Email<input type="email" name="email" value="<?= e(input('email')) ?>" required><?= field_error($response, 'email') ?></label>
            <label>Phone<input name="phone" value="<?= e(input('phone')) ?>"><?= field_error($response, 'phone') ?></label>
            <label>Password<input type="password" name="password" required><?= field_error($response, 'password') ?></label>
            <label>Role<select name="role"><?= select_options($roles, input('role', 'customer')) ?></select><?= field_error($response, 'role') ?></label>
        </div>
        <div><button class="btn">Create user</button></div>
    </form>
</details>

<form class="toolbar" method="get" style="margin-top:20px">
    <label>Search<input name="q" value="<?= e(input('q')) ?>" placeholder="Name or email"></label>
    <label>Role<select name="role"><?= select_options(['' => 'All roles'] + $roles, input('role')) ?></select></label>
    <label>Sort<select name="sort"><?= select_options(['id' => 'Oldest first', '-created_at' => 'Newest first', 'name' => 'Name A-Z', 'role' => 'Role'], input('sort', 'id')) ?></select></label>
    <button class="btn">Apply</button>
</form>

<div class="table-wrap">
<table>
    <thead><tr><th>#</th><th>User</th><th>Phone</th><th>Role</th><th>Status</th><th>Joined</th><th></th></tr></thead>
    <tbody>
    <?php foreach (api_rows($users) as $row): $self = (int) $row['user_id'] === (int) $admin['user_id']; ?>
        <tr>
            <td><?= (int) $row['user_id'] ?></td>
            <td><strong><?= e($row['full_name']) ?></strong><br><small><?= e($row['email']) ?></small></td>
            <td><?= e($row['phone'] ?: '-') ?></td>
            <td>
                <?php if ($self): ?>
                    <span class="badge role-admin">Admin (you)</span>
                <?php else: ?>
                    <form method="post" style="display:flex;gap:6px">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="role">
                        <input type="hidden" name="user_id" value="<?= (int) $row['user_id'] ?>">
                        <select name="role" onchange="this.form.submit()"><?= select_options($roles, $row['role']) ?></select>
                    </form>
                <?php endif; ?>
            </td>
            <td><?= active_badge($row['is_active']) ?></td>
            <td><?= e(format_date($row['created_at'])) ?></td>
            <td class="actions">
                <?php if (!$self): ?>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="status">
                        <input type="hidden" name="user_id" value="<?= (int) $row['user_id'] ?>">
                        <input type="hidden" name="is_active" value="<?= $row['is_active'] ? 0 : 1 ?>">
                        <button class="btn sm ghost"><?= $row['is_active'] ? 'Disable' : 'Enable' ?></button>
                    </form>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="user_id" value="<?= (int) $row['user_id'] ?>">
                        <?= confirm_button('Delete', 'Delete this user permanently?') ?>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php
render_pagination($users);
render_foot();
