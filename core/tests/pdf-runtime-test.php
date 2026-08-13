<?php

function pdf_runtime_test_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function pdf_runtime_test_remove_fixture(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $item) {
        $item_path = $path . '/' . $item;
        if (is_dir($item_path)) {
            pdf_runtime_test_remove_fixture($item_path);
        } else {
            unlink($item_path);
        }
    }
    rmdir($path);
}

$fixture = sys_get_temp_dir() . '/nimbly-pdf-runtime-test-' . bin2hex(random_bytes(4));
mkdir($fixture . '/ext/vendor', 0755, true);

$autoload = <<<'PHP'
<?php
namespace Dompdf;

class Options
{
    public function __call(string $name, array $arguments): void {}
}

class Dompdf
{
    public function __construct(Options $options) {}
    public function setPaper($size, string $orientation): void {}
    public function loadHtml(string $html): void {}
    public function render(): void {}
    public function output(): string { return '%PDF-test'; }
}
PHP;
file_put_contents($fixture . '/ext/vendor/autoload.php', $autoload);

$GLOBALS['SYSTEM'] = ['file_base' => $fixture . '/'];

require_once __DIR__ . '/../modules/pdf/lib/pdf.php';

$html = pdf_build_document_html([
    'margins' => ['top' => 10, 'right' => 11, 'bottom' => 12, 'left' => 13],
    'pages' => ['<p>First</p>', '<p>Second</p>'],
]);
pdf_runtime_test_assert(str_contains($html, '@page { margin: 10mm 11mm 12mm 13mm; }'), 'PDF margins render through the document template');
pdf_runtime_test_assert(str_contains($html, '<body><div><p>First</p></div>'), 'first PDF page renders without a page break');
pdf_runtime_test_assert(str_contains($html, '<div class="pdf-page-break"><p>Second</p></div>'), 'later PDF pages render with page breaks');

$pdf = pdf_render_document(['pages' => ['<p>Test</p>']]);
pdf_runtime_test_assert($pdf === '%PDF-test', 'PDF renders without an environment-local module state record');
pdf_runtime_test_assert(
    is_dir($fixture . '/ext/data/.pdf_font_cache'),
    'PDF font cache is created on first render'
);

pdf_runtime_test_remove_fixture($fixture);
echo "PDF runtime tests passed\n";
