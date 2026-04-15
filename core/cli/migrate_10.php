<?php

/**
 * Nimbly CLI — migrate-10 command
 *
 * Usage: php core/cli/nimbly.php migrate-10
 *
 * Migrates resources from core 1.0 to core 1.1:
 *
 *   - Finds every .meta that defines "pk" (the old primary-key-as-UUID mechanism).
 *   - Ensures the pk field is listed in the .meta "index" array.
 *   - Creates index entries for all records in that resource, including the
 *     self-referential entries (index_uuid === record_uuid) that the normal
 *     reindex command intentionally skips.  These exist for every 1.0 record
 *     because its UUID was derived as md5_uuid(pk_field_value).
 *   - Removes "pk" from .meta and writes the updated .meta file.
 *
 * After running this command you still need to update any route.inc that uses
 * the old pattern:
 *
 *     data_exists('resource', md5_uuid($slug))
 *
 * Replace it with the 1.1 pattern:
 *
 *     $records = data_read_index('resource', 'slug_field', md5_uuid($slug));
 *     if (empty($records)) return;
 *     $record = reset($records);
 *     set_variable_dot('record', $record);
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

require_once BASE_DIR . 'core/lib/find/find.php';

$env_file = BASE_DIR . '.env';
if (!file_exists($env_file)) {
    die("Error: .env not found. Run 'php core/cli/nimbly.php setup' first.\n");
}

$env = [];
foreach (file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), '#')) {
        continue;
    }
    [$key, $val] = array_map('trim', explode('=', $line, 2) + [1 => '']);
    $env[$key] = $val;
}

$_SERVER['PEPPER'] = $env['PEPPER'] ?? '';

load_library('data');
load_library('md5');

// -----------------------------------------------------------------------
// Find resources that have "pk" defined
// -----------------------------------------------------------------------

$data_dir = BASE_DIR . 'ext/data/';
if (!is_dir($data_dir)) {
    die("Error: ext/data/ not found.\n");
}

$pk_resources = [];
foreach (glob($data_dir . '*/.meta') as $meta_file) {
    $resource = basename(dirname($meta_file));
    $meta = json_decode(file_get_contents($meta_file), true) ?? [];
    if (!empty($meta['pk'])) {
        $pk_resources[$resource] = [
            'meta_file' => $meta_file,
            'meta'      => $meta,
            'pk'        => $meta['pk'],
        ];
    }
}

if (empty($pk_resources)) {
    echo "No resources with 'pk' found — nothing to migrate.\n";
    exit(0);
}

echo "\nResources with 'pk' (1.0 primary-key-as-UUID pattern):\n\n";
foreach ($pk_resources as $resource => $info) {
    printf("  %-24s pk = %s\n", $resource, $info['pk']);
}
echo "\nThis migration will:\n";
echo "  1. Add the pk field to 'index' in .meta (if not already there)\n";
echo "  2. Create index entries for all records (including 1.0 self-referential ones)\n";
echo "  3. Remove 'pk' from .meta\n";
echo "  4. Convert 'name' fields with slug:true to 'text', and add a 'slug' field definition\n";
echo "\nProceed? [y/N] ";
$confirm = trim(fgets(STDIN));
if (strtolower($confirm) !== 'y') {
    die("Aborted.\n");
}

// -----------------------------------------------------------------------
// Migrate each resource
// -----------------------------------------------------------------------

foreach ($pk_resources as $resource => $info) {
    $meta_file = $info['meta_file'];
    $meta      = $info['meta'];
    $pk_field  = $info['pk'];

    echo "\n--- $resource (pk: $pk_field) ---\n";

    // Ensure pk field is in the index array
    $meta['index'] = $meta['index'] ?? [];
    if (!in_array($pk_field, $meta['index'], true)) {
        $meta['index'][] = $pk_field;
        echo "  Added '$pk_field' to index array.\n";
    } else {
        echo "  '$pk_field' already in index array.\n";
    }

    // Create index entries for all records
    $records = data_read($resource);
    $indexed  = 0;
    $skipped  = 0;

    foreach ($records as $uuid => $record) {
        if (empty($record[$pk_field])) {
            $skipped++;
            continue;
        }
        $index_uuid = md5_uuid($record[$pk_field]);
        $file       = data_path($resource, $uuid);
        _data_create_index($resource, $file, $pk_field, $index_uuid);
        $indexed++;
    }

    echo "  Indexed: $indexed record(s), skipped (no value): $skipped record(s).\n";

    // Convert 'name' field with slug:true to 'text', add slug field definition
    $slug_field_added = false;
    if (!empty($meta['fields']) && is_array($meta['fields'])) {
        foreach ($meta['fields'] as $fname => &$fdef) {
            if (($fdef['type'] ?? '') === 'name' && !empty($fdef['slug'])) {
                $fdef['type'] = 'text';
                unset($fdef['slug']);
                echo "  Converted field '$fname': type name+slug → text.\n";

                if (!isset($meta['fields'][$pk_field])) {
                    $meta['fields'][$pk_field] = [
                        'name'      => 'URL slug',
                        'type'      => 'slug',
                        'source'    => $fname,
                        'admin_col' => false,
                    ];
                    echo "  Added field '$pk_field': type slug, source=$fname.\n";
                    $slug_field_added = true;
                }
                break;
            }
        }
        unset($fdef);
    }

    // Remove pk from meta and write it back
    unset($meta['pk']);
    $json = json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    file_put_contents($meta_file, $json . "\n");
    echo "  Removed 'pk' from .meta and saved.\n";
}

// -----------------------------------------------------------------------
// Done — remind about route.inc updates
// -----------------------------------------------------------------------

echo "\n=== Migration complete ===\n\n";
echo "Next step — update route.inc files that use the old 1.0 lookup pattern:\n\n";
echo "  OLD (1.0):\n";
echo "    if (!data_exists(\$resource, md5_uuid(\$slug))) return;\n\n";
echo "  NEW (1.1):\n";
echo "    \$records = data_read_index(\$resource, 'slug_field', md5_uuid(\$slug));\n";
echo "    if (empty(\$records)) return;\n";
echo "    \$record = reset(\$records);\n";
echo "    set_variable_dot('record', \$record);\n\n";
echo "See §17 of Nimbly.md for the full upgrade guide.\n\n";
