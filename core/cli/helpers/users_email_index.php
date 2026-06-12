<?php

function users_email_index_bootstrap(): void
{
    $GLOBALS['SYSTEM'] = [
        'file_base'  => BASE_DIR,
        'env_paths'  => ['ext', 'core'],
        'modules'    => ['root' => '/'],
        'variables'  => [],
        'uri'        => '',
    ];

    require_once BASE_DIR . 'core/lib/find.php';
    load_library('data');
    load_library('util');
}

function users_email_index_collect(): array
{
    $meta_file = BASE_DIR . 'ext/data/users/.meta';
    if (!is_file($meta_file)) {
        return [
            'exists' => false,
            'meta_file' => $meta_file,
            'needs_index' => false,
            'needs_unique' => false,
            'duplicates' => [],
            'records' => 0,
        ];
    }

    $meta = json_decode(file_get_contents($meta_file), true) ?? [];
    $index = isset($meta['index']) && is_array($meta['index']) ? $meta['index'] : [];
    $unique = isset($meta['unique']) && is_array($meta['unique']) ? $meta['unique'] : [];
    $duplicates = users_email_index_duplicates();

    return [
        'exists' => true,
        'meta_file' => $meta_file,
        'meta' => $meta,
        'needs_index' => !in_array('email', $index, true),
        'needs_unique' => empty($duplicates) && !in_array('email', $unique, true),
        'duplicates' => $duplicates,
        'records' => count(data_read('users')),
    ];
}

function users_email_index_has_work(array $state): bool
{
    if (empty($state['exists'])) {
        return false;
    }

    return !empty($state['needs_index']) || !empty($state['needs_unique']);
}

function users_email_index_duplicates(): array
{
    $seen = [];
    $duplicates = [];
    foreach (data_read('users') as $uuid => $record) {
        $email = trim((string)($record['email'] ?? ''));
        if ($email === '') {
            continue;
        }

        $key = strtolower($email);
        if (!isset($seen[$key])) {
            $seen[$key] = [];
        }
        $seen[$key][] = [
            'uuid' => $uuid,
            'email' => $email,
        ];
    }

    foreach ($seen as $key => $records) {
        if (count($records) > 1) {
            $duplicates[$key] = $records;
        }
    }

    return $duplicates;
}

function users_email_index_print_summary(array $state): void
{
    if (empty($state['exists'])) {
        echo "\nNo users/.meta found; skipping users email index migration.\n";
        return;
    }

    echo "\nUsers email lookup migration:\n\n";
    echo "  Records: " . (int)$state['records'] . "\n";
    echo "  Add email index: " . (!empty($state['needs_index']) ? 'yes' : 'already present') . "\n";
    echo "  Add email uniqueness: " . (!empty($state['needs_unique']) ? 'yes' : 'already present or blocked') . "\n";

    if (!empty($state['duplicates'])) {
        echo "\n  Duplicate emails found; unique=email will not be added until these are resolved:\n";
        foreach ($state['duplicates'] as $email => $records) {
            echo "    - {$email}\n";
            foreach ($records as $record) {
                echo "      " . $record['uuid'] . "  " . $record['email'] . "\n";
            }
        }
    }
}

function users_email_index_apply(array $state): array
{
    if (empty($state['exists'])) {
        return [
            'meta_updated' => false,
            'indexed' => 0,
            'skipped' => 0,
        ];
    }

    $meta = $state['meta'];
    $meta_updated = false;

    $meta['index'] = isset($meta['index']) && is_array($meta['index']) ? $meta['index'] : [];
    if (!in_array('email', $meta['index'], true)) {
        $meta['index'][] = 'email';
        $meta_updated = true;
    }

    if (empty($state['duplicates'])) {
        $meta['unique'] = isset($meta['unique']) && is_array($meta['unique']) ? $meta['unique'] : [];
        if (!in_array('email', $meta['unique'], true)) {
            $meta['unique'][] = 'email';
            $meta_updated = true;
        }
    }

    if ($meta_updated) {
        $json = json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        file_put_contents($state['meta_file'], $json . "\n");
    }

    $indexed = 0;
    $skipped = 0;
    foreach (data_read('users') as $uuid => $record) {
        if (empty($record['email'])) {
            $skipped++;
            continue;
        }

        $file = data_path('users', $uuid);
        foreach (data_index_uuids($record['email']) as $index_uuid) {
            _data_create_index('users', $file, 'email', $index_uuid);
            $indexed++;
        }
    }

    return [
        'meta_updated' => $meta_updated,
        'indexed' => $indexed,
        'skipped' => $skipped,
    ];
}

function users_email_index_print_done(array $result): void
{
    echo "  Meta updated: " . (!empty($result['meta_updated']) ? 'yes' : 'no') . "\n";
    echo "  Indexed: " . (int)$result['indexed'] . " entr" . ((int)$result['indexed'] === 1 ? 'y' : 'ies') . "\n";
    echo "  Skipped without email: " . (int)$result['skipped'] . " record(s)\n";
}
