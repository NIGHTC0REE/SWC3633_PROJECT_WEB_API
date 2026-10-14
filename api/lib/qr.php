<?php

function qr_payload(string $bookingRef): string
{
    return 'BOOMPLUS:' . $bookingRef;
}

function fetch_qr_data_uri(string $bookingRef): ?string
{
    $url = QR_SERVICE_URL . '?' . http_build_query([
        'size'   => '300x300',
        'margin' => 12,
        'format' => 'png',
        'data'   => qr_payload($bookingRef),
    ]);

    $context = stream_context_create(['http' => ['timeout' => QR_TIMEOUT, 'ignore_errors' => true]]);
    $image   = @file_get_contents($url, false, $context);

    if ($image === false || !str_starts_with($image, "\x89PNG")) {
        error_log('QR service unavailable for ' . $bookingRef);
        return null;
    }

    return 'data:image/png;base64,' . base64_encode($image);
}

function booking_qr(array $booking): ?string
{
    if ($booking['qr_code']) {
        return $booking['qr_code'];
    }

    if (!in_array($booking['status'], ['pending', 'confirmed'], true)) {
        return null;
    }

    $qr = fetch_qr_data_uri($booking['booking_ref']);

    if ($qr) {
        db()->prepare('UPDATE bookings SET qr_code = ? WHERE booking_id = ?')->execute([$qr, $booking['booking_id']]);
    }

    return $qr;
}
