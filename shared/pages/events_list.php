<?php

$user = require_login();

$filters = [
    'q'        => input('q'),
    'sport'    => input('sport'),
    'upcoming' => 1,
    'page'     => input('page', 1),
    'limit'    => 9,
    'mine'     => $user['role'] === 'organiser' && input('scope') === 'mine' ? 1 : '',
];

$response = api_get('/events', $filters);

render_head('Events', 'Pickleball, Futsal and Badminton events');
?>
<form class="toolbar" method="get">
    <label>Search<input name="q" value="<?= e(input('q')) ?>" placeholder="Event or venue"></label>
    <label>Sport<select name="sport"><?= sport_options(input('sport'), 'All sports') ?></select></label>
    <?php if ($user['role'] === 'organiser'): ?>
        <label>Show<select name="scope"><?= select_options(['' => 'All published', 'mine' => 'My events'], input('scope')) ?></select></label>
    <?php endif; ?>
    <button class="btn">Filter</button>
</form>
<?php
if ($user['role'] === 'customer') {
    render_event_cards(api_rows($response), page_exists('event.php') ? 'event.php' : '', 'Book now');
} else {
    render_event_rows(api_rows($response));
}

render_pagination($response);
render_foot();
