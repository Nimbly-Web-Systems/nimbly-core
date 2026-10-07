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

foreach (['image/jpeg', 'image/png', 'video/mp4', 'audio/wav', 'text/plain',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'] as $type) {
    download_headers_assert(
        download_headers($type, 'file.bin') === ['Content-Type: ' . $type],
        "{$type} is served as is, without a download instruction"
    );
}

download_headers_assert(
    download_headers('application/pdf', 'MENU 2026 Aug.pdf') === [
        'Content-Type: application/pdf',
        'Content-Disposition: inline; filename="MENU 2026 Aug.pdf"; filename*=UTF-8\'\'MENU%202026%20Aug.pdf',
    ],
    'PDFs keep browser viewing and expose the original filename'
);
download_headers_assert(
    download_headers('application/pdf') === ['Content-Type: application/pdf'],
    'PDFs without a stored filename retain browser viewing'
);
$pdf_headers = download_headers('APPLICATION/PDF; charset=binary', "menu\r\n/a\\b\" é.pdf");
download_headers_assert(
    $pdf_headers === [
        'Content-Type: application/pdf',
        'Content-Disposition: inline; filename="menuab __.pdf"; filename*=UTF-8\'\'menuab%20%C3%A9.pdf',
    ],
    'PDF filename headers normalize the type and remove unsafe characters'
);

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

$modified = strtotime('2026-09-15 09:12:21 UTC');
download_headers_assert(
    !download_is_not_modified($modified, 'Tue, 01 Sep 2026 00:00:00 GMT'),
    'a cache predating the stored file must receive the current file'
);
download_headers_assert(
    download_is_not_modified($modified, gmdate('D, d M Y H:i:s', $modified) . ' GMT'),
    'a cache matching the stored file modification time may be reused'
);
foreach (['', 'not a date'] as $cached_date) {
    download_headers_assert(
        !download_is_not_modified($modified, $cached_date),
        'missing and malformed cache dates must receive the file'
    );
}

$template = file_get_contents(dirname(__DIR__) . '/cli/setup/htaccess.tpl');
foreach ([
    'Header always set X-Content-Type-Options "nosniff" "expr=-n %{CONTENT_TYPE}"',
    'Header always set Referrer-Policy "strict-origin-when-cross-origin"',
] as $line) {
    download_headers_assert(str_contains($template, $line), "setup template sends: {$line}");
}

echo "Download header tests passed\n";
