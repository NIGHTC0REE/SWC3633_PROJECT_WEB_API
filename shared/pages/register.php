<?php

require_guest();

$response = [];

if (is_post()) {
    csrf_check();

    if (($_POST['password'] ?? '') !== ($_POST['confirm'] ?? '')) {
        $response = ['success' => false, 'errors' => ['confirm' => 'Passwords do not match']];
    } else {
        $response = api('POST', '/auth/register', [
            'full_name' => $_POST['full_name'] ?? '',
            'email'     => $_POST['email'] ?? '',
            'phone'     => $_POST['phone'] ?? '',
            'password'  => $_POST['password'] ?? '',
        ], false);
    }

    if (api_ok($response)) {
        flash('Account created. Please sign in.');
        redirect('login.php');
    }
}

render_head('Create account');
show_error($response);
?>
<form method="post" class="form">
    <?= csrf_field() ?>
    <label>Full name
        <input name="full_name" value="<?= e(input('full_name')) ?>" required>
        <?= field_error($response, 'full_name') ?>
    </label>
    <label>Email
        <input type="email" name="email" value="<?= e(input('email')) ?>" required>
        <?= field_error($response, 'email') ?>
    </label>
    <label>Phone (optional)
        <input name="phone" value="<?= e(input('phone')) ?>">
        <?= field_error($response, 'phone') ?>
    </label>
    <label>Password
        <input type="password" name="password" required>
        <?= field_error($response, 'password') ?>
    </label>
    <label>Confirm password
        <input type="password" name="confirm" required>
        <?= field_error($response, 'confirm') ?>
    </label>
    <button class="btn block">Register as customer</button>
</form>
<p class="auth-links">Already registered? <a href="login.php">Sign in</a></p>
<?php render_foot();
