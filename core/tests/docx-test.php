<?php

require_once __DIR__ . '/../lib/docx.php';

// Hyperlinked runs in a .docx must survive extraction as Markdown links
// ([text](url)) — the extracted text feeds an LLM (see
// core/modules/api/lib/openai-import-document.php), which is instructed to
// return real <a href> tags for HTML fields; without this, the source text
// carried no URL for it to reconstruct from and links were silently
// dropped, even though the AI prompt already allowed <a> in its output.
function docx_test_build_fixture(string $path): void
{
    $doc_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" '
        . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<w:body>'
        . '<w:p><w:r><w:t>Visit </w:t></w:r>'
        . '<w:hyperlink r:id="rId1"><w:r><w:t>Amsterdam</w:t></w:r></w:hyperlink>'
        . '<w:r><w:t> for the canals.</w:t></w:r></w:p>'
        . '<w:p><w:r><w:t>Plain paragraph, no links here.</w:t></w:r></w:p>'
        . '<w:p><w:r><w:t>Internal-only link: </w:t></w:r>'
        . '<w:hyperlink w:anchor="bookmark1"><w:r><w:t>see above</w:t></w:r></w:hyperlink>'
        . '</w:p>'
        . '</w:body></w:document>';

    $rels_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" '
        . 'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" '
        . 'Target="https://example.com/amsterdam" TargetMode="External"/>'
        . '</Relationships>';

    $zip = new ZipArchive();
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('word/document.xml', $doc_xml);
    $zip->addFromString('word/_rels/document.xml.rels', $rels_xml);
    $zip->close();
}

$fixture_path = sys_get_temp_dir() . '/docx-test-fixture-' . getmypid() . '.docx';
docx_test_build_fixture($fixture_path);
$text = docx_extract_text($fixture_path);
unlink($fixture_path);

$checks = [
    'external hyperlink becomes a Markdown link'
        => str_contains($text, 'Visit [Amsterdam](https://example.com/amsterdam) for the canals.'),
    'plain paragraph is untouched'
        => str_contains($text, 'Plain paragraph, no links here.'),
    'internal-only (bookmark) hyperlink keeps its visible text, no fabricated URL'
        => str_contains($text, 'Internal-only link: see above') && !str_contains($text, '[see above]'),
    'Markdown source link becomes an HTML anchor deterministically'
        => docx_markdown_links_to_html('[Amsterdam](https://example.com/amsterdam)')
            === '<a href="https://example.com/amsterdam">Amsterdam</a>',
    'Markdown URL with balanced parentheses becomes a complete HTML anchor'
        => docx_markdown_links_to_html('[Fronton](https://nl.wikipedia.org/wiki/Fronton_(bouwkunde))')
            === '<a href="https://nl.wikipedia.org/wiki/Fronton_(bouwkunde)">Fronton</a>',
    'HTML-special characters in link labels and URLs are escaped'
        => docx_markdown_links_to_html('[A & B](https://example.com/?a=1&b=2)')
            === '<a href="https://example.com/?a=1&amp;b=2">A &amp; B</a>',
];

$failed = array_filter($checks, fn($passed) => !$passed);
if (!empty($failed)) {
    foreach (array_keys($failed) as $description) {
        fwrite(STDERR, "FAIL: {$description}\n");
    }
    fwrite(STDERR, "Extracted text was:\n{$text}\n");
    exit(1);
}

echo "docx tests passed\n";
