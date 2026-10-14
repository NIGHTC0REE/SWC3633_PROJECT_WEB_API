<?php

require_once __DIR__ . '/../lib/booking_service.php';
require_once __DIR__ . '/venues.php';

const EVENT_SELECT = "e.event_id, e.organiser_id, e.venue_id, e.sport, e.title, e.description, e.start_time, e.end_time,
    e.status, e.created_at, v.venue_name, v.city, v.capacity AS venue_capacity, u.full_name AS organiser_name,
    (SELECT MIN(t.price) FROM ticket_types t WHERE t.event_id = e.event_id) AS min_price,
    (SELECT COALESCE(SUM(t.capacity), 0) FROM ticket_types t WHERE t.event_id = e.event_id) AS total_capacity,
    (SELECT COALESCE(SUM(t.capacity - t.sold), 0) FROM ticket_types t WHERE t.event_id = e.event_id) AS tickets_left";

const EVENT_FROM = 'events e JOIN venues v ON v.venue_id = e.venue_id JOIN users u ON u.user_id = e.organiser_id';

function handle_events(string $method, array $segments): void
{
    if (!$segments) {
        match ($method) {
            'GET'   => events_list(),
            'POST'  => events_create(request_body()),
            default => method_not_allowed(),
        };
    }

    if (!ctype_digit($segments[0])) {
        return;
    }

    $eventId     = (int) $segments[0];
    $subresource = $segments[1] ?? '';

    match ($subresource) {
        ''             => events_item($method, $eventId),
        'tickets'      => events_tickets($method, $eventId, $segments[2] ?? ''),
        'availability' => $method === 'GET' ? events_availability($eventId) : method_not_allowed(),
        default        => null,
    };
}

function find_event(int $eventId): array
{
    $statement = db()->prepare('SELECT ' . EVENT_SELECT . ' FROM ' . EVENT_FROM . ' WHERE e.event_id = ?');
    $statement->execute([$eventId]);
    $event = $statement->fetch();

    if (!$event) {
        json_error(404, 'Event not found');
    }

    return $event;
}

function event_visible_to(array $event, ?array $user): bool
{
    if ($event['status'] === 'published' || $event['status'] === 'completed') {
        return true;
    }

    return $user && ($user['role'] === 'admin' || (int) $user['user_id'] === (int) $event['organiser_id']);
}

function find_visible_event(int $eventId): array
{
    $event = find_event($eventId);

    if (!event_visible_to($event, optional_auth())) {
        json_error(404, 'Event not found');
    }

    return $event;
}

function event_tickets_for(int $eventId): array
{
    $statement = db()->prepare(
        'SELECT ticket_type_id, event_id, type_name, price, capacity, sold, capacity - sold AS remaining
         FROM ticket_types WHERE event_id = ? ORDER BY price, ticket_type_id'
    );
    $statement->execute([$eventId]);

    return $statement->fetchAll();
}

function events_list(): void
{
    $user       = optional_auth();
    $conditions = [];
    $params     = [];

    if (!$user || $user['role'] === 'customer') {
        $conditions[] = "e.status IN ('published','completed')";
    } elseif ($user['role'] === 'organiser') {
        $conditions[] = ($_GET['mine'] ?? '') === '1'
            ? 'e.organiser_id = ?'
            : "(e.status IN ('published','completed') OR e.organiser_id = ?)";
        $params[] = $user['user_id'];
    }

    $filters = validate($_GET, [
        'sport'    => 'in:' . SPORTS,
        'status'   => 'in:draft,published,cancelled,completed',
        'venue_id' => 'int',
        'city'     => 'string|max:80',
        'from'     => 'date',
        'to'       => 'date',
    ], true);

    foreach (['sport' => 'e.sport', 'status' => 'e.status', 'venue_id' => 'e.venue_id', 'city' => 'v.city'] as $key => $column) {
        if (isset($filters[$key])) {
            $conditions[] = "$column = ?";
            $params[]     = $filters[$key];
        }
    }

    if (isset($filters['from'])) {
        $conditions[] = 'DATE(e.start_time) >= ?';
        $params[]     = $filters['from'];
    }

    if (isset($filters['to'])) {
        $conditions[] = 'DATE(e.start_time) <= ?';
        $params[]     = $filters['to'];
    }

    if (($_GET['upcoming'] ?? '') === '1') {
        $conditions[] = 'e.start_time > NOW()';
    }

    if (($_GET['available'] ?? '') === '1') {
        $conditions[] = 'EXISTS (SELECT 1 FROM ticket_types t WHERE t.event_id = e.event_id AND t.sold < t.capacity)';
    }

    if (!empty($_GET['q'])) {
        $conditions[] = search_condition(['e.title', 'e.description', 'v.venue_name'], $_GET['q'], $params);
    }

    paginate(
        EVENT_SELECT,
        EVENT_FROM,
        $conditions,
        $params,
        ['start_time' => 'e.start_time', 'title' => 'e.title', 'price' => 'min_price', 'created_at' => 'e.created_at', 'city' => 'v.city'],
        'e.start_time ASC'
    );
}

function event_rules(): array
{
    return [
        'venue_id'    => 'required|int|min:1',
        'sport'       => 'required|in:' . SPORTS,
        'title'       => 'required|string|min:3|max:150',
        'description' => 'string|max:2000',
        'start_time'  => 'required|datetime',
        'end_time'    => 'required|datetime',
        'status'      => 'in:draft,published,cancelled,completed',
    ];
}

function ticket_rules(): array
{
    return [
        'type_name' => 'required|string|max:50',
        'price'     => 'required|numeric|min:0|max:100000',
        'capacity'  => 'required|int|min:1|max:100000',
    ];
}

function assert_event_slot(array $event, ?int $ignoreEventId): void
{
    if ($event['end_time'] <= $event['start_time']) {
        json_error(422, 'Validation failed', ['end_time' => 'Must be after start time']);
    }

    $statement = db()->prepare(
        "SELECT title FROM events
         WHERE venue_id = ? AND status IN ('draft','published') AND event_id <> ?
           AND start_time < ? AND end_time > ?
         LIMIT 1"
    );
    $statement->execute([$event['venue_id'], $ignoreEventId ?? 0, $event['end_time'], $event['start_time']]);

    if ($clash = $statement->fetchColumn()) {
        json_error(409, 'Venue is already scheduled for "' . $clash . '" at that time');
    }
}

function assert_active_venue(int $venueId): array
{
    $venue = find_venue($venueId);

    if (!$venue['is_active']) {
        json_error(422, 'Validation failed', ['venue_id' => 'Venue is not active']);
    }

    return $venue;
}

function assert_capacity_within_venue(int $venueCapacity, int $eventId, int $newCapacity, int $ignoreTicketId = 0): void
{
    $statement = db()->prepare('SELECT COALESCE(SUM(capacity), 0) FROM ticket_types WHERE event_id = ? AND ticket_type_id <> ?');
    $statement->execute([$eventId, $ignoreTicketId]);

    if ($statement->fetchColumn() + $newCapacity > $venueCapacity) {
        json_error(422, 'Validation failed', ['capacity' => "Total ticket capacity cannot exceed venue capacity ($venueCapacity)"]);
    }
}

function insert_ticket_type(int $eventId, array $ticket): void
{
    db()->prepare('INSERT INTO ticket_types (event_id, type_name, price, capacity) VALUES (?, ?, ?, ?)')
        ->execute([$eventId, $ticket['type_name'], $ticket['price'], $ticket['capacity']]);
}

function events_create(array $input): void
{
    $user = require_role('admin', 'organiser');
    $data = validate($input, event_rules() + ['organiser_id' => 'int']);

    $venue = assert_active_venue($data['venue_id']);

    if ($data['start_time'] <= date('Y-m-d H:i:s')) {
        json_error(422, 'Validation failed', ['start_time' => 'Must be in the future']);
    }

    $data['status'] = $data['status'] ?? 'draft';

    if (!in_array($data['status'], ['draft', 'published'], true)) {
        json_error(422, 'Validation failed', ['status' => 'New events must be draft or published']);
    }

    assert_event_slot($data, null);

    $tickets = [];

    foreach ((array) ($input['ticket_types'] ?? []) as $index => $ticket) {
        $tickets[] = validate((array) $ticket, ticket_rules());
    }

    if (array_sum(array_column($tickets, 'capacity')) > $venue['capacity']) {
        json_error(422, 'Validation failed', ['ticket_types' => 'Total capacity exceeds venue capacity (' . $venue['capacity'] . ')']);
    }

    $organiserId = $user['role'] === 'admin' ? ($data['organiser_id'] ?? $user['user_id']) : $user['user_id'];

    $pdo = db();
    $pdo->beginTransaction();

    $pdo->prepare(
        'INSERT INTO events (organiser_id, venue_id, sport, title, description, start_time, end_time, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $organiserId,
        $data['venue_id'],
        $data['sport'],
        $data['title'],
        $data['description'] ?? null,
        $data['start_time'],
        $data['end_time'],
        $data['status'],
    ]);

    $eventId = (int) $pdo->lastInsertId();

    foreach ($tickets as $ticket) {
        insert_ticket_type($eventId, $ticket);
    }

    $pdo->commit();

    audit((int) $user['user_id'], 'EVENT_CREATE', $data['title']);

    $event                 = find_event($eventId);
    $event['ticket_types'] = event_tickets_for($eventId);

    json_ok($event, 'Event created', 201);
}

function events_item(string $method, int $eventId): void
{
    match ($method) {
        'GET'          => events_show($eventId),
        'PUT', 'PATCH' => events_update($eventId, request_body(), $method === 'PATCH'),
        'DELETE'       => events_delete($eventId),
        default        => method_not_allowed(),
    };
}

function events_show(int $eventId): void
{
    $event                 = find_visible_event($eventId);
    $event['ticket_types'] = event_tickets_for($eventId);

    json_ok($event);
}

function events_update(int $eventId, array $input, bool $partial): void
{
    $user  = require_role('admin', 'organiser');
    $event = find_event($eventId);

    require_owner_or_admin($user, (int) $event['organiser_id']);

    if (in_array($event['status'], ['cancelled', 'completed'], true)) {
        json_error(409, 'A ' . $event['status'] . ' event can no longer be edited');
    }

    $data   = validate($input, event_rules(), $partial);
    $merged = array_merge($event, $data);

    assert_active_venue((int) $merged['venue_id']);

    if ($merged['venue_id'] !== $event['venue_id'] || $merged['start_time'] !== $event['start_time'] || $merged['end_time'] !== $event['end_time']) {
        assert_event_slot($merged, $eventId);
    }

    $pdo = db();
    $pdo->beginTransaction();

    $pdo->prepare(
        'UPDATE events SET venue_id = ?, sport = ?, title = ?, description = ?, start_time = ?, end_time = ?, status = ?
         WHERE event_id = ?'
    )->execute([
        $merged['venue_id'],
        $merged['sport'],
        $merged['title'],
        $merged['description'],
        $merged['start_time'],
        $merged['end_time'],
        $merged['status'],
        $eventId,
    ]);

    $cancelled = $merged['status'] === 'cancelled' ? cancel_event_bookings($eventId) : 0;

    $pdo->commit();

    audit((int) $user['user_id'], 'EVENT_UPDATE', "event $eventId status {$merged['status']}");

    $updated                 = find_event($eventId);
    $updated['ticket_types'] = event_tickets_for($eventId);
    $updated['bookings_cancelled'] = $cancelled;

    json_ok($updated, 'Event updated');
}

function events_delete(int $eventId): void
{
    $user  = require_role('admin', 'organiser');
    $event = find_event($eventId);

    require_owner_or_admin($user, (int) $event['organiser_id']);

    try {
        db()->prepare('DELETE FROM events WHERE event_id = ?')->execute([$eventId]);
    } catch (PDOException $exception) {
        json_error(409, 'Event has bookings; cancel it instead of deleting');
    }

    audit((int) $user['user_id'], 'EVENT_DELETE', $event['title']);

    json_ok(null, 'Event deleted');
}

function find_ticket(int $eventId, int $ticketId): array
{
    $statement = db()->prepare(
        'SELECT ticket_type_id, event_id, type_name, price, capacity, sold, capacity - sold AS remaining
         FROM ticket_types WHERE ticket_type_id = ? AND event_id = ?'
    );
    $statement->execute([$ticketId, $eventId]);
    $ticket = $statement->fetch();

    if (!$ticket) {
        json_error(404, 'Ticket type not found');
    }

    return $ticket;
}

function events_tickets(string $method, int $eventId, string $ticketSegment): void
{
    $event = find_event($eventId);

    if ($ticketSegment === '') {
        match ($method) {
            'GET'   => json_ok(event_tickets_for($eventId)),
            'POST'  => tickets_create($event, request_body()),
            default => method_not_allowed(),
        };
    }

    if (!ctype_digit($ticketSegment)) {
        return;
    }

    $ticketId = (int) $ticketSegment;

    match ($method) {
        'GET'          => json_ok(find_ticket($eventId, $ticketId)),
        'PUT', 'PATCH' => tickets_update($event, $ticketId, request_body(), $method === 'PATCH'),
        'DELETE'       => tickets_delete($event, $ticketId),
        default        => method_not_allowed(),
    };
}

function tickets_create(array $event, array $input): void
{
    $user = require_role('admin', 'organiser');
    require_owner_or_admin($user, (int) $event['organiser_id']);

    $data = validate($input, ticket_rules());

    assert_capacity_within_venue((int) $event['venue_capacity'], (int) $event['event_id'], $data['capacity']);

    try {
        insert_ticket_type((int) $event['event_id'], $data);
    } catch (PDOException $exception) {
        json_error(409, 'A ticket type with that name already exists for this event');
    }

    audit((int) $user['user_id'], 'TICKET_CREATE', $data['type_name']);

    json_ok(find_ticket((int) $event['event_id'], (int) db()->lastInsertId()), 'Ticket type created', 201);
}

function tickets_update(array $event, int $ticketId, array $input, bool $partial): void
{
    $user = require_role('admin', 'organiser');
    require_owner_or_admin($user, (int) $event['organiser_id']);

    $ticket = find_ticket((int) $event['event_id'], $ticketId);
    $merged = array_merge($ticket, validate($input, ticket_rules(), $partial));

    if ($merged['capacity'] < $ticket['sold']) {
        json_error(422, 'Validation failed', ['capacity' => 'Cannot be lower than tickets already sold (' . $ticket['sold'] . ')']);
    }

    assert_capacity_within_venue((int) $event['venue_capacity'], (int) $event['event_id'], (int) $merged['capacity'], $ticketId);

    try {
        db()->prepare('UPDATE ticket_types SET type_name = ?, price = ?, capacity = ? WHERE ticket_type_id = ?')
            ->execute([$merged['type_name'], $merged['price'], $merged['capacity'], $ticketId]);
    } catch (PDOException $exception) {
        json_error(409, 'A ticket type with that name already exists for this event');
    }

    audit((int) $user['user_id'], 'TICKET_UPDATE', "ticket $ticketId");

    json_ok(find_ticket((int) $event['event_id'], $ticketId), 'Ticket type updated');
}

function tickets_delete(array $event, int $ticketId): void
{
    $user = require_role('admin', 'organiser');
    require_owner_or_admin($user, (int) $event['organiser_id']);

    find_ticket((int) $event['event_id'], $ticketId);

    try {
        db()->prepare('DELETE FROM ticket_types WHERE ticket_type_id = ?')->execute([$ticketId]);
    } catch (PDOException $exception) {
        json_error(409, 'Ticket type has bookings and cannot be deleted');
    }

    audit((int) $user['user_id'], 'TICKET_DELETE', "ticket $ticketId");

    json_ok(null, 'Ticket type deleted');
}

function events_availability(int $eventId): void
{
    $event   = find_visible_event($eventId);
    $tickets = event_tickets_for($eventId);

    $open = $event['status'] === 'published' && $event['start_time'] > date('Y-m-d H:i:s');

    json_ok([
        'event_id'     => $eventId,
        'title'        => $event['title'],
        'bookable'     => $open && array_sum(array_column($tickets, 'remaining')) > 0,
        'tickets_left' => (int) array_sum(array_column($tickets, 'remaining')),
        'ticket_types' => $tickets,
    ]);
}
