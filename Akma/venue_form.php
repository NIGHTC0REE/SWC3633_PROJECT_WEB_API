<?php

require __DIR__ . '/app.php';

$user    = require_login('admin', 'organiser');
$id      = (int) input('id', 0);
$venue   = $id ? api_rows(api_get("/venues/$id")) : [];
$errors  = [];

if ($id && !$venue) {
    flash('Venue not found', 'bad');
    redirect('venues.php');
}

if (is_post()) {
    csrf_check();

    $payload = [
        'venue_name'    => $_POST['venue_name'] ?? '',
        'address'       => $_POST['address'] ?? '',
        'city'          => $_POST['city'] ?? '',
        'capacity'      => $_POST['capacity'] ?? '',
        'contact_phone' => $_POST['contact_phone'] ?? '',
        'is_active'     => isset($_POST['is_active']) ? 1 : 0,
    ];

    if ($user['role'] === 'admin' && !$id && !empty($_POST['owner_id'])) {
        $payload['owner_id'] = (int) $_POST['owner_id'];
    }

    $errors = $id ? api('PUT', "/venues/$id", $payload) : api('POST', '/venues', $payload);

    if (api_ok($errors)) {
        flash($id ? 'Venue updated' : 'Venue created');
        redirect($id ? 'venues.php' : 'venue.php?id=' . $errors['data']['venue_id']);
    }
}

$value = fn (string $field, $default = '') => is_post() ? input($field, $default) : ($venue[$field] ?? $default);

$organisers = $user['role'] === 'admin' && !$id ? api_rows(api_get('/users', ['role' => 'organiser', 'limit' => 100])) : [];

render_head($id ? 'Edit venue' : 'New venue', 'Venue details and availability');
show_error($errors);
?>
<section class="card">
    <form method="post" class="form">
        <?= csrf_field() ?>
        <div class="form-row">
            <label>Venue name<input name="venue_name" value="<?= e($value('venue_name')) ?>" required><?= field_error($errors, 'venue_name') ?></label>
            <label>City<input name="city" value="<?= e($value('city')) ?>" required><?= field_error($errors, 'city') ?></label>
        </div>
        <label>Address<input name="address" value="<?= e($value('address')) ?>" required><?= field_error($errors, 'address') ?></label>
        <div class="form-row">
            <label>Capacity<input type="number" min="1" name="capacity" value="<?= e($value('capacity')) ?>" required><?= field_error($errors, 'capacity') ?></label>
            <label>Contact phone<input name="contact_phone" value="<?= e($value('contact_phone')) ?>"><?= field_error($errors, 'contact_phone') ?></label>
            <?php if ($organisers): ?>
                <label>Owner
                    <select name="owner_id"><?= select_options(array_column($organisers, 'full_name', 'user_id'), input('owner_id')) ?></select>
                </label>
            <?php endif; ?>
        </div>
        <label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="is_active" style="width:auto" <?= (is_post() ? isset($_POST['is_active']) : ($venue['is_active'] ?? 1)) ? 'checked' : '' ?>> Venue is open for bookings</label>
        <div><button class="btn">Save venue</button> <a class="btn ghost" href="venues.php">Cancel</a></div>
    </form>
</section>
<?php render_foot();
