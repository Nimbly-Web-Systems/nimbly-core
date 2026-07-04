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
        die("Error: .env not found. Run './nimbly init' first.\n");
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
    load_library('util');
}

function migrate_10_collect()
{
    $data_dir = BASE_DIR . 'ext/data/';
    if (!is_dir($data_dir)) {
        die("Error: ext/data/ not found.\n");
    }

    $pk_resources = [];
    $name_fields = [];
    $i18n_meta = [];
    $custom_fields = [];
    $core_field_types = migrate_10_core_field_types();

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

        $has_translations = isset($meta['translations']);
        $has_languages = isset($meta['languages']);
        if ($has_translations || $has_languages) {
            $i18n_meta[$resource] = [
                'translations' => $has_translations,
                'languages' => $has_languages,
            ];
        }

        $fields = $meta['fields'] ?? [];
        if (!is_array($fields)) {
            $fields = [];
        }

        foreach (migrate_10_walk_fields($fields) as $field) {
            if (($field['type'] ?? '') === 'name') {
                $name_fields[] = [
                    'resource' => $resource,
                    'path' => $field['path'],
                    'slug' => !empty($field['def']['slug']),
                    'pk' => $meta['pk'] ?? null,
                    'auto' => empty($field['def']['slug']),
                ];
                continue;
            }

            if (!empty($field['type']) && !isset($core_field_types[$field['type']])) {
                $custom_fields[] = [
                    'resource' => $resource,
                    'path' => $field['path'],
                    'type' => $field['type'],
                ];
            }
        }
    }

    return [
        'pk_resources' => $pk_resources,
        'name_fields' => $name_fields,
        'i18n_meta' => $i18n_meta,
        'custom_fields' => $custom_fields,
        'legacy_field_templates' => migrate_10_find_legacy_field_templates(BASE_DIR . 'ext/'),
        'legacy_handlers' => migrate_10_find_legacy_trigger_handlers(BASE_DIR . 'ext/modules/'),
        'services' => migrate_10_find_services($data_dir),
    ];
}

function migrate_10_has_work($state)
{
    return !empty($state['pk_resources'])
        || !empty(array_filter($state['name_fields'], fn($field) => !empty($field['auto'])))
        || !empty($state['i18n_meta'])
        || !empty($state['custom_fields'])
        || !empty($state['legacy_field_templates'])
        || !empty($state['legacy_handlers'])
        || !empty($state['services']);
}

function migrate_10_print_summary($state)
{
    $pk_resources = $state['pk_resources'];
    $name_fields = $state['name_fields'];
    $i18n_meta = $state['i18n_meta'];
    $custom_fields = $state['custom_fields'];
    $legacy_field_templates = $state['legacy_field_templates'];
    $legacy_handlers = $state['legacy_handlers'];
    $services = $state['services'];

    if (!empty($legacy_handlers)) {
        echo "\nLegacy 1.0 trigger handlers found:\n\n";
        foreach ($legacy_handlers as $handler) {
            echo "  - {$handler}\n";
        }
        echo "\nCore 1.1.0 uses resource .meta events instead of automatic data-create triggers.\n";
        echo "Move each handler to a named event or job, then declare it on the target resource .meta:\n\n";
        echo "  \"events\": {\n";
        echo "      \"create\": [\"job:example-created\"]\n";
        echo "  }\n\n";
    }

    if (!empty($services)) {
        echo "\n.services records found — this resource is removed in core 1.1.0:\n\n";
        foreach ($services as $s) {
            printf("  %-40s  service=%-16s  tpl=%s\n", $s['uuid'], $s['service'], $s['tpl']);
            foreach ($s['decrypted'] as $field => $value) {
                printf("      decrypted %-6s = %s\n", $field, $value);
            }
        }
        echo "\nAll service configuration now lives in .env.\n";
        echo "Copy the decrypted values above into .env, then delete these records.\n";
        echo "Deleting a record without first copying its value into .env loses the credential permanently.\n\n";
        echo "For email services, add:\n";
        echo "  MAIL_SERVICE=resend          # or: smtp, mailgun, system\n";
        echo "  MAIL_FROM=no-reply@yourdomain.com\n";
        echo "  MAIL_FROM_NAME=Your Site Name\n";
        echo "  RESEND_API_KEY=re_xxxxxxxxxxxx\n\n";
        echo "For OpenAI:\n";
        echo "  OPENAI_API_KEY=sk-xxxxxxxxxxxx\n\n";

        $env_keys = migrate_10_read_env_keys(BASE_DIR . '.env');
        foreach ($services as $s) {
            if (stripos($s['tpl'], 'openai') === false) {
                continue;
            }
            if (empty($env_keys['OPENAI_API_KEY'])) {
                echo "  WARNING: OPENAI_API_KEY is still missing or empty in .env for '{$s['tpl']}' — do not delete this record yet.\n";
            } else {
                echo "  OK: OPENAI_API_KEY is already set in .env for '{$s['tpl']}'.\n";
            }
        }
        echo "\n";

        cli_tip("See §19 step 2 of NIMBLY.md for details.");
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

    if (!empty($name_fields)) {
        echo "\nResources still using removed field type 'name':\n\n";
        foreach ($name_fields as $field) {
            $action = $field['auto']
                ? 'will convert to text'
                : (!empty($field['pk']) ? 'handled by pk slug migration' : 'manual slug-field migration needed');
            printf("  %-24s %-28s slug=%-3s %s\n", $field['resource'], $field['path'], $field['slug'] ? 'yes' : 'no', $action);
        }
    }

    if (!empty($i18n_meta)) {
        echo "\nResource i18n metadata found:\n\n";
        foreach ($i18n_meta as $resource => $info) {
            if ($info['translations'] && $info['languages']) {
                printf("  %-24s has both legacy translations and 1.1 languages metadata — review manually\n", $resource);
            } elseif ($info['translations']) {
                printf("  %-24s legacy record-level translations metadata — keep unless intentionally migrating data\n", $resource);
            } else {
                printf("  %-24s field-level 1.1 languages metadata\n", $resource);
            }
        }
    }

    if (!empty($custom_fields)) {
        echo "\nCustom/non-core field types found:\n\n";
        foreach ($custom_fields as $field) {
            printf("  %-24s %-28s type=%s\n", $field['resource'], $field['path'], $field['type']);
        }
        echo "\nCustom field types must provide a 1.1-compatible field-{type} template and use _f.* variables.\n";
    }

    if (!empty($legacy_field_templates)) {
        echo "\nLegacy custom field template patterns found:\n\n";
        foreach ($legacy_field_templates as $hit) {
            printf("  %-64s %s\n", $hit['file'], implode(', ', $hit['tokens']));
        }
        echo "\nThese are warnings only. Replace legacy _fmodel/_fid/_ftitle/_fbg/_bf_render_field/data-te-* usage manually.\n";
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

    migrate_10_apply_name_fields($state['name_fields']);
}

function migrate_10_print_done($state)
{
    echo "\n=== Migration complete ===\n\n";
    echo "Next step — update route.inc files that use the old 1.0 lookup pattern:\n\n";
    echo "  OLD (1.0):\n";
    echo "    if (!data_exists(\$resource, md5_uuid(\$slug))) return;\n\n";
    echo "  NEW (1.1.0):\n";
    echo "    \$records = data_read_index(\$resource, 'slug_field', md5_uuid(\$slug));\n";
    echo "    if (empty(\$records)) return;\n";
    echo "    \$record = reset(\$records);\n";
    echo "    set_variable_dot('record', \$record);\n\n";
    if (!empty($state['legacy_handlers'])) {
        echo "Also migrate the legacy trigger handlers listed above to .meta events.\n\n";
    }
    cli_tip("See §18 of NIMBLY.md for the full upgrade guide.");
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

    $meta = [];
    $meta_file = $services_dir . '.meta';
    if (is_file($meta_file)) {
        $meta = json_decode(file_get_contents($meta_file), true) ?? [];
    }
    $encrypt_fields = !empty($meta['encrypt2way']) ? explode(',', $meta['encrypt2way']) : [];
    if (!empty($encrypt_fields)) {
        load_library('encrypt');
    }

    $result = [];
    foreach (glob($services_dir . '*') ?: [] as $file) {
        $basename = basename($file);
        if ($basename === '.meta' || is_dir($file)) {
            continue;
        }
        $record = json_decode(file_get_contents($file), true) ?? [];

        // Decrypt now, while the record still exists and PEPPER is available,
        // so the operator can copy the real value straight into .env instead
        // of having to remember to do it before deleting this record.
        $decrypted = [];
        foreach ($encrypt_fields as $field) {
            if (empty($record[$field]) || empty($_SERVER['PEPPER'])) {
                continue;
            }
            $value = decrypt_2way($record[$field], $record['salt'] ?? '');
            if ($value !== false) {
                $decrypted[$field] = $value;
            }
        }

        $result[] = [
            'uuid'      => $basename,
            'service'   => $record['service'] ?? '(unknown)',
            'tpl'       => $record['tpl'] ?? '(no tpl)',
            'decrypted' => $decrypted,
        ];
    }
    return $result;
}

function migrate_10_read_env_keys($env_file)
{
    $keys = [];
    if (!is_file($env_file)) {
        return $keys;
    }
    foreach (file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        if ($line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        $keys[$k] = $v;
    }
    return $keys;
}

function migrate_10_core_field_types(): array
{
    $types = [];
    foreach ([
        BASE_DIR . 'core/modules/forms/tpl/field-*',
        BASE_DIR . 'core/modules/admin/tpl/field-*',
    ] as $pattern) {
        foreach (glob($pattern, GLOB_ONLYDIR) ?: [] as $dir) {
            $types[substr(basename($dir), strlen('field-'))] = true;
        }
    }
    return $types;
}

function migrate_10_walk_fields(array $fields, string $prefix = ''): array
{
    $result = [];
    foreach ($fields as $name => $def) {
        if (!is_array($def)) {
            continue;
        }
        $path = $prefix === '' ? (string)$name : $prefix . '.' . $name;
        $result[] = [
            'path' => $path,
            'type' => $def['type'] ?? '',
            'def' => $def,
        ];
        if (!empty($def['fields']) && is_array($def['fields'])) {
            $result = array_merge($result, migrate_10_walk_fields($def['fields'], $path));
        }
    }
    return $result;
}

function migrate_10_find_legacy_field_templates(string $ext_dir): array
{
    if (!is_dir($ext_dir)) {
        return [];
    }

    $patterns = [
        '_fmodel' => '/\[#_fmodel#\]/',
        '_fid' => '/\[#_fid#\]/',
        '_ftitle' => '/\[#_ftitle#\]/',
        '_fbg' => '/\[#_fbg#\]/',
        '_bf_render_field' => '/\b_bf_render_field\s*\(/',
        'data-te-*' => '/\bdata-te-[a-z0-9_-]+/i',
    ];
    $result = [];
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($ext_dir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $file) {
        $path = $file->getPathname();
        if (str_starts_with($path, BASE_DIR . 'ext/static/')) {
            continue;
        }
        if (!in_array($file->getExtension(), ['tpl', 'php', 'inc', 'js'], true)) {
            continue;
        }
        $content = file_get_contents($path);
        $tokens = [];
        foreach ($patterns as $label => $pattern) {
            if (preg_match($pattern, $content)) {
                $tokens[] = $label;
            }
        }
        if (!empty($tokens)) {
            $result[] = [
                'file' => str_replace(BASE_DIR, '', $path),
                'tokens' => $tokens,
            ];
        }
    }
    return $result;
}

function migrate_10_apply_name_fields(array $name_fields): void
{
    $resources = [];
    foreach ($name_fields as $field) {
        if (!empty($field['auto'])) {
            $resources[$field['resource']] = true;
        }
    }

    foreach (array_keys($resources) as $resource) {
        $meta_file = BASE_DIR . 'ext/data/' . $resource . '/.meta';
        if (!is_file($meta_file)) {
            continue;
        }
        $meta = json_decode(file_get_contents($meta_file), true) ?? [];
        if (empty($meta['fields']) || !is_array($meta['fields'])) {
            continue;
        }
        $changed = migrate_10_convert_non_slug_name_fields($meta['fields']);
        if ($changed === 0) {
            continue;
        }
        $json = json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        file_put_contents($meta_file, $json . "\n");
        echo "\n--- $resource ---\n";
        echo "  Converted {$changed} non-slug 'name' field" . ($changed === 1 ? '' : 's') . " to 'text'.\n";
    }
}

function migrate_10_convert_non_slug_name_fields(array &$fields): int
{
    $changed = 0;
    foreach ($fields as &$def) {
        if (!is_array($def)) {
            continue;
        }
        if (($def['type'] ?? '') === 'name' && empty($def['slug'])) {
            $def['type'] = 'text';
            $changed++;
        }
        if (!empty($def['fields']) && is_array($def['fields'])) {
            $changed += migrate_10_convert_non_slug_name_fields($def['fields']);
        }
    }
    unset($def);
    return $changed;
}
