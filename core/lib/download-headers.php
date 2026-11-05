<?php

/**
 * Returns the headers for serving a stored upload: its content type, and
 * a stored filename for PDFs, and a download instruction for active documents.
 */
function download_headers($type, $name = '') {
    $type = strtolower(trim(explode(';', (string)$type)[0]));
    if (!preg_match('~^[a-z0-9][a-z0-9.+_-]*/[a-z0-9][a-z0-9.+_-]*$~', $type)) {
        $type = 'application/octet-stream';
    }
    $headers = ['Content-Type: ' . $type];
    $attachment = download_headers_is_document($type);
    if ($attachment || $type === 'application/pdf') {
        $name = trim(preg_replace('~[\x00-\x1F\x7F"\\\\/]~', '', (string)$name));
        $ascii = preg_replace('~[^\x20-\x7E]~', '_', $name);
        $disposition = 'Content-Disposition: ' . ($attachment ? 'attachment' : 'inline');
        if ($name !== '') {
            $disposition .= '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($name);
        }
        if ($attachment || $name !== '') {
            $headers[] = $disposition;
        }
    }
    return $headers;
}

function download_headers_is_document($type) {
    return in_array($type, ['text/html', 'text/xml', 'application/xml'], true)
        || str_ends_with($type, '+xml');
}

function download_is_not_modified($modified, $if_modified_since) {
    $cached = strtotime((string)$if_modified_since);
    return $cached !== false && $modified <= $cached;
}

// Byte range for a single "Range: bytes=..." request header. Returns null to
// send the whole file, false when the range cannot be satisfied, or [start, end].
function download_range($size, $range_header) {
    if (!preg_match('/^bytes=(\d*)-(\d*)$/', trim((string)$range_header), $m) || ($m[1] === '' && $m[2] === '')) {
        return null;
    }
    if ($m[1] === '') {
        $start = max(0, $size - (int)$m[2]);
        $end = $size - 1;
    } else {
        $start = (int)$m[1];
        $end = $m[2] === '' ? $size - 1 : min((int)$m[2], $size - 1);
    }
    return $size > 0 && $start <= $end ? [$start, $end] : false;
}
