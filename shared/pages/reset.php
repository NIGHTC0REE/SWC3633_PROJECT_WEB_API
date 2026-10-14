<?php

require_guest();

$token    = (string) input('token');
$response = [];

if (is_post()) {
    csrf_check();

    if (($_POST['password'] ?? '') !== ($_POST['confirm'] ?? '')) {
        $response = ['success' => false, 'errors' => ['confirm' => 'Passwords do not match']];
    } else {
        $response = api('POST', '/auth/reset-password', ['token' => $token, 'password' => $_POST['password'] ?? ''], false);
    }

    if (api_ok($response)) {
        redirect('login.php?reset=1');
    }
}

render_head('Reset password');
show_error($response);
?>
<form method="post" class="form">
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <label>New password
        <input type="password" name="password" required autofocus>
        <?= field_error($response, 'password') ?>
        <?= field_error($response, 'token') ?>
    </label>
    <label>Confirm new password
        <input type="password" name="confirm" required>
        <?= field_error($response, 'confirm') ?>
    </label>
    <button class="btn block">Update password</button>
</form>
<p class="auth-links"><a href="login.php">Back to sign in</a></p>
<?php render_foot();
