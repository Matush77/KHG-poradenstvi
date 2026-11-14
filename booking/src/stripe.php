<?php
/* Stripe over plain HTTPS (no SDK): form-encoded requests, JSON answers. */
declare(strict_types=1);

/* Stripe wants nested fields as line_items[0][price_data][currency] and booleans as true/false. */
function stripe_form(array $data): string
{
    array_walk_recursive($data, function (&$v) { if (is_bool($v)) $v = $v ? 'true' : 'false'; });
    return http_build_query($data, '', '&');
}

function stripe(string $method, string $path, ?array $data = null, ?string $idempotency = null): array
{
    $headers = ['Authorization: Bearer ' . cfg()['stripe_sk'], 'Stripe-Version: 2024-06-20'];
    if ($idempotency !== null) $headers[] = 'Idempotency-Key: ' . $idempotency;
    $body = null;
    if ($data !== null && $method === 'GET') {
        $path .= (str_contains($path, '?') ? '&' : '?') . stripe_form($data);
    } elseif ($data !== null) {
        $body = stripe_form($data);
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    }
    return http($method, 'https://api.stripe.com/v1/' . $path, $headers, $body);
}

/* Webhook signature (Stripe-Signature: t=...,v1=...): HMAC-SHA256 of "t.payload" with the endpoint secret.
   Old timestamps are refused so a captured request can't be replayed. */
function stripe_signature_ok(string $payload, string $header, string $secret, int $tolerance = 300, ?int $now = null): bool
{
    if ($secret === '' || $header === '') return false;
    $t = null;
    $sigs = [];
    foreach (explode(',', $header) as $part) {
        [$k, $v] = array_pad(explode('=', trim($part), 2), 2, '');
        if ($k === 't') $t = ctype_digit($v) ? (int)$v : null;
        if ($k === 'v1') $sigs[] = $v;
    }
    if ($t === null || !$sigs || abs(($now ?? time()) - $t) > $tolerance) return false;
    $expected = hash_hmac('sha256', $t . '.' . $payload, $secret);
    foreach ($sigs as $s) if (hash_equals($expected, $s)) return true;
    return false;
}
