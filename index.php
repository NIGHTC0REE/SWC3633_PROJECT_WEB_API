<?php

$apps = [
    'Hafiq'        => 'Auth & User Profile Portal',
    'Akma'         => 'Organiser / Admin Management Dashboard',
    'Ariff'        => 'Customer Booking & Checkout Portal',
    'Putera_Aidil' => 'Customer Account & Ticketing Portal',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>BOOM+</title>
    <link rel="stylesheet" href="shared/assets/base.css">
    <link rel="stylesheet" href="shared/assets/auth.css">
</head>
<body class="theme-auth">
<div class="auth-shell">
    <section class="auth-art">
        <a class="brand" href="./"><span class="brand-word">BOOM</span><span class="brand-plus">+</span></a>
        <h2>Sport event ticketing &amp; venue booking</h2>
        <p>One REST API, four member frontends. Pick a portal to continue.</p>
        <ul><li>Pickleball</li><li>Futsal</li><li>Badminton</li></ul>
    </section>
    <section class="auth-panel">
        <div class="auth-card">
            <h1>Choose a portal</h1>
            <div class="form">
                <?php foreach ($apps as $folder => $label): ?>
                    <a class="btn block" href="<?= $folder ?>/"><?= htmlspecialchars(str_replace('_', ' ', $folder)) ?> · <?= htmlspecialchars($label) ?></a>
                <?php endforeach; ?>
                <a class="btn block ghost" href="api/">REST API root</a>
            </div>
        </div>
    </section>
</div>
</body>
</html>
