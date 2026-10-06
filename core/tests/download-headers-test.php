<?php

// A stored upload is served with its own type; types a browser opens as a
// document are sent as a download. The setup template carries the baseline
// response headers.

require dirname(__DIR__) . '/lib/download-headers.php';

function download_headers_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

foreach (['application/pdf', 'image/jpeg', 'image/png', 'video/mp4', 'audio/wav', 'text/plain',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'] as $type) {
    download_headers_assert(
        download_headers($type, 'file.bin') === ['Content-Type: ' . $type],
        "{$type} is served as is, without a download instruction"
    );
}

foreach ([
    'text/html' => 'text/html',
    'TEXT/HTML; charset=utf-8' => 'text/html',
    'application/xhtml+xml' => 'application/xhtml+xml',
    'image/svg+xml' => 'image/svg+xml',
    'text/xml' => 'text/xml',
    'application/xml' => 'application/xml',
] as $stored => $sent) {
    $headers = download_headers($stored, 'page.html');
    download_headers_assert($headers[0] === 'Content-Type: ' . $sent, "{$stored} is sent as {$sent}");
    download_headers_assert(
        ($headers[1] ?? '') === 'Content-Disposition: attachment; filename="page.html"; filename*=UTF-8\'\'page.html',
        "{$stored} is sent as a download"
    );
}

foreach (['', 'text', 'textarea', "text/html\r\nX-Other: 1", null] as $stored) {
    download_headers_assert(
        download_headers($stored, 'x') === ['Content-Type: application/octet-stream'],
        'a missing or malformed type becomes application/octet-stream'
    );
}

$headers = download_headers('image/svg+xml', "a\"b\r\nc/d\\e é.svg");
download_headers_assert(
    $headers[1] === 'Content-Disposition: attachment; filename="abcde __.svg"; filename*=UTF-8\'\'abcde%20%C3%A9.svg',
    'the file name loses quotes, slashes and control characters'
);
download_headers_assert(
    download_headers('text/html')[1] === 'Content-Disposition: attachment',
    'no file name is sent when none is stored'
);

$template = file_get_contents(dirname(__DIR__) . '/cli/setup/htaccess.tpl');
foreach ([
    'Header always set X-Content-Type-Options "nosniff" "expr=-n %{CONTENT_TYPE}"',
    'Header always set Referrer-Policy "strict-origin-when-cross-origin"',
] as $line) {
    download_headers_assert(str_contains($template, $line), "setup template sends: {$line}");
}

echo "Download header tests passed\n";
