<?php

const USER_COLUMNS = 'user_id, full_name, email, phone, is_active, role, created_at';

function handle_users(string $method, array $segments): void
{
    $first = $segments[0] ?? '';

    if ($first === 'me') {
        users_me($method, $segments[1] ?? '');
    }

    $admin = require_role('admin');

    if (!$segments) {
        match ($method) {
            'GET'   => users_list(),
            'POST'  => users_create(request_body(), $admin),
            default => method_not_allowed(),
        };
    }

    if (ctype_digit($first)) {
        users_by_id($method, (int) $first, $segments[1] ?? '', $admin);
    }
}

function users_me(string $method, string $subresource): void
{
    $me    = require_auth();
    $input = request_body();

    if ($method === 'GET' && $subresource === '') {
        json_ok($me);
    }

    if (in_array($method, ['PUT', 'PATCH'], true) && $subresource === '') {
        users_update_profile($me, $input);
    }

    if ($method === 'PUT' && $subresource === 'password') {
        users_change_password($me, $input);
    }

    method_not_allowed();
}

function users_update_profile(array $me, array $input): void
{
    $data = validate($input, [
        'full_name' => 'string|max:100',
        'phone'     => 'phone',
    ], true);

    db()->prepare('UPDATE users SET full_name = ?, phone = ? WHERE user_id = ?')
        ->execute([
            $data['full_name'] ?? $me['full_name'],
            $data['phone'] ?? $me['phone'],
            $me['user_id'],
        ]);

    audit((int) $me['user_id'], 'PROFILE_UPDATE');

    json_ok(null, 'Profile updated');
}

function users_change_password(array $me, array $input): void
{
    $data = validate($input, [
        'current_password' => 'required|string',
        'new_password'     => 'required|string|password',
    ]);

    $statement = db()->prepare('SELECT password_hash FROM users WHERE user_id = ?');
    $statement->execute([$me['user_id']]);

    if (!password_verify($data['current_password'], $statement->fetchColumn())) {
        json_error(400, 'Current password is incorrect');
    }

    db()->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?')
        ->execute([password_hash($data['new_password'], PASSWORD_BCRYPT), $me['user_id']]);

    audit((int) $me['user_id'], 'PASSWORD_CHANGE');

    json_ok(null, 'Password changed');
}

function users_list(): void
{
    $conditions = [];
    $params     = [];

    if (!empty($_GET['role']) && in_array($_GET['role'], ['admin', 'organiser', 'customer'], true)) {
        $conditions[] = 'role = ?';
        $params[]     = $_GET['role'];
    }

    if (isset($_GET['is_active']) && $_GET['is_active'] !== '') {
        $conditions[] = 'is_active = ?';
        $params[]     = (int) $_GET['is_active'];
    }

    if (!empty($_GET['q'])) {
        $conditions[] = search_condition(['full_name', 'email'], $_GET['q'], $params);
    }

    paginate(
        USER_COLUMNS,
        'users',
        $conditions,
        $params,
        ['id' => 'user_id', 'name' => 'full_name', 'email' => 'email', 'role' => 'role', 'created_at' => 'created_at'],
        'user_id ASC'
    );
}

function users_create(array $input, array $admin): void
{
    $input['email'] = strtolower(trim((string) ($input['email'] ?? '')));

    $data = validate($input, [
        'full_name' => 'required|string|max:100',
        'email'     => 'required|email|max:150',
        'password'  => 'required|string|password',
        'phone'     => 'phone',
        'role'      => 'required|in:admin,organiser,customer',
    ]);

    if (email_exists($data['email'])) {
        json_error(409, 'Email already registered');
    }

    db()->prepare('INSERT INTO users (role, full_name, email, password_hash, phone) VALUES (?, ?, ?, ?, ?)')
        ->execute([
            $data['role'],
            $data['full_name'],
            $data['email'],
            password_hash($data['password'], PASSWORD_BCRYPT),
            $data['phone'] ?? null,
        ]);

    $userId = (int) db()->lastInsertId();
    audit((int) $admin['user_id'], 'USER_CREATE', "user $userId as {$data['role']}");

    json_ok(['user_id' => $userId], 'User created', 201);
}

function users_by_id(string $method, int $id, string $subresource, array $admin): void
{
    $input   = request_body();
    $adminId = (int) $admin['user_id'];

    if ($method === 'GET' && $subresource === '') {
        users_show($id);
    }

    if ($method === 'PUT' && $subresource === 'role') {
        users_set_role($id, $adminId, $input);
    }

    if ($method === 'PUT' && $subresource === 'status') {
        users_set_status($id, $adminId, $input);
    }

    if ($method === 'DELETE' && $subresource === '') {
        users_delete($id, $adminId);
    }

    method_not_allowed();
}

function users_show(int $id): void
{
    $statement = db()->prepare('SELECT ' . USER_COLUMNS . ' FROM users WHERE user_id = ?');
    $statement->execute([$id]);
    $user = $statement->fetch();

    if (!$user) {
        json_error(404, 'User not found');
    }

    json_ok($user);
}

function users_set_role(int $id, int $adminId, array $input): void
{
    $data = validate($input, ['role' => 'required|in:admin,organiser,customer']);

    if ($id === $adminId) {
        json_error(400, 'You cannot change your own role');
    }

    $statement = db()->prepare('UPDATE users SET role = ? WHERE user_id = ?');
    $statement->execute([$data['role'], $id]);

    if (!$statement->rowCount() && !users_exists($id)) {
        json_error(404, 'User not found');
    }

    audit($adminId, 'ROLE_CHANGE', "user $id -> {$data['role']}");

    json_ok(null, 'Role updated');
}

function users_exists(int $id): bool
{
    $statement = db()->prepare('SELECT 1 FROM users WHERE user_id = ?');
    $statement->execute([$id]);

    return (bool) $statement->fetchColumn();
}

function users_set_status(int $id, int $adminId, array $input): void
{
    $data = validate($input, ['is_active' => 'required|bool']);

    if ($id === $adminId) {
        json_error(400, 'You cannot disable yourself');
    }

    if (!users_exists($id)) {
        json_error(404, 'User not found');
    }

    db()->prepare('UPDATE users SET is_active = ? WHERE user_id = ?')->execute([$data['is_active'], $id]);

    audit($adminId, 'USER_STATUS', "user $id active={$data['is_active']}");

    json_ok(null, 'Status updated');
}

function users_delete(int $id, int $adminId): void
{
    if ($id === $adminId) {
        json_error(400, 'You cannot delete yourself');
    }

    if (!users_exists($id)) {
        json_error(404, 'User not found');
    }

    try {
        db()->prepare('DELETE FROM users WHERE user_id = ?')->execute([$id]);
    } catch (PDOException $exception) {
        json_error(409, 'User has venues, events or bookings; disable the account instead');
    }

    audit($adminId, 'USER_DELETE', "user $id");

    json_ok(null, 'User deleted');
}
