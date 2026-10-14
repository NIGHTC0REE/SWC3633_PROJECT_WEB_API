<?php

function base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode(string $data): string
{
    return base64_decode(strtr($data, '-_', '+/'));
}

function jwt_sign(string $unsignedToken): string
{
    return base64url_encode(hash_hmac('sha256', $unsignedToken, JWT_SECRET, true));
}

function jwt_encode(array $claims): string
{
    $now = time();

    $header  = base64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload = base64url_encode(json_encode($claims + ['iat' => $now, 'exp' => $now + JWT_TTL]));

    $unsigned = $header . '.' . $payload;

    return $unsigned . '.' . jwt_sign($unsigned);
}

function jwt_decode(string $token): ?array
{
    $parts = explode('.', $token);

    if (count($parts) !== 3) {
        return null;
    }

    [$header, $payload, $signature] = $parts;

    $headerData = json_decode(base64url_decode($header), true);

    if (!is_array($headerData) || ($headerData['alg'] ?? '') !== 'HS256') {
        return null;
    }

    if (!hash_equals(jwt_sign($header . '.' . $payload), $signature)) {
        return null;
    }

    $claims = json_decode(base64url_decode($payload), true);

    if (!is_array($claims) || ($claims['exp'] ?? 0) < time()) {
        return null;
    }

    return $claims;
}
