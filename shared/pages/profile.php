<?php

$user     = require_login();
$profile  = api_get('/users/me');
$response = [];
$section  = '';

if (is_post()) {
    csrf_check();
    $section = $_POST['section'] ?? '';

    if ($section === 'profile') {
        $response = api('PUT', '/users/me', [
            'full_name' => $_POST['full_name'] ?? '',
            'phone'     => $_POST['phone'] ?? '',
        ]);

        if (api_ok($response)) {
            $_SESSION['user']['full_name'] = trim($_POST['full_name']);
            flash('Profile updated');
            redirect('profile.php');
        }
    }

    if ($section === 'password') {
        if (($_POST['new_password'] ?? '') !== ($_POST['confirm'] ?? '')) {
            $response = ['success' => false, 'errors' => ['confirm' => 'Passwords do not match']];
        } else {
            $response = api('PUT', '/users/me/password', [
                'current_password' => $_POST['current_password'] ?? '',
                'new_password'     => $_POST['new_password'] ?? '',
            ]);
        }

        if (api_ok($response)) {
            flash('Password changed');
            redirect('profile.php');
        }
    }
}

$me = api_rows($profile) ?: $user;

$permissions = [
    'admin'     => ['Manage users and roles', 'Manage every venue and event', 'Oversee all bookings and system logs'],
    'organiser' => ['Create and manage own venues and courts', 'Create events, schedules and ticket capacity', 'View and manage bookings for own events'],
    'customer'  => ['Browse events and venues', 'Check seat availability in real time', 'Book, modify and cancel own bookings'],
];

render_head('My profile', 'Account details, role and password');
?>
<div class="grid two">
    <section class="card">
        <h2>Account details</h2>
        <?php if ($section === 'profile') show_error($response); ?>
        <form method="post" class="form">
            <?= csrf_field() ?>
            <input type="hidden" name="section" value="profile">
            <label>Email
                <input value="<?= e($me['email']) ?>" disabled>
            </label>
            <label>Full name
                <input name="full_name" value="<?= e($section === 'profile' ? input('full_name') : $me['full_name']) ?>" required>
                <?= $section === 'profile' ? field_error($response, 'full_name') : '' ?>
            </label>
            <label>Phone
                <input name="phone" value="<?= e($section === 'profile' ? input('phone') : $me['phone']) ?>">
                <?= $section === 'profile' ? field_error($response, 'phone') : '' ?>
            </label>
            <button class="btn">Save changes</button>
        </form>
    </section>

    <section class="card">
        <h2>Your role</h2>
        <p><span class="badge role-<?= e($me['role']) ?>"><?= e(ucfirst($me['role'])) ?></span></p>
        <ul class="checks">
            <?php foreach ($permissions[$me['role']] as $permission): ?>
                <li><?= e($permission) ?></li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="card">
        <h2>Change password</h2>
        <?php if ($section === 'password') show_error($response); ?>
        <form method="post" class="form">
            <?= csrf_field() ?>
            <input type="hidden" name="section" value="password">
            <label>Current password
                <input type="password" name="current_password" required>
            </label>
            <label>New password
                <input type="password" name="new_password" required>
                <?= $section === 'password' ? field_error($response, 'new_password') : '' ?>
            </label>
            <label>Confirm new password
                <input type="password" name="confirm" required>
                <?= $section === 'password' ? field_error($response, 'confirm') : '' ?>
            </label>
            <button class="btn">Update password</button>
        </form>
    </section>
</div>
<?php render_foot();
