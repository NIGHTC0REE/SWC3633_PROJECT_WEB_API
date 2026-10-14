<?php

require_once __DIR__ . '/../lib/booking_service.php';
require_once __DIR__ . '/../lib/qr.php';
require_once __DIR__ . '/venues.php';

const BOOKING_SELECT = "b.booking_id, b.booking_ref, b.user_id, cu.full_name AS customer_name, cu.email AS customer_email,
    b.booking_type, b.ticket_type_id, b.court_id, b.quantity, b.slot_start, b.slot_end, b.total_amount, b.status,
    b.created_at, b.updated_at, t.type_name AS ticket_name, e.event_id, e.title AS event_title, e.start_time AS event_start,
    e.status AS event_status, c.court_name, c.sport AS court_sport,
    COALESCE(e.title, CONCAT(c.court_name, ' (court booking)')) AS item_title,
    COALESCE(ev.venue_name, cv.venue_name) AS venue_name,
    COALESCE(ev.city, cv.city) AS city,
    COALESCE(e.sport, c.sport) AS sport,
    COALESCE(e.start_time, b.slot_start) AS starts_at,
    p.method AS payment_method, p.status AS payment_status";

const BOOKING_FROM = "bookings b
    JOIN users cu ON cu.user_id = b.user_id
    LEFT JOIN ticket_types t ON t.ticket_type_id = b.ticket_type_id
    LEFT JOIN events e ON e.event_id = t.event_id
    LEFT JOIN venues ev ON ev.venue_id = e.venue_id
    LEFT JOIN courts c ON c.court_id = b.court_id
    LEFT JOIN venues cv ON cv.venue_id = c.venue_id
    LEFT JOIN payments p ON p.booking_id = b.booking_id";

const MAX_TICKETS_PER_BOOKING = 10;
const MAX_COURT_HOURS = 4;

const STATUS_TRANSITIONS = [
    'pending'   => ['confirmed', 'cancelled'],
    'confirmed' => ['completed', 'cancelled'],
    'completed' => [],
    'cancelled' => [],
];

function handle_bookings(string $method, array $segments): void
{
    if (!$segments) {
        match ($method) {
            'GET'   => bookings_list(),
            'POST'  => bookings_create(request_body()),
            default => method_not_allowed(),
        };
    }

    if (!ctype_digit($segments[0])) {
        return;
    }

    $bookingId   = (int) $segments[0];
    $subresource = $segments[1] ?? '';

    match (true) {
        $subresource === ''                                  => bookings_item($method, $bookingId),
        $subresource === 'cancel' && $method === 'POST'     => bookings_cancel($bookingId),
        $subresource === 'status' && in_array($method, ['PUT', 'PATCH'], true)
                                                              => bookings_set_status($bookingId, request_body()),
        default                                              => null,
    };
}

function booking_scope(array $user, array &$params): ?string
{
    if ($user['role'] === 'admin') {
        return null;
    }

    $params[] = $user['user_id'];

    if ($user['role'] === 'customer') {
        return 'b.user_id = ?';
    }

    $params[] = $user['user_id'];

    return '(e.organiser_id = ? OR cv.owner_id = ?)';
}

function find_booking(array $user, int $bookingId): array
{
    $params     = [$bookingId];
    $conditions = ['b.booking_id = ?'];

    if ($scope = booking_scope($user, $params)) {
        $conditions[] = $scope;
    }

    $statement = db()->prepare(
        'SELECT ' . BOOKING_SELECT . ', b.qr_code FROM ' . BOOKING_FROM . ' WHERE ' . implode(' AND ', $conditions)
    );
    $statement->execute($params);
    $booking = $statement->fetch();

    if (!$booking) {
        json_error(404, 'Booking not found');
    }

    return $booking;
}

function bookings_list(): void
{
    $user       = require_auth();
    $conditions = [];
    $params     = [];

    if ($scope = booking_scope($user, $params)) {
        $conditions[] = $scope;
    }

    $filters = validate($_GET, [
        'status'       => 'in:pending,confirmed,cancelled,completed',
        'booking_type' => 'in:event_ticket,court',
        'event_id'     => 'int',
        'user_id'      => 'int',
        'from'         => 'date',
        'to'           => 'date',
    ], true);

    $columns = ['status' => 'b.status', 'booking_type' => 'b.booking_type', 'event_id' => 'e.event_id'];

    if ($user['role'] !== 'customer') {
        $columns['user_id'] = 'b.user_id';
    }

    foreach ($columns as $key => $column) {
        if (isset($filters[$key])) {
            $conditions[] = "$column = ?";
            $params[]     = $filters[$key];
        }
    }

    if (isset($filters['from'])) {
        $conditions[] = 'DATE(b.created_at) >= ?';
        $params[]     = $filters['from'];
    }

    if (isset($filters['to'])) {
        $conditions[] = 'DATE(b.created_at) <= ?';
        $params[]     = $filters['to'];
    }

    if (($_GET['view'] ?? '') === 'active') {
        $conditions[] = "b.status IN ('pending','confirmed') AND COALESCE(e.start_time, b.slot_start) > NOW()";
    }

    if (($_GET['view'] ?? '') === 'history') {
        $conditions[] = "(b.status IN ('cancelled','completed') OR COALESCE(e.start_time, b.slot_start) <= NOW())";
    }

    if (!empty($_GET['q'])) {
        $conditions[] = search_condition(['b.booking_ref', 'cu.full_name', 'e.title', 'c.court_name'], $_GET['q'], $params);
    }

    paginate(
        BOOKING_SELECT,
        BOOKING_FROM,
        $conditions,
        $params,
        [
            'created_at' => 'b.created_at',
            'amount'     => 'b.total_amount',
            'status'     => 'b.status',
            'starts_at'  => 'starts_at',
            'ref'        => 'b.booking_ref',
        ],
        'b.created_at DESC'
    );
}

function new_booking_ref(): string
{
    return 'BM' . strtoupper(bin2hex(random_bytes(4)));
}

function booking_status_for_payment(string $method): array
{
    return $method === 'cash' ? ['pending', 'pending', null] : ['confirmed', 'paid', date('Y-m-d H:i:s')];
}

function insert_booking(int $userId, array $fields, string $paymentMethod): int
{
    [$bookingStatus, $paymentStatus, $paidAt] = booking_status_for_payment($paymentMethod);

    $pdo = db();

    $pdo->prepare(
        'INSERT INTO bookings (booking_ref, user_id, booking_type, ticket_type_id, court_id, quantity, slot_start, slot_end, total_amount, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        new_booking_ref(),
        $userId,
        $fields['booking_type'],
        $fields['ticket_type_id'] ?? null,
        $fields['court_id'] ?? null,
        $fields['quantity'] ?? 1,
        $fields['slot_start'] ?? null,
        $fields['slot_end'] ?? null,
        $fields['total_amount'],
        $bookingStatus,
    ]);

    $bookingId = (int) $pdo->lastInsertId();

    $pdo->prepare('INSERT INTO payments (booking_id, amount, method, status, paid_at) VALUES (?, ?, ?, ?, ?)')
        ->execute([$bookingId, $fields['total_amount'], $paymentMethod, $paymentStatus, $paidAt]);

    return $bookingId;
}

function bookings_create(array $input): void
{
    $user = require_role('customer');

    $data = validate($input, [
        'booking_type'   => 'required|in:event_ticket,court',
        'payment_method' => 'required|in:card,fpx,ewallet,cash',
        'ticket_type_id' => 'int|min:1',
        'quantity'       => 'int|min:1|max:' . MAX_TICKETS_PER_BOOKING,
        'court_id'       => 'int|min:1',
        'slot_start'     => 'datetime',
        'slot_end'       => 'datetime',
    ]);

    $pdo = db();
    $pdo->beginTransaction();

    $fields = $data['booking_type'] === 'event_ticket'
        ? prepare_ticket_booking($data)
        : prepare_court_booking($data);

    $bookingId = insert_booking((int) $user['user_id'], $fields, $data['payment_method']);

    $pdo->commit();

    audit((int) $user['user_id'], 'BOOKING_CREATE', "booking $bookingId");

    $booking = find_booking($user, $bookingId);
    $booking['qr_code'] = booking_qr($booking);

    json_ok($booking, 'Booking created', 201);
}

function prepare_ticket_booking(array $data): array
{
    if (empty($data['ticket_type_id'])) {
        json_error(422, 'Validation failed', ['ticket_type_id' => 'Required']);
    }

    $quantity = $data['quantity'] ?? 1;

    $statement = db()->prepare(
        'SELECT t.ticket_type_id, t.price, t.capacity, t.sold, e.status, e.start_time
         FROM ticket_types t
         JOIN events e ON e.event_id = t.event_id
         WHERE t.ticket_type_id = ?
         FOR UPDATE'
    );
    $statement->execute([$data['ticket_type_id']]);
    $ticket = $statement->fetch();

    if (!$ticket) {
        json_error(404, 'Ticket type not found');
    }

    assert_event_open($ticket);
    assert_seats($ticket, $quantity);

    db()->prepare('UPDATE ticket_types SET sold = sold + ? WHERE ticket_type_id = ?')
        ->execute([$quantity, $ticket['ticket_type_id']]);

    return [
        'booking_type'   => 'event_ticket',
        'ticket_type_id' => $ticket['ticket_type_id'],
        'quantity'       => $quantity,
        'total_amount'   => round($ticket['price'] * $quantity, 2),
    ];
}

function assert_event_open(array $ticket): void
{
    if ($ticket['status'] !== 'published') {
        json_error(409, 'This event is not open for booking');
    }

    if ($ticket['start_time'] <= date('Y-m-d H:i:s')) {
        json_error(409, 'This event has already started');
    }
}

function assert_seats(array $ticket, int $quantity, int $alreadyHeld = 0): void
{
    $remaining = $ticket['capacity'] - $ticket['sold'] + $alreadyHeld;

    if ($quantity > $remaining) {
        json_error(409, $remaining > 0 ? "Only $remaining ticket(s) left" : 'Sold out');
    }
}

function court_slot(array $data): array
{
    if (empty($data['slot_start']) || empty($data['slot_end'])) {
        json_error(422, 'Validation failed', ['slot_start' => 'slot_start and slot_end are required']);
    }

    $start = new DateTime($data['slot_start']);
    $end   = new DateTime($data['slot_end']);

    $minutes = ($end->getTimestamp() - $start->getTimestamp()) / 60;

    if ($start->format('i') !== '00' || $minutes < 60 || $minutes % 60 !== 0 || $minutes > MAX_COURT_HOURS * 60) {
        json_error(422, 'Validation failed', ['slot_end' => 'Slots start on the hour and last 1 to ' . MAX_COURT_HOURS . ' whole hours']);
    }

    if ($data['slot_start'] <= date('Y-m-d H:i:s')) {
        json_error(422, 'Validation failed', ['slot_start' => 'Must be in the future']);
    }

    return [$data['slot_start'], $data['slot_end'], $minutes / 60];
}

function assert_slot_free(int $courtId, int $venueId, string $start, string $end, int $ignoreBookingId = 0): void
{
    $statement = db()->prepare(
        "SELECT 1 FROM bookings
         WHERE court_id = ? AND booking_id <> ? AND status IN ('pending','confirmed','completed')
           AND slot_start < ? AND slot_end > ?
         LIMIT 1"
    );
    $statement->execute([$courtId, $ignoreBookingId, $end, $start]);

    if ($statement->fetchColumn()) {
        json_error(409, 'That court is already booked for the selected time');
    }

    $event = db()->prepare(
        "SELECT title FROM events WHERE venue_id = ? AND status = 'published' AND start_time < ? AND end_time > ? LIMIT 1"
    );
    $event->execute([$venueId, $end, $start]);

    if ($title = $event->fetchColumn()) {
        json_error(409, 'The venue is reserved for the event "' . $title . '" at that time');
    }
}

function prepare_court_booking(array $data): array
{
    if (empty($data['court_id'])) {
        json_error(422, 'Validation failed', ['court_id' => 'Required']);
    }

    $statement = db()->prepare(
        'SELECT c.court_id, c.venue_id, c.hourly_rate, c.is_active, v.is_active AS venue_active
         FROM courts c
         JOIN venues v ON v.venue_id = c.venue_id
         WHERE c.court_id = ?
         FOR UPDATE'
    );
    $statement->execute([$data['court_id']]);
    $court = $statement->fetch();

    if (!$court) {
        json_error(404, 'Court not found');
    }

    if (!$court['is_active'] || !$court['venue_active']) {
        json_error(409, 'This court is not available for booking');
    }

    [$start, $end, $hours] = court_slot($data);

    assert_slot_free((int) $court['court_id'], (int) $court['venue_id'], $start, $end);

    return [
        'booking_type' => 'court',
        'court_id'     => $court['court_id'],
        'slot_start'   => $start,
        'slot_end'     => $end,
        'total_amount' => round($court['hourly_rate'] * $hours, 2),
    ];
}

function bookings_item(string $method, int $bookingId): void
{
    match ($method) {
        'GET'          => bookings_show($bookingId),
        'PUT', 'PATCH' => bookings_update($bookingId, request_body()),
        'DELETE'       => bookings_cancel($bookingId),
        default        => method_not_allowed(),
    };
}

function bookings_show(int $bookingId): void
{
    $booking            = find_booking(require_auth(), $bookingId);
    $booking['qr_code'] = booking_qr($booking);

    json_ok($booking);
}

function assert_modifiable(array $booking): void
{
    if (!in_array($booking['status'], ['pending', 'confirmed'], true)) {
        json_error(409, 'A ' . $booking['status'] . ' booking cannot be changed');
    }

    if ($booking['starts_at'] <= date('Y-m-d H:i:s')) {
        json_error(409, 'This booking has already started');
    }
}

function bookings_update(int $bookingId, array $input): void
{
    $user    = require_role('customer', 'admin');
    $booking = find_booking($user, $bookingId);

    assert_modifiable($booking);

    $pdo = db();
    $pdo->beginTransaction();

    $total = $booking['booking_type'] === 'event_ticket'
        ? change_ticket_quantity($booking, $input)
        : change_court_slot($booking, $input);

    $pdo->prepare('UPDATE bookings SET total_amount = ? WHERE booking_id = ?')->execute([$total, $bookingId]);
    $pdo->prepare('UPDATE payments SET amount = ? WHERE booking_id = ?')->execute([$total, $bookingId]);

    $pdo->commit();

    audit((int) $user['user_id'], 'BOOKING_UPDATE', "booking $bookingId");

    json_ok(find_booking($user, $bookingId), 'Booking updated');
}

function change_ticket_quantity(array $booking, array $input): float
{
    $data = validate($input, ['quantity' => 'required|int|min:1|max:' . MAX_TICKETS_PER_BOOKING]);

    $statement = db()->prepare('SELECT price, capacity, sold FROM ticket_types WHERE ticket_type_id = ? FOR UPDATE');
    $statement->execute([$booking['ticket_type_id']]);
    $ticket = $statement->fetch();

    assert_seats($ticket, $data['quantity'], (int) $booking['quantity']);

    db()->prepare('UPDATE ticket_types SET sold = sold - ? + ? WHERE ticket_type_id = ?')
        ->execute([$booking['quantity'], $data['quantity'], $booking['ticket_type_id']]);

    db()->prepare('UPDATE bookings SET quantity = ? WHERE booking_id = ?')->execute([$data['quantity'], $booking['booking_id']]);

    return round($ticket['price'] * $data['quantity'], 2);
}

function change_court_slot(array $booking, array $input): float
{
    $data = validate($input, ['slot_start' => 'required|datetime', 'slot_end' => 'required|datetime']);

    $statement = db()->prepare('SELECT court_id, venue_id, hourly_rate FROM courts WHERE court_id = ? FOR UPDATE');
    $statement->execute([$booking['court_id']]);
    $court = $statement->fetch();

    [$start, $end, $hours] = court_slot($data);

    assert_slot_free((int) $court['court_id'], (int) $court['venue_id'], $start, $end, (int) $booking['booking_id']);

    db()->prepare('UPDATE bookings SET slot_start = ?, slot_end = ? WHERE booking_id = ?')
        ->execute([$start, $end, $booking['booking_id']]);

    return round($court['hourly_rate'] * $hours, 2);
}

function apply_status(array $booking, string $status, array $actor): void
{
    $pdo = db();
    $pdo->beginTransaction();

    if (in_array($status, ['cancelled'], true)) {
        release_booking($booking, $status);
    } else {
        $pdo->prepare('UPDATE bookings SET status = ? WHERE booking_id = ?')->execute([$status, $booking['booking_id']]);

        if ($status === 'confirmed') {
            $pdo->prepare("UPDATE payments SET status = 'paid', paid_at = NOW() WHERE booking_id = ? AND status = 'pending'")
                ->execute([$booking['booking_id']]);
        }
    }

    $pdo->commit();

    audit((int) $actor['user_id'], 'BOOKING_' . strtoupper($status), $booking['booking_ref']);
}

function bookings_cancel(int $bookingId): void
{
    $user    = require_auth();
    $booking = find_booking($user, $bookingId);

    assert_modifiable($booking);

    $fullBooking = db()->prepare('SELECT * FROM bookings WHERE booking_id = ?');
    $fullBooking->execute([$bookingId]);

    apply_status($fullBooking->fetch(), 'cancelled', $user);

    json_ok(find_booking($user, $bookingId), 'Booking cancelled');
}

function bookings_set_status(int $bookingId, array $input): void
{
    $user    = require_role('admin', 'organiser');
    $data    = validate($input, ['status' => 'required|in:pending,confirmed,cancelled,completed']);
    $booking = find_booking($user, $bookingId);

    if (!in_array($data['status'], STATUS_TRANSITIONS[$booking['status']], true)) {
        json_error(409, "Cannot change a {$booking['status']} booking to {$data['status']}");
    }

    $fullBooking = db()->prepare('SELECT * FROM bookings WHERE booking_id = ?');
    $fullBooking->execute([$bookingId]);

    apply_status($fullBooking->fetch(), $data['status'], $user);

    json_ok(find_booking($user, $bookingId), 'Booking status updated');
}
