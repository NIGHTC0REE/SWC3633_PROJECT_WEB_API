<?php

require_guest();

$response = [];

if (is_post()) {
    csrf_check();

    $response = api('POST', '/auth/login', [
        'email'    => $_POST['email'] ?? '',
        'password' => $_POST['password'] ?? '',
    ], false);

    if (api_ok($response)) {
        login_user($response['data']);
        redirect('dashboard.php');
    }
}

render_head('Sign in');

if (isset($_GET['expired'])) {
    echo '<div class="alert bad">Your session expired. Please sign in again.</div>';
}

if (isset($_GET['reset'])) {
    echo '<div class="alert ok">Password updated. You can sign in now.</div>';
}

show_error($response);
?>
<form method="post" class="form">
    <?= csrf_field() ?>
    <label>Email
        <input type="email" name="email" value="<?= e(input('email')) ?>" required autofocus>
        <?= field_error($response, 'email') ?>
    </label>
    <label>Password
        <input type="password" name="password" required>
    </label>
    <button class="btn block">Sign in</button>
</form>
<p class="auth-links"><a href="forgot.php">Forgot password?</a> · <a href="register.php">Create an account</a></p>
<p class="demo-note">Demo logins use <code>Password123!</code>: admin@boom.test, organiser@boom.test, customer@boom.test</p>
<?php render_foot();
