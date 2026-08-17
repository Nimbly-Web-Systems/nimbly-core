<?php

/**
 * Minimal, dependency-free .docx → plain text extraction. A .docx is a zip
 * archive containing word/document.xml (WordprocessingML); this reads just
 * enough of that XML to recover paragraph text — no Composer/PhpWord, this
 * project has neither.
 */
function docx_extract_text(string $path)
{
    if (!is_file($path)) {
        return false;
    }

    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        return false;
    }
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    if ($xml === false) {
        return false;
    }

    $doc = new DOMDocument();
    $prev_errors = libxml_use_internal_errors(true);
    $loaded = $doc->loadXML($xml);
    libxml_use_internal_errors($prev_errors);
    if (!$loaded) {
        return false;
    }

    $xpath = new DOMXPath($doc);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

    $paragraphs = [];
    foreach ($xpath->query('//w:p') as $p) {
        $text = '';
        foreach ($xpath->query('.//w:t', $p) as $t) {
            $text .= $t->textContent;
        }
        $paragraphs[] = $text;
    }

    return implode("\n", $paragraphs);
}
