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

function pdf_build_document_html(array $config): string
{
    $margins = $config['margins'] ?? [];
    $top = (float)($margins['top'] ?? 20);
    $right = (float)($margins['right'] ?? 18);
    $bottom = (float)($margins['bottom'] ?? 20);
    $left = (float)($margins['left'] ?? 18);

    $pages = $config['pages'] ?? [];
    $body = '';
    foreach ($pages as $ix => $page_html) {
        $style = $ix > 0 ? ' style="page-break-before: always;"' : '';
        $body .= '<div' . $style . '>' . $page_html . '</div>';
    }

    return '<html><head><style>'
        . '@page { margin: ' . $top . 'mm ' . $right . 'mm ' . $bottom . 'mm ' . $left . 'mm; }'
        . '</style></head><body>' . $body . '</body></html>';
}
