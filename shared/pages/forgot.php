<?php

require_guest();

$response = [];
$resetUrl = '';

if (is_post()) {
    csrf_check();

    $response = api('POST', '/auth/forgot-password', ['email' => $_POST['email'] ?? ''], false);

    if (api_ok($response) && !empty($response['dev_reset_token'])) {
        $resetUrl = 'reset.php?token=' . urlencode($response['dev_reset_token']);
    }
}

render_head('Forgot password');
show_error($response);

if (api_ok($response)):
?>
<div class="alert ok"><?= e($response['message']) ?></div>
<?php if ($resetUrl): ?>
    <p>Email delivery is not configured on this local server, so use the link below to reset the password.</p>
    <a class="btn block" href="<?= e($resetUrl) ?>">Open reset link</a>
<?php endif; ?>
<?php else: ?>
<form method="post" class="form">
    <?= csrf_field() ?>
    <label>Account email
        <input type="email" name="email" value="<?= e(input('email')) ?>" required autofocus>
        <?= field_error($response, 'email') ?>
    </label>
    <button class="btn block">Send reset link</button>
</form>
<?php endif; ?>
<p class="auth-links"><a href="login.php">Back to sign in</a></p>
<?php render_foot();
