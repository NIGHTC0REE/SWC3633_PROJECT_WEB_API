<?php

const ACTIVE_BOOKING_STATUSES = "'pending','confirmed'";

function release_booking(array $booking, string $newStatus = 'cancelled'): void
{
    $pdo = db();

    $pdo->prepare('UPDATE bookings SET status = ? WHERE booking_id = ?')
        ->execute([$newStatus, $booking['booking_id']]);

    if ($booking['booking_type'] === 'event_ticket' && $booking['ticket_type_id']) {
        $pdo->prepare('UPDATE ticket_types SET sold = GREATEST(0, sold - ?) WHERE ticket_type_id = ?')
            ->execute([$booking['quantity'], $booking['ticket_type_id']]);
    }

    $pdo->prepare("UPDATE payments SET status = IF(status = 'paid', 'refunded', 'failed') WHERE booking_id = ? AND status IN ('paid','pending')")
        ->execute([$booking['booking_id']]);
}

function cancel_event_bookings(int $eventId): int
{
    $statement = db()->prepare(
        'SELECT b.* FROM bookings b
         JOIN ticket_types t ON t.ticket_type_id = b.ticket_type_id
         WHERE t.event_id = ? AND b.status IN (' . ACTIVE_BOOKING_STATUSES . ')'
    );
    $statement->execute([$eventId]);
    $bookings = $statement->fetchAll();

    foreach ($bookings as $booking) {
        release_booking($booking);
    }

    return count($bookings);
}
