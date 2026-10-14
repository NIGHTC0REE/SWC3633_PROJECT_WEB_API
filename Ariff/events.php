<?php

require __DIR__ . '/app.php';

$user = require_login();

$response = api_get('/events', [
    'q'         => input('q'),
    'sport'     => input('sport'),
    'city'      => input('city'),
    'from'      => input('from'),
    'available' => input('available'),
    'upcoming'  => 1,
    'sort'      => input('sort', 'start_time'),
    'page'      => input('page', 1),
    'limit'     => 9,
]);

$cities = array_unique(array_column(api_rows(api_get('/venues', ['limit' => 100])), 'city'));
sort($cities);

render_head('Browse events', 'Find a match, clinic or tournament and book your seat');
show_error($response);
?>
<form class="toolbar card" method="get">
    <label>Search<input name="q" value="<?= e(input('q')) ?>" placeholder="Event, venue..."></label>
    <label>Sport<select name="sport"><?= sport_options(input('sport'), 'All sports') ?></select></label>
    <label>City<select name="city"><?= select_options(['' => 'All cities'] + array_combine($cities, $cities), input('city')) ?></select></label>
    <label>From date<input type="date" name="from" value="<?= e(input('from')) ?>"></label>
    <label>Sort<select name="sort"><?= select_options(['start_time' => 'Soonest', 'price' => 'Cheapest', 'title' => 'Title A-Z'], input('sort', 'start_time')) ?></select></label>
    <label style="flex:0 0 auto;display:flex;gap:6px;align-items:center;padding-bottom:10px"><input type="checkbox" name="available" value="1" style="width:auto" <?= input('available') ? 'checked' : '' ?>> Seats left only</label>
    <button class="btn">Search</button>
</form>
<?php
if ($user['role'] === 'customer') {
    render_event_cards(api_rows($response), 'event.php', 'Book now');
} else {
    render_event_rows(api_rows($response), fn ($event) => '<a class="btn sm" href="event.php?id=' . (int) $event['event_id'] . '">Details</a>');
}

render_pagination($response);
render_foot();
