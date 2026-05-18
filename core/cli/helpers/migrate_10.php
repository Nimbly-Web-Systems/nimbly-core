<?php

function migrate_10_bootstrap()
{
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
        if (str_starts_with(trim($line), '#')) {
            continue;
        }
        [$key, $val] = array_map('trim', explode('=', $line, 2) + [1 => '']);
        $env[$key] = $val;
    }

    $_SERVER['PEPPER'] = $env['PEPPER'] ?? '';

    load_library('data');
    load_library('md5');
}

function migrate_10_collect()
{
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

    return [
        'pk_resources' => $pk_resources,
        'legacy_handlers' => migrate_10_find_legacy_trigger_handlers(BASE_DIR . 'ext/modules/'),
        'services' => migrate_10_find_services($data_dir),
    ];
}

function migrate_10_has_work($state)
{
    return !empty($state['pk_resources']) || !empty($state['legacy_handlers']) || !empty($state['services']);
}

function migrate_10_print_summary($state)
{
    $pk_resources = $state['pk_resources'];
    $legacy_handlers = $state['legacy_handlers'];
    $services = $state['services'];

    if (!empty($legacy_handlers)) {
        echo "\nLegacy 1.0 trigger handlers found:\n\n";
        foreach ($legacy_handlers as $handler) {
            echo "  - {$handler}\n";
        }
        echo "\nCore 1.1 uses resource .meta events instead of automatic data-create triggers.\n";
        echo "Move each handler to a named event or job, then declare it on the target resource .meta:\n\n";
        echo "  \"events\": {\n";
        echo "      \"create\": [\"job:example-created\"]\n";
        echo "  }\n\n";
    }

    if (!empty($services)) {
        echo "\n.services records found — this resource is removed in core 1.1:\n\n";
        foreach ($services as $s) {
            printf("  %-40s  service=%-16s  tpl=%s\n", $s['uuid'], $s['service'], $s['tpl']);
        }
        echo "\nAll service configuration now lives in .env.\n";
        echo "Move any credentials or API keys to .env and delete these records.\n\n";
        echo "For email services, add:\n";
        echo "  MAIL_SERVICE=resend          # or: phpmailer, mailgun, system\n";
        echo "  MAIL_FROM=no-reply@yourdomain.com\n";
        echo "  MAIL_FROM_NAME=Your Site Name\n";
        echo "  RESEND_API_KEY=re_xxxxxxxxxxxx\n\n";
        echo "For OpenAI:\n";
        echo "  OPENAI_API_KEY=sk-xxxxxxxxxxxx\n\n";
        echo "See §18 step 7 of NIMBLY.md for details.\n\n";
    }

    if (!empty($pk_resources)) {
        echo "\nResources with 'pk' (1.0 primary-key-as-UUID pattern):\n\n";
        foreach ($pk_resources as $resource => $info) {
            printf("  %-24s pk = %s\n", $resource, $info['pk']);
        }
        echo "\nThis migration will:\n";
        echo "  1. Add the pk field to 'index' in .meta (if not already there)\n";
        echo "  2. Create index entries for all records (including 1.0 self-referential ones)\n";
        echo "  3. Remove 'pk' from .meta\n";
        echo "  4. Convert 'name' fields with slug:true to 'text', and add a 'slug' field definition\n";
    } else {
        echo "\nNo resources with 'pk' found.\n";
    }
}

function migrate_10_apply($state)
{
    foreach ($state['pk_resources'] as $resource => $info) {
        $meta_file = $info['meta_file'];
        $meta      = $info['meta'];
        $pk_field  = $info['pk'];

        echo "\n--- $resource (pk: $pk_field) ---\n";

        $meta['index'] = $meta['index'] ?? [];
        if (!in_array($pk_field, $meta['index'], true)) {
            $meta['index'][] = $pk_field;
            echo "  Added '$pk_field' to index array.\n";
        } else {
            echo "  '$pk_field' already in index array.\n";
        }

        $records = data_read($resource);
        $indexed  = 0;
        $skipped  = 0;

        foreach ($records as $uuid => $record) {
            if (empty($record[$pk_field])) {
                $skipped++;
                continue;
            }
            $file = data_path($resource, $uuid);
            foreach (data_index_uuids($record[$pk_field]) as $index_uuid) {
                _data_create_index($resource, $file, $pk_field, $index_uuid);
                $indexed++;
            }
        }

        echo "  Indexed: $indexed entr" . ($indexed === 1 ? 'y' : 'ies') . ", skipped (no value): $skipped record(s).\n";

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
                    }
                    break;
                }
            }
            unset($fdef);
        }

        unset($meta['pk']);
        $json = json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        file_put_contents($meta_file, $json . "\n");
        echo "  Removed 'pk' from .meta and saved.\n";
    }
}

function migrate_10_print_done($state)
{
    echo "\n=== Migration complete ===\n\n";
    echo "Next step — update route.inc files that use the old 1.0 lookup pattern:\n\n";
    echo "  OLD (1.0):\n";
    echo "    if (!data_exists(\$resource, md5_uuid(\$slug))) return;\n\n";
    echo "  NEW (1.1):\n";
    echo "    \$records = data_read_index(\$resource, 'slug_field', md5_uuid(\$slug));\n";
    echo "    if (empty(\$records)) return;\n";
    echo "    \$record = reset(\$records);\n";
    echo "    set_variable_dot('record', \$record);\n\n";
    if (!empty($state['legacy_handlers'])) {
        echo "Also migrate the legacy trigger handlers listed above to .meta events.\n\n";
    }
    echo "See §18 of NIMBLY.md for the full upgrade guide.\n\n";
}

function migrate_10_find_legacy_trigger_handlers($modules_dir)
{
    if (!is_dir($modules_dir)) {
        return [];
    }

    $result = [];
    foreach (glob($modules_dir . '*/lib/*-on-data-create/*-on-data-create.php') ?: [] as $file) {
        $result[] = str_replace(BASE_DIR, '', $file);
    }
    return $result;
}

function migrate_10_find_services($data_dir)
{
    $services_dir = $data_dir . '.services/';
    if (!is_dir($services_dir)) {
        return [];
    }

    $result = [];
    foreach (glob($services_dir . '*') ?: [] as $file) {
        $basename = basename($file);
        if ($basename === '.meta' || is_dir($file)) {
            continue;
        }
        $record = json_decode(file_get_contents($file), true) ?? [];
        $result[] = [
            'uuid'    => $basename,
            'service' => $record['service'] ?? '(unknown)',
            'tpl'     => $record['tpl'] ?? '(no tpl)',
        ];
    }
    return $result;
}
