<?php

function handle_system(string $method, array $segments): void
{
    if ($method !== 'GET') {
        method_not_allowed();
    }

    match ($segments[0] ?? '') {
        'health'       => system_health(),
        'stats'        => system_stats(require_auth()),
        'audit-logs'   => system_audit_logs(require_role('admin')),
        'request-logs' => system_request_logs(require_role('admin')),
        default        => null,
    };
}

function scalar(string $sql, array $params = [])
{
    $statement = db()->prepare($sql);
    $statement->execute($params);

    return $statement->fetchColumn();
}

function system_health(): void
{
    json_ok(['status' => 'ok', 'database' => scalar('SELECT 1') == 1 ? 'up' : 'down', 'time' => date('c')]);
}

function system_stats(array $user): void
{
    $stats = match ($user['role']) {
        'admin'     => admin_stats(),
        'organiser' => organiser_stats((int) $user['user_id']),
        default     => customer_stats((int) $user['user_id']),
    };

    json_ok($stats);
}

function admin_stats(): array
{
    $bookingsByStatus = db()->query('SELECT status, COUNT(*) AS total FROM bookings GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
    $usersByRole      = db()->query('SELECT role, COUNT(*) AS total FROM users GROUP BY role')->fetchAll(PDO::FETCH_KEY_PAIR);

    return [
        'users'             => (int) scalar('SELECT COUNT(*) FROM users'),
        'users_by_role'     => $usersByRole,
        'venues'            => (int) scalar('SELECT COUNT(*) FROM venues'),
        'events'            => (int) scalar('SELECT COUNT(*) FROM events'),
        'published_events'  => (int) scalar("SELECT COUNT(*) FROM events WHERE status = 'published'"),
        'bookings'          => (int) scalar('SELECT COUNT(*) FROM bookings'),
        'bookings_by_status'=> $bookingsByStatus,
        'revenue'           => (float) scalar("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'paid'"),
        'requests_today'    => (int) scalar('SELECT COUNT(*) FROM request_logs WHERE DATE(created_at) = CURDATE()'),
        'errors_today'      => (int) scalar('SELECT COUNT(*) FROM request_logs WHERE DATE(created_at) = CURDATE() AND status_code >= 500'),
    ];
}

function organiser_stats(int $userId): array
{
    $scope = '(e.organiser_id = ? OR cv.owner_id = ?)';
    $from  = 'bookings b
        LEFT JOIN ticket_types t ON t.ticket_type_id = b.ticket_type_id
        LEFT JOIN events e ON e.event_id = t.event_id
        LEFT JOIN courts c ON c.court_id = b.court_id
        LEFT JOIN venues cv ON cv.venue_id = c.venue_id
        LEFT JOIN payments p ON p.booking_id = b.booking_id';

    return [
        'venues'            => (int) scalar('SELECT COUNT(*) FROM venues WHERE owner_id = ?', [$userId]),
        'events'            => (int) scalar('SELECT COUNT(*) FROM events WHERE organiser_id = ?', [$userId]),
        'published_events'  => (int) scalar("SELECT COUNT(*) FROM events WHERE organiser_id = ? AND status = 'published'", [$userId]),
        'upcoming_events'   => (int) scalar("SELECT COUNT(*) FROM events WHERE organiser_id = ? AND status = 'published' AND start_time > NOW()", [$userId]),
        'tickets_sold'      => (int) scalar('SELECT COALESCE(SUM(t.sold), 0) FROM ticket_types t JOIN events e ON e.event_id = t.event_id WHERE e.organiser_id = ?', [$userId]),
        'bookings'          => (int) scalar("SELECT COUNT(*) FROM $from WHERE $scope", [$userId, $userId]),
        'pending_bookings'  => (int) scalar("SELECT COUNT(*) FROM $from WHERE $scope AND b.status = 'pending'", [$userId, $userId]),
        'revenue'           => (float) scalar("SELECT COALESCE(SUM(p.amount), 0) FROM $from WHERE $scope AND p.status = 'paid'", [$userId, $userId]),
    ];
}

function customer_stats(int $userId): array
{
    $startsAt = 'COALESCE(e.start_time, b.slot_start)';
    $from     = 'bookings b
        LEFT JOIN ticket_types t ON t.ticket_type_id = b.ticket_type_id
        LEFT JOIN events e ON e.event_id = t.event_id';

    return [
        'active_bookings' => (int) scalar("SELECT COUNT(*) FROM $from WHERE b.user_id = ? AND b.status IN ('pending','confirmed') AND $startsAt > NOW()", [$userId]),
        'total_bookings'  => (int) scalar('SELECT COUNT(*) FROM bookings WHERE user_id = ?', [$userId]),
        'cancelled'       => (int) scalar("SELECT COUNT(*) FROM bookings WHERE user_id = ? AND status = 'cancelled'", [$userId]),
        'total_spent'     => (float) scalar("SELECT COALESCE(SUM(p.amount), 0) FROM payments p JOIN bookings b ON b.booking_id = p.booking_id WHERE b.user_id = ? AND p.status = 'paid'", [$userId]),
        'open_events'     => (int) scalar("SELECT COUNT(*) FROM events WHERE status = 'published' AND start_time > NOW()"),
    ];
}

function system_audit_logs(array $admin): void
{
    $conditions = [];
    $params     = [];

    if (!empty($_GET['action'])) {
        $conditions[] = 'a.action = ?';
        $params[]     = $_GET['action'];
    }

    if (!empty($_GET['user_id']) && ctype_digit((string) $_GET['user_id'])) {
        $conditions[] = 'a.user_id = ?';
        $params[]     = (int) $_GET['user_id'];
    }

    if (!empty($_GET['q'])) {
        $conditions[] = search_condition(['a.action', 'a.detail', 'u.full_name'], $_GET['q'], $params);
    }

    paginate(
        'a.log_id, a.user_id, u.full_name AS user_name, a.action, a.detail, a.ip_address, a.created_at',
        'audit_logs a LEFT JOIN users u ON u.user_id = a.user_id',
        $conditions,
        $params,
        ['created_at' => 'a.created_at', 'action' => 'a.action'],
        'a.log_id DESC'
    );
}

function system_request_logs(array $admin): void
{
    $conditions = [];
    $params     = [];

    if (!empty($_GET['method'])) {
        $conditions[] = 'r.method = ?';
        $params[]     = strtoupper($_GET['method']);
    }

    if (!empty($_GET['status_code']) && ctype_digit((string) $_GET['status_code'])) {
        $conditions[] = 'r.status_code = ?';
        $params[]     = (int) $_GET['status_code'];
    }

    if (!empty($_GET['q'])) {
        $conditions[] = search_condition(['r.path'], $_GET['q'], $params);
    }

    paginate(
        'r.request_id, r.user_id, r.method, r.path, r.status_code, r.duration_ms, r.ip_address, r.created_at',
        'request_logs r',
        $conditions,
        $params,
        ['created_at' => 'r.created_at', 'duration' => 'r.duration_ms', 'status' => 'r.status_code'],
        'r.request_id DESC'
    );
}
