<?php

/**
 * Renders one or more HTML pages into a single PDF document.
 *
 * $config = [
 *   'paper_size' => 'a4',        // or [width_pt, height_pt]
 *   'orientation' => 'portrait', // or 'landscape'
 *   'margins' => ['top' => 20, 'right' => 18, 'bottom' => 20, 'left' => 18], // mm
 *   'pages' => ['<html>...</html>', '<html>...</html>'],
 * ]
 *
 * Returns raw PDF bytes. Requires Dompdf in ext/vendor/. Install it during
 * development with `php core/cli/nimbly.php module:install pdf`, then deploy
 * the resulting Composer files and vendor directory with the application.
 */
function pdf_render_document(array $config): string
{
    $autoload_file = $GLOBALS['SYSTEM']['file_base'] . 'ext/vendor/autoload.php';
    if (!is_file($autoload_file)) {
        throw new Exception('Dompdf is unavailable. Run: php core/cli/nimbly.php module:install pdf');
    }

    require_once $autoload_file;
    if (!class_exists(\Dompdf\Dompdf::class) || !class_exists(\Dompdf\Options::class)) {
        throw new Exception('Dompdf is unavailable. Run: php core/cli/nimbly.php module:install pdf');
    }

    $font_cache_dir = $GLOBALS['SYSTEM']['file_base'] . 'ext/data/.pdf_font_cache/';
    if (!is_dir($font_cache_dir)) {
        mkdir($font_cache_dir, 0775, true);
    }

    $options = new \Dompdf\Options();
    $options->setFontDir($font_cache_dir);
    $options->setFontCache($font_cache_dir);
    $options->setChroot($GLOBALS['SYSTEM']['file_base']);
    $options->setIsRemoteEnabled(false);
    $options->setIsHtml5ParserEnabled(true);

    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->setPaper($config['paper_size'] ?? 'a4', $config['orientation'] ?? 'portrait');

    $dompdf->loadHtml(pdf_build_document_html($config));
    $dompdf->render();

    return $dompdf->output();
}

/**
 * Renders a PDF once and reuses it while the complete document config remains
 * unchanged. Because the rendered pages are part of the cache identity, callers
 * do not need to maintain separate modification timestamps or dependency lists.
 */
function pdf_render_cached_document(array $config): string
{
    $cache_dir = $GLOBALS['SYSTEM']['file_base'] . 'ext/data/.tmp/cache/pdf/';
    $cache_key = hash('sha256', serialize([
        'config' => $config,
        'renderer' => pdf_renderer_fingerprint(),
    ]));
    $cache_file = $cache_dir . $cache_key . '.pdf';
    $cached = is_file($cache_file) ? file_get_contents($cache_file) : false;
    if ($cached !== false) {
        return $cached;
    }

    $pdf_bytes = pdf_render_document($config);
    if (!is_dir($cache_dir)) {
        mkdir($cache_dir, 0775, true);
    }

    $temporary_file = tempnam($cache_dir, 'pdf-');
    if ($temporary_file !== false) {
        if (file_put_contents($temporary_file, $pdf_bytes, LOCK_EX) !== false) {
            rename($temporary_file, $cache_file);
        } else {
            unlink($temporary_file);
        }
    }

    return $pdf_bytes;
}

/**
 * Streams PDF bytes as a private browser download and ends the request.
 */
function pdf_send_download(string $pdf_bytes, string $filename): void
{
    $safe_filename = pdf_safe_download_filename($filename);

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $safe_filename . '"');
    header('Content-Length: ' . strlen($pdf_bytes));
    header('Cache-Control: private, no-store');
    echo $pdf_bytes;
}

function pdf_safe_download_filename(string $filename): string
{
    $safe_filename = trim(preg_replace('/[^a-zA-Z0-9_.-]+/', '-', $filename), '-');
    if ($safe_filename === '') {
        return 'document.pdf';
    }
    return str_ends_with(strtolower($safe_filename), '.pdf') ? $safe_filename : $safe_filename . '.pdf';
}

function pdf_renderer_fingerprint(): array
{
    $files = [
        __FILE__,
        dirname(__DIR__) . '/tpl/document.tpl',
        dirname(__DIR__) . '/tpl/page.tpl',
    ];

    return array_map(
        static fn(string $file): string => is_file($file) ? hash_file('sha256', $file) : '',
        $files
    );
}

function pdf_build_document_html(array $config): string
{
    $margins = $config['margins'] ?? [];
    $top = (float)($margins['top'] ?? 20);
    $right = (float)($margins['right'] ?? 18);
    $bottom = (float)($margins['bottom'] ?? 20);
    $left = (float)($margins['left'] ?? 18);

    $page_template = pdf_read_template('page.tpl');
    $body = '';
    foreach (($config['pages'] ?? []) as $ix => $page_html) {
        $body .= strtr($page_template, [
            '[#pdf.page_class#]' => $ix > 0 ? ' class="pdf-page-break"' : '',
            '[#pdf.page_body#]' => (string)$page_html,
        ]);
    }

    return strtr(pdf_read_template('document.tpl'), [
        '[#pdf.margins#]' => $top . 'mm ' . $right . 'mm ' . $bottom . 'mm ' . $left . 'mm',
        '[#pdf.body#]' => $body,
    ]);
}

function pdf_read_template(string $name): string
{
    $template = file_get_contents(dirname(__DIR__) . '/tpl/' . $name);
    if ($template === false) {
        throw new RuntimeException('PDF document template is unavailable');
    }
    return $template;
}
