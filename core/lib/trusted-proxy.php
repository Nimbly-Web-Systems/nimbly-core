<?php

/**
 * Behind a load balancer the web server sees the balancer, not the visitor.
 * When the request comes straight from an address named in TRUSTED_PROXIES,
 * the scheme, port and visitor address are taken from the forwarded headers,
 * once, so every later reader of $_SERVER gets the visitor's request.
 * Without TRUSTED_PROXIES, or from any other address, the headers are ignored.
 *
 * A web server that puts the visitor in REMOTE_ADDR itself (mod_remoteip) names
 * the address it was called from in CONN_REMOTE_ADDR. That address is the one
 * checked then, and the visitor address stays the web server's: what is left in
 * X-Forwarded-For is whatever the client sent.
 */
function trusted_proxy_apply(): void
{
    $trusted = trusted_proxy_list((string)env('TRUSTED_PROXIES', ''));
    $connection = (string)($_SERVER['CONN_REMOTE_ADDR'] ?? '');
    $peer = $connection !== '' ? $connection : (string)($_SERVER['REMOTE_ADDR'] ?? '');
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

    if ($connection !== '') {
        return;
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

/** The mod_remoteip lines for Apache that go with a TRUSTED_PROXIES value; empty without a valid address. */
function trusted_proxy_apache_config(string $value): string
{
    $lines = [];
    foreach (trusted_proxy_list($value) as [$network, $bits]) {
        // Apache wants the start of a range: 10.1.2.3/8 becomes 10.0.0.0/8.
        $mask = str_pad(str_repeat("\xFF", intdiv($bits, 8)) . ($bits % 8 ? chr(0xFF << (8 - $bits % 8) & 0xFF) : ''), strlen($network), "\0");
        $lines[] = 'RemoteIPInternalProxy ' . inet_ntop($network & $mask) . '/' . $bits;
    }
    if (!$lines) {
        return '';
    }
    return "RemoteIPHeader X-Forwarded-For\n" . implode("\n", $lines) . "\n"
        . 'SetEnvIfExpr "%{CONN_REMOTE_ADDR} =~ /(.+)/" CONN_REMOTE_ADDR=$1' . "\n";
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
