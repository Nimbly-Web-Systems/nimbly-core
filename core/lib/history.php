<?php

/**
 * Record history: what a record was before each change, who made the change
 * and when. Kept under ext/data/.state/.history, which no project tracks in
 * git, as <resource>/<uuid>/<time>-<random>.
 */

/** Hidden resources with content an editor changes; the other hidden ones are machine state. */
const HISTORY_HIDDEN_RESOURCES = ['.content', '.config', '.navigation'];

/**
 * Whether changes to a resource are recorded. `history` in `.meta` decides;
 * without it every visible resource is recorded except `users`, whose records
 * hold password hashes and tokens.
 */
function history_enabled($resource, $meta = null)
{
    $resource = (string)$resource;
    $meta = is_array($meta) ? $meta : data_meta($resource);
    if (is_array($meta) && isset($meta['history'])) {
        return !empty($meta['history']);
    }
    if ($resource === '' || $resource === 'users') {
        return false;
    }
    return $resource[0] !== '.' || in_array($resource, HISTORY_HIDDEN_RESOURCES, true);
}

function history_path($resource, $uuid = '')
{
    if (!data_path_valid($resource, $uuid)) {
        return false;
    }
    $path = data_path('.state') . '/.history/' . $resource;
    return (string)$uuid === '' ? $path : $path . '/' . $uuid;
}

/**
 * Stores one change of a record. Called by the data functions, inside the
 * resource's write lock and before the record file is replaced or removed.
 *
 * @param string $action create, update or delete.
 * @param array|null $record The record as it was before the change; null for a create.
 * @param array $changed Names of the fields an update changes.
 * @return bool
 */
function history_capture($action, $resource, $uuid, $record = null, $changed = [])
{
    $dir = history_path($resource, $uuid);
    if ($dir === false || (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir))) {
        return false;
    }
    $now = microtime(true);
    $entry = [
        'action' => $action,
        'at' => (int)$now,
        'by' => _data_actor(),
        'run' => (string)($GLOBALS['SYSTEM']['data_actor']['run'] ?? ''),
        'changed' => array_values((array)$changed),
        'record' => is_array($record) ? $record : null,
    ];
    $file = $dir . '/' . sprintf('%017.6F', $now) . '-' . bin2hex(random_bytes(3));
    return _data_write_file_atomically($file, json_encode($entry, JSON_UNESCAPED_UNICODE)) !== false;
}

/** Same record apart from the two fields every update rewrites. */
function history_same_record($before, $after)
{
    if (!is_array($before) || !is_array($after)) {
        return false;
    }
    unset($before['_modified'], $before['_modified_by'], $after['_modified'], $after['_modified_by']);
    return $before === $after;
}

/** Names of the fields that differ between two versions of a record. */
function history_changed_fields($before, $after)
{
    $before = is_array($before) ? $before : [];
    $after = is_array($after) ? $after : [];
    $changed = [];
    foreach (array_keys($before + $after) as $field) {
        $field = (string)$field;
        if ($field === 'uuid' || $field[0] === '_') {
            continue;
        }
        if (($before[$field] ?? null) !== ($after[$field] ?? null)) {
            $changed[] = $field;
        }
    }
    return $changed;
}

/**
 * The changes of one record, newest first. Each has `id`, `action`, `at`,
 * `by`, `run`, `changed` (the fields an update changed) and `record`, the
 * record before the change.
 */
function history_list($resource, $uuid)
{
    $dir = history_path($resource, $uuid);
    if ($dir === false || (string)$uuid === '' || !is_dir($dir)) {
        return [];
    }
    $entries = [];
    foreach (scandir($dir, SCANDIR_SORT_DESCENDING) as $id) {
        if ($id[0] === '.') {
            continue;
        }
        $entry = json_decode((string)@file_get_contents($dir . '/' . $id), true);
        if (is_array($entry)) {
            $entries[] = ['id' => $id] + $entry;
        }
    }
    return $entries;
}

/** Records of a resource that were deleted and can be brought back: uuid => the delete. */
function history_deleted($resource)
{
    $dir = history_path($resource);
    if ($dir === false || !is_dir($dir)) {
        return [];
    }
    $deleted = [];
    foreach (scandir($dir) as $uuid) {
        if ($uuid === '.' || $uuid === '..' || data_exists($resource, $uuid)) {
            continue;
        }
        $ids = array_values(array_filter(scandir($dir . '/' . $uuid, SCANDIR_SORT_DESCENDING), fn($id) => $id[0] !== '.'));
        $entry = $ids === [] ? null : json_decode((string)@file_get_contents($dir . '/' . $uuid . '/' . $ids[0]), true);
        if (is_array($entry) && ($entry['action'] ?? '') === 'delete' && is_array($entry['record'] ?? null)) {
            $deleted[$uuid] = ['id' => $ids[0]] + $entry;
        }
    }
    uasort($deleted, fn($a, $b) => $b['at'] <=> $a['at']);
    return $deleted;
}

/**
 * The latest changes of a whole resource, newest first, each as in
 * history_list() plus `uuid` and `deleted`: true on the change that deleted a
 * record that is still gone.
 */
function history_resource_list($resource, $limit = 500)
{
    $dir = history_path($resource);
    if ($dir === false || !is_dir($dir)) {
        return [];
    }
    // The file names start with the time, so the newest are found without opening a file
    $ids = [];
    $latest = [];
    foreach (scandir($dir) as $uuid) {
        if ($uuid === '.' || $uuid === '..' || !is_dir($dir . '/' . $uuid)) {
            continue;
        }
        foreach (scandir($dir . '/' . $uuid) as $id) {
            if ($id[0] !== '.') {
                $ids[] = $id . '/' . $uuid;
                $latest[$uuid] = max($latest[$uuid] ?? '', $id);
            }
        }
    }
    rsort($ids, SORT_STRING);
    $entries = [];
    foreach (array_slice($ids, 0, $limit) as $key) {
        [$id, $uuid] = explode('/', $key, 2);
        $entry = json_decode((string)@file_get_contents($dir . '/' . $uuid . '/' . $id), true);
        if (!is_array($entry)) {
            continue;
        }
        $entries[] = ['id' => $id, 'uuid' => $uuid] + $entry + [
            'deleted' => ($entry['action'] ?? '') === 'delete' && $latest[$uuid] === $id && !data_exists($resource, $uuid),
        ];
    }
    return $entries;
}

/**
 * Puts a record back as it was before the given change. The whole record is
 * replaced, so a field added later is gone again; the replaced version is
 * itself kept as a change.
 *
 * @return array|false The restored record, false when the change is unknown or the record does not validate.
 */
function history_restore($resource, $uuid, $id)
{
    $dir = history_path($resource, $uuid);
    if ($dir === false || (string)$uuid === '' || preg_match('/^[0-9.]+-[a-f0-9]+$/', (string)$id) !== 1) {
        return false;
    }
    $entry = json_decode((string)@file_get_contents($dir . '/' . $id), true);
    if (!is_array($entry) || !is_array($entry['record'] ?? null)) {
        return false;
    }

    _data_lock($resource);
    try {
        $current = data_exists($resource, $uuid) ? data_read($resource, $uuid) : null;
        $record = $entry['record'];
        load_library('util');
        $record['_modified_by'] = md5_uuid(_data_actor());
        $record['_modified'] = time();
        if (!data_create($resource, $uuid, $record)) {
            return false;
        }
        if (is_array($current)) {
            _data_delete_stale_indexes($resource, $uuid, $current, $record);
        }
        return $record;
    } finally {
        _data_unlock($resource);
    }
}

/**
 * Removes everything kept about one record, for good. For a deleted record
 * that is the record itself.
 *
 * @return int Number of changes removed.
 */
function history_forget($resource, $uuid)
{
    $dir = history_path($resource, $uuid);
    if ($dir === false || (string)$uuid === '' || !is_dir($dir)) {
        return 0;
    }
    $removed = 0;
    foreach (array_diff(scandir($dir), ['.', '..']) as $id) {
        $removed += (int)@unlink($dir . '/' . $id);
    }
    @rmdir($dir);
    return $removed;
}

/** Removes every deleted record of a resource for good. Returns how many records. */
function history_forget_deleted($resource)
{
    $records = 0;
    foreach (array_keys(history_deleted($resource)) as $uuid) {
        $records += history_forget($resource, $uuid) > 0 ? 1 : 0;
    }
    return $records;
}

/**
 * Removes changes older than a number of days, and the folders they leave empty.
 *
 * @return int Number of changes removed (or that would be, on a dry run).
 */
function history_prune($days, $dry_run = false)
{
    $root = data_path('.state') . '/.history';
    if (!is_dir($root)) {
        return 0;
    }
    $cutoff = sprintf('%017.6F', time() - $days * 86400);
    $pruned = 0;
    foreach (array_diff(scandir($root), ['.', '..']) as $resource) {
        foreach (array_diff((array)@scandir("$root/$resource"), ['.', '..']) as $uuid) {
            foreach (array_diff((array)@scandir("$root/$resource/$uuid"), ['.', '..']) as $id) {
                // The name starts with the time of the change
                if (strcmp($id, $cutoff) < 0) {
                    $pruned += $dry_run ? 1 : (int)@unlink("$root/$resource/$uuid/$id");
                }
            }
            $dry_run || @rmdir("$root/$resource/$uuid");
        }
        $dry_run || @rmdir("$root/$resource");
    }
    return $pruned;
}
