<?php

/**
 * Behind a load balancer the web server sees the balancer, not the visitor.
 * When the request comes straight from an address named in TRUSTED_PROXIES,
 * the scheme, port and visitor address are taken from the forwarded headers,
 * once, so every later reader of $_SERVER gets the visitor's request.
 * Without TRUSTED_PROXIES, or from any other address, the headers are ignored.
 */
function trusted_proxy_apply(): void
{
    $trusted = trusted_proxy_list((string)env('TRUSTED_PROXIES', ''));
    $peer = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    if (!$trusted || !trusted_proxy_matches($peer, $trusted)) {
        return;
    }

    $proto = strtolower(trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
    if ($proto === 'https' || $proto === 'http') {
        if ($proto === 'https') {
            $_SERVER['HTTPS'] = 'on';
        } else {
            unset($_SERVER['HTTPS']);
        }
        $port = trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PORT'] ?? ''))[0]);
        $_SERVER['SERVER_PORT'] = ctype_digit($port) ? $port : ($proto === 'https' ? '443' : '80');
    }

    // Read from the right: the last address was added by our own proxy, the first is whatever the client sent.
    $forwarded = array_reverse(array_map('trim', explode(',', (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''))));
    foreach ($forwarded as $address) {
        if (filter_var($address, FILTER_VALIDATE_IP) === false) {
            break;
        }
        $_SERVER['REMOTE_ADDR'] = $address;
        if (!trusted_proxy_matches($address, $trusted)) {
            break;
        }
    }
}

/** Addresses and ranges from a comma-separated list, as [packed address, prefix length]. */
function trusted_proxy_list(string $value): array
{
    $list = [];
    foreach (explode(',', $value) as $item) {
        [$address, $bits] = array_pad(explode('/', trim($item), 2), 2, null);
        $packed = @inet_pton($address);
        if ($packed === false) {
            continue;
        }
        $max = strlen($packed) * 8;
        if ($bits !== null && (!ctype_digit($bits) || (int)$bits > $max)) {
            continue;
        }
        $list[] = [$packed, $bits === null ? $max : (int)$bits];
    }
    return $list;
}

function trusted_proxy_matches(string $address, array $trusted): bool
{
    $packed = @inet_pton($address);
    if ($packed === false) {
        return false;
    }
    foreach ($trusted as [$network, $bits]) {
        if (strlen($network) !== strlen($packed)) {
            continue;
        }
        $bytes = intdiv($bits, 8);
        if (substr($packed, 0, $bytes) !== substr($network, 0, $bytes)) {
            continue;
        }
        $rest = $bits % 8;
        if ($rest === 0) {
            return true;
        }
        $mask = 0xFF << (8 - $rest) & 0xFF;
        if ((ord($packed[$bytes]) & $mask) === (ord($network[$bytes]) & $mask)) {
            return true;
        }
    }
    return false;
}
