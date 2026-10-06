<?php

/**
 * Returns the headers for serving a stored upload: its content type, and
 * a download instruction for types a browser would open as a document.
 */
function download_headers($type, $name = '') {
    $type = strtolower(trim(explode(';', (string)$type)[0]));
    if (!preg_match('~^[a-z0-9][a-z0-9.+_-]*/[a-z0-9][a-z0-9.+_-]*$~', $type)) {
        $type = 'application/octet-stream';
    }
    $headers = ['Content-Type: ' . $type];
    if (download_headers_is_document($type)) {
        $name = trim(preg_replace('~[\x00-\x1F\x7F"\\\\/]~', '', (string)$name));
        $ascii = preg_replace('~[^\x20-\x7E]~', '_', $name);
        $disposition = 'Content-Disposition: attachment';
        if ($name !== '') {
            $disposition .= '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($name);
        }
        $headers[] = $disposition;
    }
    return $headers;
}

function download_headers_is_document($type) {
    return in_array($type, ['text/html', 'text/xml', 'application/xml'], true)
        || str_ends_with($type, '+xml');
}
