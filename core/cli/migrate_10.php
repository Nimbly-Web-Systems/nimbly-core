<?php

/**
 * Nimbly CLI — migrate-pk-index command
 *
 * Usage: php core/cli/nimbly.php migrate-pk-index
 *
 * Migrates resources from core 1.0 to core 1.1:
 *
 *   - Finds every .meta that defines "pk" (the old primary-key-as-UUID mechanism).
 *   - Ensures the pk field is listed in the .meta "index" array.
 *   - Creates index entries for all records in that resource, including the
 *     self-referential entries (index_uuid === record_uuid) that the normal
 *     reindex command intentionally skips. These exist for every 1.0 record
 *     because its UUID was derived as md5_uuid(pk_field_value).
 *   - Removes "pk" from .meta and writes the updated .meta file.
 *   - Reports legacy 1.0 trigger handlers (`*-on-data-create`) so they can be
 *     migrated to resource `.meta` event declarations.
 */

if (php_sapi_name() !== 'cli') {
    die("nimbly.php must be run from the command line.\n");
}

if (!defined('BASE_DIR')) {
    define('BASE_DIR', realpath(__DIR__ . '/../..') . '/');
}

require_once BASE_DIR . 'core/cli/helpers/migrate_10.php';

migrate_10_bootstrap();
$state = migrate_10_collect();

if (!migrate_10_has_work($state)) {
    echo "No resources with 'pk' and no legacy trigger handlers found — nothing to migrate.\n";
    exit(0);
}

migrate_10_print_summary($state);
echo "\nProceed? [y/N] ";
$confirm = trim(fgets(STDIN));
if (strtolower($confirm) !== 'y') {
    die("Aborted.\n");
}

migrate_10_apply($state);
migrate_10_print_done($state);
