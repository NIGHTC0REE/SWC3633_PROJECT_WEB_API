<?php

function show_flash(): void
{
    if ($flash = flash()) {
        echo '<div class="alert ' . e($flash[1]) . '">' . e($flash[0]) . '</div>';
    }
}

function show_error(array $response): void
{
    if ($response && empty($response['success']) && empty($response['errors']) && !empty($response['message'])) {
        echo '<div class="alert bad">' . e($response['message']) . '</div>';
    }
}

function field_error(array $response, string $field): string
{
    if (!isset($response['errors'][$field])) {
        return '';
    }

    return '<small class="err">' . e($response['errors'][$field]) . '</small>';
}

function respond_flash(array $response, string $success, string $redirectTo): void
{
    if (api_ok($response)) {
        flash($success);
    } else {
        flash($response['message'] ?? 'Request failed', 'bad');
    }

    redirect($redirectTo);
}

function status_badge(string $status): string
{
    return '<span class="badge ' . e($status) . '">' . e(ucfirst($status)) . '</span>';
}

function active_badge($isActive): string
{
    return $isActive ? '<span class="badge confirmed">Active</span>' : '<span class="badge cancelled">Inactive</span>';
}

function sport_tag(string $sport): string
{
    return '<span class="sport ' . e(strtolower($sport)) . '">' . e($sport) . '</span>';
}

function select_options(array $options, $selected = ''): string
{
    $html = '';

    foreach ($options as $value => $label) {
        $isSelected = (string) $value === (string) $selected ? ' selected' : '';
        $html .= '<option value="' . e($value) . '"' . $isSelected . '>' . e($label) . '</option>';
    }

    return $html;
}

function sport_options(string $selected = '', string $blank = ''): string
{
    $options = array_combine(SPORT_LIST, SPORT_LIST);

    if ($blank !== '') {
        $options = ['' => $blank] + $options;
    }

    return select_options($options, $selected);
}

function render_pagination(array $response): void
{
    $meta = $response['meta'] ?? null;

    if (!$meta || $meta['pages'] <= 1) {
        return;
    }

    echo '<nav class="pager">';

    if ($meta['page'] > 1) {
        echo '<a href="' . e(query_with(['page' => $meta['page'] - 1])) . '">Previous</a>';
    }

    echo '<span>Page ' . (int) $meta['page'] . ' of ' . (int) $meta['pages'] . ' · ' . (int) $meta['total'] . ' records</span>';

    if ($meta['page'] < $meta['pages']) {
        echo '<a href="' . e(query_with(['page' => $meta['page'] + 1])) . '">Next</a>';
    }

    echo '</nav>';
}

function render_empty(string $message): void
{
    echo '<div class="empty"><p>' . e($message) . '</p></div>';
}

function render_stat_cards(array $cards): void
{
    echo '<section class="stats">';

    foreach ($cards as [$label, $value, $tone]) {
        echo '<div class="stat ' . e($tone) . '"><span>' . e($label) . '</span><strong>' . e($value) . '</strong></div>';
    }

    echo '</section>';
}

function render_event_rows(array $events, ?callable $actions = null): void
{
    if (!$events) {
        render_empty('No events found.');
        return;
    }

    echo '<div class="table-wrap"><table><thead><tr><th>Event</th><th>Sport</th><th>Venue</th><th>When</th><th>Tickets left</th><th>From</th><th>Status</th>'
        . ($actions ? '<th></th>' : '') . '</tr></thead><tbody>';

    foreach ($events as $event) {
        echo '<tr><td><strong>' . e($event['title']) . '</strong></td>'
            . '<td>' . sport_tag($event['sport']) . '</td>'
            . '<td>' . e($event['venue_name']) . '<br><small>' . e($event['city']) . '</small></td>'
            . '<td>' . e(format_datetime($event['start_time'])) . '</td>'
            . '<td>' . (int) $event['tickets_left'] . ' / ' . (int) $event['total_capacity'] . '</td>'
            . '<td>' . ($event['min_price'] !== null ? e(money($event['min_price'])) : '-') . '</td>'
            . '<td>' . status_badge($event['status']) . '</td>'
            . ($actions ? '<td class="actions">' . $actions($event) . '</td>' : '')
            . '</tr>';
    }

    echo '</tbody></table></div>';
}

function render_booking_rows(array $bookings, bool $showCustomer = false, ?callable $actions = null): void
{
    if (!$bookings) {
        render_empty('No bookings found.');
        return;
    }

    echo '<div class="table-wrap"><table><thead><tr><th>Reference</th>' . ($showCustomer ? '<th>Customer</th>' : '')
        . '<th>Booking</th><th>When</th><th>Qty / Slot</th><th>Total</th><th>Payment</th><th>Status</th>'
        . ($actions ? '<th></th>' : '') . '</tr></thead><tbody>';

    foreach ($bookings as $booking) {
        $detail = $booking['booking_type'] === 'court'
            ? format_time($booking['slot_start']) . ' - ' . format_time($booking['slot_end'])
            : (int) $booking['quantity'] . ' × ' . $booking['ticket_name'];

        echo '<tr><td><code>' . e($booking['booking_ref']) . '</code></td>'
            . ($showCustomer ? '<td>' . e($booking['customer_name']) . '<br><small>' . e($booking['customer_email']) . '</small></td>' : '')
            . '<td><strong>' . e($booking['item_title']) . '</strong><br><small>' . e($booking['venue_name']) . '</small></td>'
            . '<td>' . e(format_datetime($booking['starts_at'])) . '</td>'
            . '<td>' . e($detail) . '</td>'
            . '<td>' . e(money($booking['total_amount'])) . '</td>'
            . '<td>' . status_badge($booking['payment_status'] ?? 'pending') . '</td>'
            . '<td>' . status_badge($booking['status']) . '</td>'
            . ($actions ? '<td class="actions">' . $actions($booking) . '</td>' : '')
            . '</tr>';
    }

    echo '</tbody></table></div>';
}

function render_ticket(array $booking): void
{
    ?>
    <article class="ticket">
        <div class="ticket-main">
            <span class="ticket-label">BOOM+ e-ticket</span>
            <h2><?= e($booking['item_title']) ?></h2>
            <p><?= sport_tag($booking['sport'] ?? '') ?> <?= status_badge($booking['status']) ?></p>
            <dl>
                <dt>Venue</dt><dd><?= e($booking['venue_name']) ?>, <?= e($booking['city']) ?></dd>
                <dt>When</dt><dd><?= e(format_datetime($booking['starts_at'])) ?></dd>
                <?php if ($booking['booking_type'] === 'court'): ?>
                    <dt>Slot</dt><dd><?= e(format_time($booking['slot_start'])) ?> - <?= e(format_time($booking['slot_end'])) ?></dd>
                <?php else: ?>
                    <dt>Admit</dt><dd><?= (int) $booking['quantity'] ?> × <?= e($booking['ticket_name']) ?></dd>
                <?php endif; ?>
                <dt>Holder</dt><dd><?= e($booking['customer_name']) ?></dd>
                <dt>Total</dt><dd><?= e(money($booking['total_amount'])) ?> · <?= e(strtoupper((string) $booking['payment_method'])) ?> · <?= e($booking['payment_status']) ?></dd>
            </dl>
        </div>
        <div class="ticket-qr">
            <?php if (!empty($booking['qr_code'])): ?>
                <img src="<?= e($booking['qr_code']) ?>" alt="QR code for <?= e($booking['booking_ref']) ?>">
            <?php else: ?>
                <div class="qr-missing">QR unavailable</div>
            <?php endif; ?>
            <code><?= e($booking['booking_ref']) ?></code>
        </div>
    </article>
    <?php
}

function confirm_button(string $label, string $message, string $class = 'btn sm danger'): string
{
    return '<button class="' . e($class) . '" onclick="return confirm(\'' . e($message) . '\')">' . e($label) . '</button>';
}

function render_event_cards(array $events, string $detailPage = '', string $buttonLabel = 'View details'): void
{
    if (!$events) {
        render_empty('No events match your search.');
        return;
    }

    echo '<div class="event-grid">';

    foreach ($events as $event) {
        $left = (int) $event['tickets_left'];

        echo '<article class="event-card"><div class="event-cover ' . e(strtolower($event['sport'])) . '">'
            . '<span>' . e($event['sport']) . '</span><span>' . e(format_date($event['start_time'])) . '</span></div>'
            . '<div class="event-body"><h3>' . e($event['title']) . '</h3>'
            . '<div class="event-meta">' . e($event['venue_name']) . ', ' . e($event['city']) . '<br>'
            . e(format_datetime($event['start_time'])) . '</div>'
            . '<div class="event-meta">' . ($left > 0 ? $left . ' tickets left' : 'Sold out') . '</div>'
            . '<div class="event-foot"><span class="price">' . ($event['min_price'] !== null ? 'from ' . e(money($event['min_price'])) : 'Free') . '</span>';

        if ($detailPage !== '') {
            echo '<a class="btn sm" href="' . e($detailPage . '?id=' . $event['event_id']) . '">' . e($buttonLabel) . '</a>';
        }

        echo '</div></div></article>';
    }

    echo '</div>';
}

function booking_status_actions(array $booking, string $role): string
{
    $id   = (int) $booking['booking_id'];
    $html = '';

    if (!in_array($booking['status'], ['pending', 'confirmed'], true)) {
        return $html;
    }

    $button = fn (string $status, string $label, string $class) => '<form method="post">' . csrf_field()
        . '<input type="hidden" name="booking_id" value="' . $id . '"><input type="hidden" name="status" value="' . $status . '">'
        . '<button class="btn sm ' . $class . '">' . $label . '</button></form>';

    if ($role !== 'customer') {
        $html .= $booking['status'] === 'pending' ? $button('confirmed', 'Confirm', 'good') : $button('completed', 'Check in', 'good');
    }

    return $html . $button('cancelled', 'Cancel', 'danger');
}

function handle_booking_status_post(string $role): void
{
    if (!is_post() || !isset($_POST['booking_id'])) {
        return;
    }

    csrf_check();

    $id     = (int) $_POST['booking_id'];
    $status = $_POST['status'] ?? '';

    $response = $role === 'customer'
        ? api('POST', "/bookings/$id/cancel")
        : api('PATCH', "/bookings/$id/status", ['status' => $status]);

    respond_flash($response, 'Booking updated', $_SERVER['REQUEST_URI']);
}
