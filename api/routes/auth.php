<?php

function handle_auth(string $method, array $segments): void
{
    $route = $method . ' ' . ($segments[0] ?? '');

    match ($route) {
        'POST register'        => auth_register(request_body()),
        'POST login'           => auth_login(request_body()),
        'GET me'               => json_ok(require_auth()),
        'POST forgot-password' => auth_forgot_password(request_body()),
        'POST reset-password'  => auth_reset_password(request_body()),
        default                => json_error(404, 'Unknown auth endpoint'),
    };
}

function email_exists(string $email): bool
{
    $statement = db()->prepare('SELECT 1 FROM users WHERE email = ?');
    $statement->execute([$email]);

    return (bool) $statement->fetch();
}

function auth_register(array $input): void
{
    $input['email'] = strtolower(trim((string) ($input['email'] ?? '')));

    $data = validate($input, [
        'full_name' => 'required|string|max:100',
        'email'     => 'required|email|max:150',
        'password'  => 'required|string|password',
        'phone'     => 'phone',
    ]);

    if (email_exists($data['email'])) {
        json_error(409, 'Email already registered');
    }

    db()->prepare("INSERT INTO users (role, full_name, email, password_hash, phone) VALUES ('customer', ?, ?, ?, ?)")
        ->execute([
            $data['full_name'],
            $data['email'],
            password_hash($data['password'], PASSWORD_BCRYPT),
            $data['phone'] ?? null,
        ]);

    $userId = (int) db()->lastInsertId();
    audit($userId, 'REGISTER', $data['email']);

    json_ok(['user_id' => $userId], 'Registered', 201);
}

function auth_login(array $input): void
{
    $data = validate($input, [
        'email'    => 'required|string',
        'password' => 'required|string',
    ]);

    $statement = db()->prepare(
        'SELECT user_id, full_name, email, password_hash, is_active, role FROM users WHERE email = ?'
    );
    $statement->execute([strtolower($data['email'])]);
    $user = $statement->fetch();

    if (!$user || !password_verify($data['password'], $user['password_hash'])) {
        audit($user['user_id'] ?? null, 'LOGIN_FAIL', $data['email']);
        json_error(401, 'Invalid email or password');
    }

    if (!$user['is_active']) {
        json_error(403, 'Account disabled');
    }

    $userId = (int) $user['user_id'];

    $token = jwt_encode([
        'sub'   => $userId,
        'role'  => $user['role'],
        'email' => $user['email'],
    ]);

    audit($userId, 'LOGIN');

    json_ok([
        'token'      => $token,
        'token_type' => 'Bearer',
        'expires_in' => JWT_TTL,
        'user'       => [
            'user_id'   => $userId,
            'full_name' => $user['full_name'],
            'email'     => $user['email'],
            'role'      => $user['role'],
        ],
    ]);
}

function auth_forgot_password(array $input): void
{
    $data = validate($input, ['email' => 'required|email']);

    $statement = db()->prepare('SELECT user_id FROM users WHERE email = ? AND is_active = 1');
    $statement->execute([strtolower($data['email'])]);
    $user = $statement->fetch();

    $response = [
        'success' => true,
        'message' => 'If that email exists, a reset link has been sent',
    ];

    if ($user) {
        $token = bin2hex(random_bytes(32));

        db()->prepare(
            'INSERT INTO password_resets (user_id, token_hash, expires_at)
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ' . (int) RESET_TTL . ' SECOND))'
        )->execute([$user['user_id'], hash('sha256', $token)]);

        audit((int) $user['user_id'], 'PASSWORD_RESET_REQUEST');

        $response['dev_reset_token'] = $token;
    }

    json_out(200, $response);
}

function auth_reset_password(array $input): void
{
    $data = validate($input, [
        'token'    => 'required|string',
        'password' => 'required|string|password',
    ]);

    $statement = db()->prepare(
        'SELECT reset_id, user_id FROM password_resets
         WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()'
    );
    $statement->execute([hash('sha256', $data['token'])]);
    $reset = $statement->fetch();

    if (!$reset) {
        json_error(400, 'Invalid or expired reset token');
    }

    $pdo = db();
    $pdo->beginTransaction();

    $pdo->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?')
        ->execute([password_hash($data['password'], PASSWORD_BCRYPT), $reset['user_id']]);

    $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE reset_id = ?')
        ->execute([$reset['reset_id']]);

    $pdo->commit();

    audit((int) $reset['user_id'], 'PASSWORD_RESET');

    json_ok(null, 'Password updated');
}
