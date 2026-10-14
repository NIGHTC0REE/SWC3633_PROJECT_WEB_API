<?php

function json_out(int $status, $data): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    exit;
}

function json_error(int $status, string $message, array $errors = []): void
{
    $response = ['success' => false, 'message' => $message];

    if ($errors) {
        $response['errors'] = $errors;
    }

    json_out($status, $response);
}

function json_ok($data, string $message = '', int $status = 200, array $meta = []): void
{
    $response = ['success' => true];

    if ($message !== '') {
        $response['message'] = $message;
    }

    $response['data'] = $data;

    if ($meta) {
        $response['meta'] = $meta;
    }

    json_out($status, $response);
}

function request_body(): array
{
    $raw = file_get_contents('php://input');

    if ($raw === '' || $raw === false) {
        return $_POST;
    }

    $data = json_decode($raw, true);

    if (!is_array($data)) {
        json_error(400, 'Request body must be valid JSON');
    }

    return $data;
}

function client_ip(): string
{
    $remote = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    if (in_array($remote, ['127.0.0.1', '::1'], true) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    }

    return $remote;
}

function method_not_allowed(): void
{
    json_error(405, 'Method not allowed');
}
