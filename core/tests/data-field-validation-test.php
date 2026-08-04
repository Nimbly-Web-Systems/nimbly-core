<?php

$GLOBALS['SYSTEM'] = [
    'file_base' => dirname(__DIR__, 2) . '/',
    'variables' => [],
];

require_once dirname(__DIR__) . '/lib/data.php';

function data_field_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$meta = [
    'fields' => [
        'label' => ['required' => true],
        'position' => ['required' => true, 'min' => 0, 'max' => 1],
    ],
];

data_field_assert(
    _data_validate_field_definitions($meta, ['label' => 'Point', 'position' => 0]) === true,
    'zero was not accepted as a present boundary value'
);
data_field_assert(
    _data_validate_field_definitions($meta, ['label' => ['nl' => '', 'en' => 'Point'], 'position' => 1]) === true,
    'localized value or upper boundary was rejected'
);
data_field_assert(
    _data_validate_field_definitions($meta, ['label' => '', 'position' => 0.5]) === false,
    'empty required value was accepted'
);
data_field_assert(
    _data_validate_field_definitions($meta, ['label' => 'Point', 'position' => 1.1]) === false,
    'value above maximum was accepted'
);
data_field_assert(
    _data_validate_field_definitions($meta, ['label' => 'Point', 'position' => 'invalid']) === false,
    'non-numeric constrained value was accepted'
);

echo "Data field validation tests passed.\n";
