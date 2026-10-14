<?php

function brand_logo(string $href = 'dashboard.php'): string
{
    return '<a class="brand" href="' . e($href) . '"><span class="brand-word">BOOM</span><span class="brand-plus">+</span></a>';
}

function active_page(): string
{
    return basename($_SERVER['SCRIPT_NAME']);
}

function nav_has(string $file): bool
{
    foreach (APP_NAV[user()['role'] ?? ''] ?? [] as [, $target]) {
        if ($target === $file) {
            return true;
        }
    }

    return false;
}

function nav_links(array $items, string $class = ''): string
{
    $html = '';

    foreach ($items as [$label, $file]) {
        $active = active_page() === $file ? ' active' : '';
        $html  .= '<a class="' . trim($class . $active) . '" href="' . e($file) . '">' . e($label) . '</a>';
    }

    return $html;
}

function render_document_start(string $title, string $theme): void
{
    // Portals may opt into an extra, portal-specific skin by defining APP_THEME
    // (e.g. Ariff/app.php defines APP_THEME = 'ariff'). The skin stylesheet is
    // additive: it loads after the role theme and only affects that portal.
    $skin = defined('APP_THEME') ? APP_THEME : '';
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= e($title) ?> · BOOM+</title>
    <link rel="stylesheet" href="<?= SHARED_URL ?>/base.css">
    <link rel="stylesheet" href="<?= SHARED_URL ?>/<?= e($theme) ?>.css">
    <?php if ($skin !== ''): ?>
    <link rel="stylesheet" href="<?= SHARED_URL ?>/<?= e($skin) ?>.css">
    <?php endif; ?>
</head>
<body class="theme-<?= e($theme) ?><?= $skin !== '' ? ' app-' . e($skin) : '' ?>">
<?php
}

function render_head(string $title, string $subtitle = ''): void
{
    $user = user();

    if (!$user) {
        render_auth_head($title);
        return;
    }

    $role  = $user['role'];
    $items = APP_NAV[$role];

    render_document_start($title, $role);

    match ($role) {
        'admin'     => render_admin_head($user, $items, $title, $subtitle),
        'organiser' => render_organiser_head($user, $items, $title, $subtitle),
        default     => render_customer_head($user, $items, $title, $subtitle),
    };
}

function render_admin_head(array $user, array $items, string $title, string $subtitle): void
{
    ?>
<div class="shell">
    <aside class="sidebar">
        <?= brand_logo() ?>
        <p class="side-label">Administration</p>
        <nav><?= nav_links($items) ?></nav>
        <div class="side-user">
            <strong><?= e($user['full_name']) ?></strong>
            <small>System administrator</small>
            <a href="logout.php">Sign out</a>
        </div>
    </aside>
    <div class="content">
        <header class="topbar">
            <div><h1><?= e($title) ?></h1><?php if ($subtitle): ?><p><?= e($subtitle) ?></p><?php endif; ?></div>
            <span class="app-tag"><?= e(APP_NAME) ?> console</span>
        </header>
        <main class="page">
            <?php show_flash(); ?>
<?php
}

function render_organiser_head(array $user, array $items, string $title, string $subtitle): void
{
    ?>
<header class="topnav">
    <div class="topnav-inner">
        <?= brand_logo() ?>
        <nav><?= nav_links($items) ?></nav>
        <div class="topnav-user">
            <span><?= e($user['full_name']) ?></span>
            <a href="logout.php">Sign out</a>
        </div>
    </div>
</header>
<section class="banner">
    <div class="banner-inner">
        <small>Organiser workspace · <?= e(APP_NAME) ?></small>
        <h1><?= e($title) ?></h1>
        <?php if ($subtitle): ?><p><?= e($subtitle) ?></p><?php endif; ?>
    </div>
</section>
<main class="board">
    <?php show_flash(); ?>
<?php
}

function render_customer_head(array $user, array $items, string $title, string $subtitle): void
{
    ?>
<header class="pillbar">
    <div class="pillbar-inner">
        <?= brand_logo() ?>
        <nav><?= nav_links($items, 'pill') ?></nav>
        <div class="pill-user">
            <a class="pill ghost" href="profile.php">Hi, <?= e(strtok($user['full_name'], ' ')) ?></a>
            <a class="pill solid" href="logout.php">Sign out</a>
        </div>
    </div>
</header>
<main class="stage">
    <div class="stage-head">
        <h1><?= e($title) ?></h1>
        <?php if ($subtitle): ?><p><?= e($subtitle) ?></p><?php endif; ?>
    </div>
    <?php show_flash(); ?>
<?php
}

function render_auth_head(string $title): void
{
    render_document_start($title, 'auth');
    ?>
<div class="auth-shell">
    <section class="auth-art">
        <?= brand_logo('login.php') ?>
        <h2>Book the court.<br>Own the game.</h2>
        <p>Tickets and venues for Pickleball, Futsal and Badminton in one place.</p>
        <ul><li>Pickleball</li><li>Futsal</li><li>Badminton</li></ul>
    </section>
    <section class="auth-panel">
        <div class="auth-card">
            <h1><?= e($title) ?></h1>
            <?php show_flash(); ?>
<?php
}

function render_foot(): void
{
    $user = user();

    match ($user['role'] ?? 'guest') {
        'admin'     => print('</main></div></div>'),
        'organiser' => print('</main>'),
        'customer'  => print('</main><footer class="site-foot">BOOM+ · Pickleball · Futsal · Badminton</footer>'),
        default     => print('</div></section></div>'),
    };

    echo '</body></html>';
}
