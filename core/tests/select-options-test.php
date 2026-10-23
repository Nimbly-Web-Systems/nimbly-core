<?php

function load_library($_name) {}

function make_slug($value): string
{
    return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower((string)$value)), '-');
}

require_once __DIR__ . '/../modules/api/lib/select-options.php';

function select_options_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$current = ['acquisition' => 'Acquisition', 'marketing' => 'Marketing'];

// the given order is kept, a new option gets a value from its label
$options = select_options_from_input([
    ['value' => 'marketing', 'label' => 'Marketing & Sales'],
    ['label' => 'Business Plan'],
    ['value' => 'acquisition', 'label' => 'Acquisition'],
], $current);
select_options_assert($options === ['marketing' => 'Marketing & Sales', 'business_plan' => 'Business Plan', 'acquisition' => 'Acquisition'], 'order and new value');

// a new label that makes an existing value gets its own
select_options_assert(array_keys(select_options_from_input([['value' => 'marketing', 'label' => 'Marketing'], ['label' => 'Marketing']], $current)) === ['marketing', 'marketing_2'], 'values stay unique');

// labels are plain text and values must exist
select_options_assert(select_options_from_input([['label' => '[#env APP_ENV#]']], $current) === null, 'shortcode refused');
select_options_assert(select_options_from_input([['label' => '<b>x</b>']], $current) === null, 'markup refused');
select_options_assert(select_options_from_input([['label' => '']], $current) === null, 'empty refused');
select_options_assert(select_options_from_input([['value' => 'unknown', 'label' => 'X']], $current) === null, 'unknown value refused');

echo "select-options: ok\n";
