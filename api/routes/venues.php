<?php

const SPORTS = 'Pickleball,Futsal,Badminton';

function handle_venues(string $method, array $segments): void
{
    if (!$segments) {
        match ($method) {
            'GET'   => venues_list(),
            'POST'  => venues_create(request_body()),
            default => method_not_allowed(),
        };
    }

    if (!ctype_digit($segments[0])) {
        return;
    }

    $venueId     = (int) $segments[0];
    $subresource = $segments[1] ?? '';

    match ($subresource) {
        ''         => venues_item($method, $venueId),
        'courts'   => venues_courts($method, $venueId, $segments[2] ?? ''),
        'schedule' => $method === 'GET' ? venues_schedule($venueId) : method_not_allowed(),
        default    => null,
    };
}

function find_venue(int $venueId): array
{
    $statement = db()->prepare(
        'SELECT v.*, u.full_name AS owner_name
         FROM venues v
         JOIN users u ON u.user_id = v.owner_id
         WHERE v.venue_id = ?'
    );
    $statement->execute([$venueId]);
    $venue = $statement->fetch();

    if (!$venue) {
        json_error(404, 'Venue not found');
    }

    return $venue;
}

function venues_list(): void
{
    $user       = optional_auth();
    $conditions = [];
    $params     = [];

    if (!$user || $user['role'] === 'customer') {
        $conditions[] = 'v.is_active = 1';
    } elseif ($user['role'] === 'organiser' && ($_GET['mine'] ?? '') === '1') {
        $conditions[] = 'v.owner_id = ?';
        $params[]     = $user['user_id'];
    } elseif ($user['role'] === 'organiser') {
        $conditions[] = '(v.is_active = 1 OR v.owner_id = ?)';
        $params[]     = $user['user_id'];
    }

    if (!empty($_GET['city'])) {
        $conditions[] = 'v.city = ?';
        $params[]     = $_GET['city'];
    }

    if (!empty($_GET['sport']) && in_array($_GET['sport'], explode(',', SPORTS), true)) {
        $conditions[] = 'EXISTS (SELECT 1 FROM courts c WHERE c.venue_id = v.venue_id AND c.sport = ?)';
        $params[]     = $_GET['sport'];
    }

    if (isset($_GET['is_active']) && $_GET['is_active'] !== '' && $user && $user['role'] !== 'customer') {
        $conditions[] = 'v.is_active = ?';
        $params[]     = (int) $_GET['is_active'];
    }

    if (!empty($_GET['q'])) {
        $conditions[] = search_condition(['v.venue_name', 'v.address', 'v.city'], $_GET['q'], $params);
    }

    paginate(
        "v.venue_id, v.owner_id, v.venue_name, v.address, v.city, v.capacity, v.contact_phone, v.is_active, v.created_at,
         u.full_name AS owner_name,
         (SELECT COUNT(*) FROM courts c WHERE c.venue_id = v.venue_id) AS court_count,
         (SELECT GROUP_CONCAT(DISTINCT c.sport ORDER BY c.sport) FROM courts c WHERE c.venue_id = v.venue_id) AS sports",
        'venues v JOIN users u ON u.user_id = v.owner_id',
        $conditions,
        $params,
        ['name' => 'v.venue_name', 'city' => 'v.city', 'capacity' => 'v.capacity', 'created_at' => 'v.created_at'],
        'v.venue_name ASC'
    );
}

function venue_rules(): array
{
    return [
        'venue_name'    => 'required|string|max:120',
        'address'       => 'required|string|max:255',
        'city'          => 'required|string|max:80',
        'capacity'      => 'required|int|min:1|max:100000',
        'contact_phone' => 'phone',
        'is_active'     => 'bool',
    ];
}

function venues_create(array $input): void
{
    $user = require_role('admin', 'organiser');
    $data = validate($input, venue_rules() + ['owner_id' => 'int']);

    $ownerId = $user['role'] === 'admin' ? ($data['owner_id'] ?? $user['user_id']) : $user['user_id'];

    db()->prepare(
        'INSERT INTO venues (owner_id, venue_name, address, city, capacity, contact_phone, is_active)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $ownerId,
        $data['venue_name'],
        $data['address'],
        $data['city'],
        $data['capacity'],
        $data['contact_phone'] ?? null,
        $data['is_active'] ?? 1,
    ]);

    $venueId = (int) db()->lastInsertId();
    audit((int) $user['user_id'], 'VENUE_CREATE', $data['venue_name']);

    json_ok(find_venue($venueId), 'Venue created', 201);
}

function venues_item(string $method, int $venueId): void
{
    match ($method) {
        'GET'           => venues_show($venueId),
        'PUT', 'PATCH'  => venues_update($venueId, request_body(), $method === 'PATCH'),
        'DELETE'        => venues_delete($venueId),
        default         => method_not_allowed(),
    };
}

function venues_show(int $venueId): void
{
    $user  = optional_auth();
    $venue = find_venue($venueId);

    if (!$venue['is_active'] && (!$user || ($user['role'] === 'customer'))) {
        json_error(404, 'Venue not found');
    }

    $venue['courts'] = venue_courts($venueId);

    json_ok($venue);
}

function venues_update(int $venueId, array $input, bool $partial): void
{
    $user  = require_role('admin', 'organiser');
    $venue = find_venue($venueId);

    require_owner_or_admin($user, (int) $venue['owner_id']);

    $data   = validate($input, venue_rules(), $partial);
    $merged = array_merge($venue, $data);

    db()->prepare(
        'UPDATE venues SET venue_name = ?, address = ?, city = ?, capacity = ?, contact_phone = ?, is_active = ?
         WHERE venue_id = ?'
    )->execute([
        $merged['venue_name'],
        $merged['address'],
        $merged['city'],
        $merged['capacity'],
        $merged['contact_phone'],
        $merged['is_active'],
        $venueId,
    ]);

    audit((int) $user['user_id'], 'VENUE_UPDATE', "venue $venueId");

    json_ok(find_venue($venueId), 'Venue updated');
}

function venues_delete(int $venueId): void
{
    $user  = require_role('admin', 'organiser');
    $venue = find_venue($venueId);

    require_owner_or_admin($user, (int) $venue['owner_id']);

    try {
        db()->prepare('DELETE FROM venues WHERE venue_id = ?')->execute([$venueId]);
    } catch (PDOException $exception) {
        json_error(409, 'Venue has events or bookings; deactivate it instead');
    }

    audit((int) $user['user_id'], 'VENUE_DELETE', $venue['venue_name']);

    json_ok(null, 'Venue deleted');
}

function venue_courts(int $venueId): array
{
    $statement = db()->prepare('SELECT * FROM courts WHERE venue_id = ? ORDER BY sport, court_name');
    $statement->execute([$venueId]);

    return $statement->fetchAll();
}

function find_court(int $venueId, int $courtId): array
{
    $statement = db()->prepare('SELECT * FROM courts WHERE court_id = ? AND venue_id = ?');
    $statement->execute([$courtId, $venueId]);
    $court = $statement->fetch();

    if (!$court) {
        json_error(404, 'Court not found');
    }

    return $court;
}

function court_rules(): array
{
    return [
        'sport'       => 'required|in:' . SPORTS,
        'court_name'  => 'required|string|max:60',
        'hourly_rate' => 'required|numeric|min:0|max:100000',
        'is_active'   => 'bool',
    ];
}

function venues_courts(string $method, int $venueId, string $courtSegment): void
{
    $venue = find_venue($venueId);

    if ($courtSegment === '') {
        match ($method) {
            'GET'   => json_ok(venue_courts($venueId)),
            'POST'  => courts_create($venue, request_body()),
            default => method_not_allowed(),
        };
    }

    if (!ctype_digit($courtSegment)) {
        return;
    }

    $courtId = (int) $courtSegment;

    match ($method) {
        'GET'          => json_ok(find_court($venueId, $courtId)),
        'PUT', 'PATCH' => courts_update($venue, $courtId, request_body(), $method === 'PATCH'),
        'DELETE'       => courts_delete($venue, $courtId),
        default        => method_not_allowed(),
    };
}

function courts_create(array $venue, array $input): void
{
    $user = require_role('admin', 'organiser');
    require_owner_or_admin($user, (int) $venue['owner_id']);

    $data = validate($input, court_rules());

    db()->prepare('INSERT INTO courts (venue_id, sport, court_name, hourly_rate, is_active) VALUES (?, ?, ?, ?, ?)')
        ->execute([$venue['venue_id'], $data['sport'], $data['court_name'], $data['hourly_rate'], $data['is_active'] ?? 1]);

    audit((int) $user['user_id'], 'COURT_CREATE', $data['court_name']);

    json_ok(find_court((int) $venue['venue_id'], (int) db()->lastInsertId()), 'Court created', 201);
}

function courts_update(array $venue, int $courtId, array $input, bool $partial): void
{
    $user = require_role('admin', 'organiser');
    require_owner_or_admin($user, (int) $venue['owner_id']);

    $court  = find_court((int) $venue['venue_id'], $courtId);
    $merged = array_merge($court, validate($input, court_rules(), $partial));

    db()->prepare('UPDATE courts SET sport = ?, court_name = ?, hourly_rate = ?, is_active = ? WHERE court_id = ?')
        ->execute([$merged['sport'], $merged['court_name'], $merged['hourly_rate'], $merged['is_active'], $courtId]);

    audit((int) $user['user_id'], 'COURT_UPDATE', "court $courtId");

    json_ok(find_court((int) $venue['venue_id'], $courtId), 'Court updated');
}

function courts_delete(array $venue, int $courtId): void
{
    $user = require_role('admin', 'organiser');
    require_owner_or_admin($user, (int) $venue['owner_id']);

    find_court((int) $venue['venue_id'], $courtId);

    try {
        db()->prepare('DELETE FROM courts WHERE court_id = ?')->execute([$courtId]);
    } catch (PDOException $exception) {
        json_error(409, 'Court has bookings; deactivate it instead');
    }

    audit((int) $user['user_id'], 'COURT_DELETE', "court $courtId");

    json_ok(null, 'Court deleted');
}

function venues_schedule(int $venueId): void
{
    $venue = find_venue($venueId);

    $input = validate($_GET, ['date' => 'date']);
    $date  = $input['date'] ?? date('Y-m-d');

    $events = db()->prepare(
        "SELECT event_id, title, sport, start_time, end_time, status
         FROM events
         WHERE venue_id = ? AND status IN ('published','completed') AND DATE(start_time) = ?
         ORDER BY start_time"
    );
    $events->execute([$venueId, $date]);

    $slots = db()->prepare(
        "SELECT b.court_id, b.slot_start, b.slot_end, b.status
         FROM bookings b
         JOIN courts c ON c.court_id = b.court_id
         WHERE c.venue_id = ? AND b.status IN ('pending','confirmed','completed') AND DATE(b.slot_start) = ?
         ORDER BY b.slot_start"
    );
    $slots->execute([$venueId, $date]);

    $booked = [];

    foreach ($slots->fetchAll() as $slot) {
        $booked[$slot['court_id']][] = ['slot_start' => $slot['slot_start'], 'slot_end' => $slot['slot_end'], 'status' => $slot['status']];
    }

    $courts = array_map(
        fn ($court) => $court + ['booked_slots' => $booked[$court['court_id']] ?? []],
        venue_courts($venueId)
    );

    json_ok([
        'venue_id' => $venueId,
        'venue'    => $venue['venue_name'],
        'date'     => $date,
        'events'   => $events->fetchAll(),
        'courts'   => $courts,
    ]);
}
