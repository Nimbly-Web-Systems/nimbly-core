<?php

/**
 * Minimal, dependency-free .docx → plain text extraction. A .docx is a zip
 * archive containing word/document.xml (WordprocessingML); this reads just
 * enough of that XML to recover paragraph text — no Composer/PhpWord, this
 * project has neither.
 *
 * Hyperlinked runs are rendered as Markdown links (`[text](url)`) rather
 * than dropped — the extracted text is read by an LLM, not rendered
 * directly, so Markdown is enough for it to reconstruct a real `<a href>`
 * when the target field is HTML, or fall back to just the visible text for
 * a plain-text field (it's already instructed to return plain text only).
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
    $rels_xml = $zip->getFromName('word/_rels/document.xml.rels');
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

    $relationships = docx_extract_relationships($rels_xml);

    $xpath = new DOMXPath($doc);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    $xpath->registerNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');

    $paragraphs = [];
    foreach ($xpath->query('//w:p') as $p) {
        $text = '';
        // Plain text runs anywhere in the paragraph, except ones inside a
        // hyperlink (those are handled as one unit below) — this stays as
        // broad as the original `.//w:t` so runs nested in unrelated
        // wrapper elements (tracked changes, smart tags, etc.) still count.
        $nodes = $xpath->query('.//w:t[not(ancestor::w:hyperlink)] | .//w:hyperlink', $p);
        foreach ($nodes as $node) {
            if ($node->nodeName === 'w:hyperlink') {
                $link_text = '';
                foreach ($xpath->query('.//w:t', $node) as $t) {
                    $link_text .= $t->textContent;
                }
                if ($link_text === '') {
                    continue;
                }
                $rel_id = $node->getAttributeNS(
                    'http://schemas.openxmlformats.org/officeDocument/2006/relationships',
                    'id'
                );
                $url = $relationships[$rel_id] ?? '';
                $text .= $url !== '' ? "[{$link_text}]({$url})" : $link_text;
            } else {
                $text .= $node->textContent;
            }
        }
        $paragraphs[] = $text;
    }

    return implode("\n", $paragraphs);
}

/**
 * Reads word/_rels/document.xml.rels for hyperlink relationship targets.
 * Only external (real URL) targets are returned — internal ones (e.g. a
 * table-of-contents bookmark) aren't links worth preserving in extracted
 * text.
 */
function docx_extract_relationships($rels_xml): array
{
    if ($rels_xml === false || $rels_xml === '') {
        return [];
    }
    $doc = new DOMDocument();
    $prev_errors = libxml_use_internal_errors(true);
    $loaded = $doc->loadXML($rels_xml);
    libxml_use_internal_errors($prev_errors);
    if (!$loaded) {
        return [];
    }
    $result = [];
    foreach ($doc->getElementsByTagName('Relationship') as $rel) {
        if ($rel->getAttribute('TargetMode') !== 'External') {
            continue;
        }
        $id = $rel->getAttribute('Id');
        $target = $rel->getAttribute('Target');
        if ($id !== '' && $target !== '') {
            $result[$id] = $target;
        }
    }
    return $result;
}
