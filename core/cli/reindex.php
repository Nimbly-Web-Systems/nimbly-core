<?php

/**
 * Nimbly CLI — reindex command
 *
 * Usage: php core/cli/nimbly.php reindex [resource]
 *
 * Rebuilds index entries for all records in an indexed resource.
 * If no resource is given, lists resources with indexes and prompts for a choice.
 */

if (php_sapi_name() !== 'cli') {
    die("nimbly.php must be run from the command line.\n");
}

if (!defined('BASE_DIR')) define('BASE_DIR', realpath(__DIR__ . '/../..') . '/');

// -----------------------------------------------------------------------
// Bootstrap
// -----------------------------------------------------------------------

$GLOBALS['SYSTEM'] = [
    'file_base'  => BASE_DIR,
    'env_paths'  => ['ext', 'core'],
    'modules'    => ['root' => '/'],
    'variables'  => [],
    'uri'        => '',
];

require_once BASE_DIR . 'core/lib/find.php';

$env_file = BASE_DIR . '.env';
if (!file_exists($env_file)) {
    die("Error: .env not found. Run 'php core/cli/nimbly.php setup' first.\n");
}

$env = [];
foreach (file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), '#')) continue;
    [$key, $val] = array_map('trim', explode('=', $line, 2) + [1 => '']);
    $env[$key] = $val;
}

$_SERVER['PEPPER'] = $env['PEPPER'] ?? '';

load_library('data');
load_library('md5');

// -----------------------------------------------------------------------
// Find resources that have indexes defined
// -----------------------------------------------------------------------

$data_dir = BASE_DIR . 'ext/data/';
if (!is_dir($data_dir)) {
    die("Error: ext/data/ not found.\n");
}

$indexed_resources = [];
foreach (glob($data_dir . '*/.meta') as $meta_file) {
    $resource = basename(dirname($meta_file));
    $meta = json_decode(file_get_contents($meta_file), true) ?? [];
    if (!empty($meta['index']) && is_array($meta['index'])) {
        $indexed_resources[$resource] = $meta['index'];
    }
}

if (empty($indexed_resources)) {
    echo "No resources with indexes found.\n";
    exit(0);
}

// -----------------------------------------------------------------------
// Select resource
// -----------------------------------------------------------------------

$target = $argv[2] ?? null;

if ($target) {
    if (!isset($indexed_resources[$target])) {
        echo "Resource '$target' has no indexes or does not exist.\n";
        echo "Indexed resources: " . implode(', ', array_keys($indexed_resources)) . "\n";
        exit(1);
    }
} else {
    echo "\nResources with indexes:\n\n";
    $i = 1;
    $keys = array_keys($indexed_resources);
    foreach ($keys as $name) {
        printf("  [%d] %-24s indexes: %s\n", $i++, $name, implode(', ', $indexed_resources[$name]));
    }
    echo "\nEnter number or resource name: ";
    $input = trim(fgets(STDIN));
    if (is_numeric($input) && isset($keys[(int)$input - 1])) {
        $target = $keys[(int)$input - 1];
    } elseif (isset($indexed_resources[$input])) {
        $target = $input;
    } else {
        die("Invalid selection.\n");
    }
}

$indexes = $indexed_resources[$target];
echo "\nReindexing '$target' (indexes: " . implode(', ', $indexes) . ")...\n\n";

// -----------------------------------------------------------------------
// Walk records and rebuild index entries
// -----------------------------------------------------------------------

$records = data_read($target);
$count      = 0;
$self_ref   = 0;
$skipped    = 0;

foreach ($records as $uuid => $record) {
    $file         = data_path($target, $uuid);
    $indexed      = false;
    $is_self_ref  = false;
    foreach ($indexes as $index_name) {
        if (empty($record[$index_name])) {
            continue;
        }
        $index_uuid = md5_uuid($record[$index_name]);
        if ($index_uuid === $uuid) {
            $is_self_ref = true;
            continue;
        }
        _data_create_index($target, $file, $index_name, $index_uuid);
        $indexed = true;
    }
    if ($indexed) {
        $count++;
    } elseif ($is_self_ref) {
        $self_ref++;
    } else {
        $skipped++;
    }
}

echo "Done. Indexed: $count record(s)";
if ($self_ref > 0) {
    echo ", self-referential (already indexed): $self_ref record(s)";
}
if ($skipped > 0) {
    echo ", skipped (no index value): $skipped record(s)";
}
echo ".\n\n";
