<?php

$GLOBALS['SYSTEM']['data_base'] = $GLOBALS['SYSTEM']['file_base'] . 'ext/data';
$GLOBALS['SYSTEM']['data_error'] = null;

/**
 * Implements the [#data#] shortcode for loading data in templates.
 * 
 * @doc Loads data according to given parameters and sets variables for templates.
 * @doc
 * @doc Default output variable: `data.<resource>[.<uuid>]`.
 * @doc
 * @doc **Parameters:**
 * @doc - resource (*): resource name, optionally with `.uuid` for single records, e.g. `users`, `users.123`.
 * @doc - var: custom variable name for the loaded data.
 * @doc - op: operation to perform. Supported: `read` (default) or `list` (list UUIDs only).
 * @doc - sort: sorting instructions, e.g. `date|desc,title|asc`.
 * @doc - filter: filtering instructions, e.g. `published:1,status:new`.
 * @doc - search: search term to filter records by any matching field.
 * @doc - fields: comma-separated fields to keep in the result, e.g. `title,intro`. Applied after sort, search and filter.
 * @doc
 * @doc (*): Mandatory. 
 * @doc
 * @doc **Examples:**
 * @doc - `[#data users#]` loads all users into the frontend variable `data.users`.
 * @doc - `[#data users.123#]` loads the single user with UUID `123` into `data.users.123`.
 * @doc - `[#data users var=all_users#]` loads all users into the custom variable `all_users`.
 * @doc - `[#data projects sort=date|desc,title|asc#]` loads projects sorted by date descending, then title ascending.
 * @doc - `[#data blog-items filter=published:1#]` loads blog items whose boolean `published` field is enabled.
 * @doc - `[#data blog-items filter=published:1 fields=title,intro#]` loads only the title and intro of published blog items.
 */
function data_sc($params)
{
    if (empty($params)) {
        return;
    }
    $set = explode('.', (string)get_param_value($params, 'resource', current($params)));
    if (empty($set[0]) && count($set) > 1) {
        array_shift($set);
        $set[0] = '.' . $set[0];
    }
    $resource = $set[0];
    $uuid = count($set) > 1 ? $set[1] : get_param_value($params, 'uuid', null);
    $op = get_param_value($params, "op", "read");
    $var_id = get_param_value($params, "var", null);
    $function_name = sprintf("data_%s", $op);
    $result = call_user_func($function_name, $resource, $uuid);

    $sort = get_param_value($params, "sort", false);
    if ($sort) {
        load_library('data-sort');
        $result = data_sort_param($result, $sort);
    }

    $search = get_param_value($params, "search", false);
    if ($search !== false) {
        $result = data_search($result, $search);
    }

    $filter = get_param_value($params, "filter", false);
    if ($filter !== false) {
        $result = data_filter($result, $filter);
    }

    $fields = get_param_value($params, "fields", false);
    if ($fields !== false && $op === 'read' && is_array($result)) {
        $result = data_select_fields($result, $fields, (string)$uuid !== '');
    }

    $data_var = $var_id ?? data_var($resource, $uuid, $op);
    load_library('set');
    set_variable($data_var, $result);
    if ((string)$uuid !== '' && (string)$var_id !== '') {
        set_variable_dot($var_id, $result);
    }
}

/**
 * Keeps only the listed fields of one record or of every record in a list.
 *
 * @param array $data A single record, or an associative array of UUID => record.
 * @param string|array $fields Field names, as an array or comma-separated string.
 * @param bool $single Whether $data is a single record.
 * @return array The record(s) reduced to the listed fields that exist.
 */
function data_select_fields($data, $fields, $single = false)
{
    $keys = is_array($fields) ? $fields : explode(',', (string)$fields);
    $keys = array_flip(array_filter(array_map('trim', $keys), fn($key) => $key !== ''));
    if ($single) {
        return array_intersect_key($data, $keys);
    }
    foreach ($data as $uuid => $record) {
        if (is_array($record)) {
            $data[$uuid] = array_intersect_key($record, $keys);
        }
    }
    return $data;
}

/**
 * Builds the frontend variable name for data returned by the shortcode.
 *
 * @param string $resource The resource name (e.g., "users").
 * @param string $uuid Optional UUID for a single record.
 * @param string $op Operation name; defaults to "read". If not "read", appended to variable name.
 * @return string The constructed variable name, e.g., "data.users", "data.users.123", or "data.users.123.update".
 */
function data_var($resource, $uuid = "", $op = "read")
{
    return sprintf("data.%s%s%s", trim($resource, '.'), (string)$uuid === '' ? '' : '.' . $uuid, ($op === "read") ? "" : '.' . $op);
}

/**
 * Tells whether a resource name and record id stay inside the data directory
 * when used as a path.
 *
 * A resource may name a subfolder ("a/b") but not climb ("..") or start at
 * the root; an id is a single file name. Names starting with a dot (".meta",
 * ".files") are valid here.
 *
 * @param string $resource Resource name
 * @param string $uuid Record id (optional)
 * @return bool True if both are safe path segments
 */
function data_path_valid($resource, $uuid = '')
{
    $resource = (string)$resource;
    $uuid = (string)$uuid;

    if (strpbrk($resource . $uuid, "\\\0") !== false) {
        return false;
    }
    if (strpos($uuid, '/') !== false || $uuid === '.' || $uuid === '..') {
        return false;
    }
    if (strpos($resource, '/') === 0) {
        return false;
    }

    return !in_array('..', explode('/', $resource), true);
}

/**
 * Returns the filesystem path for a resource's data file.
 *
 * Depending on the resource's `.meta` settings, data files may be stored flat
 * (ext/data/(resource)/(uuid)) or split into subdirectories for scalability:
 * ext/data/(resource)/xx/yy/(uuid).
 *
 * @param string $resource Resource name, e.g. "users"
 * @param string $uuid Unique ID of the record (optional)
 * @return string Full filesystem path to the data file
 */
function data_path($resource, $uuid = '')
{
    if (!data_path_valid($resource, $uuid)) {
        // nothing can exist or be created below a regular file
        return __FILE__ . '/invalid' . ((string)$uuid === '' ? '' : '/invalid');
    }

    $base = $GLOBALS['SYSTEM']['data_base'] . '/' . $resource;

    if ((string)$uuid === '') {
        // return resource directory path if uuid is empty
        return $base;
    }

    $flat_path = "$base/$uuid";

    if (file_exists($flat_path)) {
        return $flat_path;
    }

    if (!file_exists("$base/.meta")) {
        return $flat_path;
    }

    $meta = data_meta($resource);

    if (empty($meta['splitdir']) || strlen($uuid) < 4) {
        return $flat_path;
    }

    // Split the uuid into subdirectories for better filesystem performance
    $id = strtolower($uuid);
    $sub1 = substr($id, 0, 2);
    $sub2 = substr($id, 2, 2);

    return "$base/$sub1/$sub2/$uuid";
}

/**
 * Checks if a resource or specific record exists.
 *
 * If only $resource is given, checks if the resource directory exists.
 * If $uuid is provided, checks if the specific record file exists.
 *
 * Example:
 * - data_exists('users') returns true if the 'users' resource directory exists.
 * - data_exists('users', '123') returns true if the user with UUID '123' exists.
 *
 * @param string $resource Resource name (e.g., 'users').
 * @param string $uuid Optional UUID of the record.
 * @return bool True if the resource or record exists, false otherwise.
 */
function data_exists($resource, $uuid = "")
{
    return file_exists(data_path($resource, $uuid));
}

function data_error_set($message, $detail = null)
{
    $GLOBALS['SYSTEM']['data_error'] = $message;
    $GLOBALS['SYSTEM']['data_error_detail'] = is_string($detail) ? $detail : null;
}

function data_error_get()
{
    return $GLOBALS['SYSTEM']['data_error'];
}

function data_error_detail_get()
{
    return $GLOBALS['SYSTEM']['data_error_detail'] ?? null;
}

function data_error_clear()
{
    $GLOBALS['SYSTEM']['data_error'] = null;
    $GLOBALS['SYSTEM']['data_error_detail'] = null;
}


/**
 * Returns a flat list of all UUIDs in a resource (no data loaded).
 *
 * Example: data_list('users') might return ['1', '2', 'a12f', ...]
 *
 * @param string $resource Resource name.
 * @return array List of UUID strings.
 */
function data_list($resource)
{
    $base = data_path($resource);
    $result = [];

    if (!is_dir($base)) {
        return $result;
    }

    return _data_list_recursive($base);
}

/**
 * Recursively scans directory and collects filenames representing UUIDs.
 *
 * @param string $dir Directory path to scan.
 * @param array &$result Accumulates UUIDs found.
 * @return array List of UUID strings.
 */
function _data_list_recursive($dir, &$result = [])
{
    foreach (scandir($dir) as $entry) {
        if ($entry[0] === '.') {
            continue;
        }
        $path = "$dir/$entry";
        if (is_file($path)) {
            $result[] = $entry;
        } elseif (is_dir($path) && strlen($entry) === 2) {
            // Only recurse into splitdir folders named with 2 chars
            _data_list_recursive($path, $result);
        }
    }
    return $result;
}

/**
 * Reads data from a resource.
 *
 * If no UUID is provided, returns all records via `_data_read_all`.
 * If UUID is provided, returns the decoded JSON data for that record.
 * Optionally, specific fields can be requested.
 *
 * Examples:
 * - `data_read('users')` returns all user records.
 * - `data_read('users', '123')` returns the user record with UUID '123'.
 * - `data_read('users', '123', 'email')` returns only the 'email' field of the user.
 * - `data_read('users', '123', ['email', 'name'])` returns an array with 'email' and 'name' fields.
 *
 * @param string $resource Resource name.
 * @param string|null $uuid Optional UUID of a single record.
 * @param string|array|null $field Optional field name or array of fields to filter.
 * @return array|string|null Decoded record data, filtered field(s), or null if not found.
 */
function data_read($resource, $uuid = null, $field = null)
{
    if ((string)$uuid === '') {
        return _data_read_all($resource, $field);
    }

    $file = data_path($resource, $uuid);

    if (!file_exists($file) || is_dir($file)) {
        return null;
    }

    $contents = file_get_contents($file);
    $result = json_decode($contents, true);

    if (!isset($result['_modified'])) {
        $result['_modified'] = filemtime($file);
    }
    if (!isset($result['_created'])) {
        $result['_created'] = filectime($file);
    }

    if (!empty($field)) {
        if (is_string($field) && isset($result[$field])) {
            return $result[$field];
        } elseif (is_array($field)) {
            $filtered_result = [];
            foreach ($field as $f) {
                $filtered_result[$f] = $result[$f] ?? false;
            }
            unset($result);
            return $filtered_result;
        }
        return null;
    }

    return $result;
}

/**
 * Reads all records for a given resource.
 *
 * Uses cache if available and valid.
 * Reads recursively from resource directory and subdirectories.
 *
 * @param string $resource Resource name.
 * @param mixed $setting Optional settings (e.g., fields filter).
 * @return array Associative array of UUID => record data.
 */
function _data_read_all($resource, $setting = null)
{
    $result = [];
    $base = data_path($resource);

    if (!is_dir($base)) {
        return $result;
    }

    $cache = _data_read_cache('_data_read_all', $resource, $setting);
    if ($cache !== false) {
        return $cache;
    }

    $result = _data_read_all_recursive($base, $resource, $setting);
    _data_write_cache('_data_read_all', $resource, $setting, $result);
    return $result;
}

/**
 * Recursively reads all data files in a directory and its 2-char subdirectories.
 *
 * @param string $dir Directory path to scan.
 * @param string $resource Resource name.
 * @param mixed $setting Optional settings (e.g., fields filter).
 * @param array &$result Accumulator for results.
 * @return array Associative array of UUID => record data.
 */
function _data_read_all_recursive($dir, $resource, $setting, &$result = [])
{
    foreach (scandir($dir) as $entry) {
        if ($entry[0] === '.') {
            continue;
        }
        $path = "$dir/$entry";
        if (is_file($path)) {
            $result[$entry] = data_read($resource, $entry, $setting);
        } elseif (is_dir($path) && strlen($entry) === 2) {
            // Recurse only into splitdir subfolders named with 2 chars
            _data_read_all_recursive($path, $resource, $setting, $result);
        }
    }
    return $result;
}

/**
 * Generates a cache file path for a given operation, resource, and options.
 *
 * The cache key is an MD5 hash of the operation name, resource, and serialized options.
 * The cache directory is created if it does not exist.
 *
 * @param string $op Operation name (e.g., '_data_read_all').
 * @param string $resource Resource name.
 * @param mixed $options Optional parameters affecting cache key.
 * @return string Full path to the cache file.
 */
function _data_cache_file($op, $resource, $options)
{
    $cache_key = md5($op . $resource . serialize($options));
    $cache_dir = $GLOBALS['SYSTEM']['file_base'] . 'ext/data/.tmp/cache/_data';

    if (!is_dir($cache_dir)) {
        @mkdir($cache_dir, 0755, true);
    }

    return $cache_dir . '/' . $cache_key;
}

/**
 * Atomically replaces a file with complete contents.
 *
 * The temporary file is created beside the destination so rename() publishes
 * it atomically on the same filesystem. Readers therefore see either the old
 * complete file or the new complete file, never an in-progress write.
 *
 * @param string $file Destination file path.
 * @param string $contents Complete file contents.
 * @return int|false Number of bytes written, or false on failure.
 */
function _data_write_file_atomically($file, $contents)
{
    $directory = dirname($file);
    if (!is_dir($directory)) {
        return false;
    }

    $temporary = tempnam($directory, '.' . basename($file) . '.tmp.');
    if ($temporary === false) {
        return false;
    }

    $bytes = @file_put_contents($temporary, $contents, LOCK_EX);
    if ($bytes === false || $bytes !== strlen($contents)) {
        @unlink($temporary);
        return false;
    }

    $permissions = is_file($file) ? (fileperms($file) & 0777) : 0644;
    @chmod($temporary, $permissions);
    if (!@rename($temporary, $file)) {
        @unlink($temporary);
        return false;
    }

    return $bytes;
}

/**
 * Reads cached data for a given operation and resource if cache is valid.
 *
 * Checks if the cache file exists and if its modification time is newer than
 * the resource’s last modification time. If the cache is outdated or missing,
 * returns false.
 *
 * @param string $op Operation name (e.g., '_data_read_all').
 * @param string $resource Resource name.
 * @param mixed $setting Optional settings used for cache key.
 * @return mixed Cached data decoded from JSON, or false if cache is invalid.
 */
function _data_read_cache($op, $resource, $setting)
{
    $modified = data_modified($resource);
    $cache_file = _data_cache_file($op, $resource, $setting);

    if (!file_exists($cache_file)) {
        return false;
    }

    $cache_time = filemtime($cache_file);

    // Times have whole seconds: a cache written in the second of the last change may have missed it.
    if ($cache_time <= $modified) {
        @unlink($cache_file);
        return false;
    }

    $contents = file_get_contents($cache_file);
    return json_decode($contents, true);
}

/**
 * Writes data to a cache file for a given operation and resource.
 *
 * The data is JSON encoded with Unicode characters unescaped.
 *
 * @param string $op Operation name (e.g., '_data_read_all').
 * @param string $resource Resource name.
 * @param mixed $setting Optional settings affecting cache key.
 * @param mixed $content Data to cache (will be JSON encoded).
 * @return int|false Number of bytes written, or false on failure.
 */
function _data_write_cache($op, $resource, $setting, $content)
{
    $cache_file = _data_cache_file($op, $resource, $setting);
    $json_data = json_encode($content, JSON_UNESCAPED_UNICODE);
    return _data_write_file_atomically($cache_file, $json_data);
}

/**
 * Deletes the cache file for a given operation and resource.
 *
 * @param string $op Operation name.
 * @param string $resource Resource name.
 * @param mixed $options Optional parameters for cache key.
 * @return void
 */
function _data_clear_cache($op, $resource, $options = null)
{
    $cache_file = _data_cache_file($op, $resource, $options);
    if (file_exists($cache_file)) {
        @unlink($cache_file);
    }
}

/**
 * Throws away every cached query result; the next read of a resource builds
 * its cache again. For a record that was edited in place by hand, which the
 * folder check does not see. The folder itself stays, with its owner.
 *
 * @return int Number of cache files removed.
 */
function data_cache_clear()
{
    $removed = 0;
    foreach (glob($GLOBALS['SYSTEM']['file_base'] . 'ext/data/.tmp/cache/_data/*') ?: [] as $file) {
        if (is_file($file) && @unlink($file)) {
            $removed++;
        }
    }
    return $removed;
}

/**
 * Checks if an index exists for a given resource, index name, and index UUID.
 *
 * @param string $resource Resource name.
 * @param string $index_name Name of the index field.
 * @param string $index_uuid UUID representing the indexed value.
 * @return bool True if the index path exists, false otherwise.
 */
function data_indexed($resource, $index_name, $index_uuid)
{
    $path = _data_index_path($resource, $index_name, $index_uuid);
    return file_exists($path);
}

/**
 * Reads all records indexed by a specific index value.
 *
 * Looks up the index directory for files pointing to records matching
 * the given index UUID. Invalid index files (not matching current record data)
 * are removed.
 *
 * @param string $resource Resource name.
 * @param string $index_name Name of the indexed field.
 * @param string $index_uuid MD5 UUID of the index value.
 * @return array Associative array of record UUID => record data.
 */
function data_read_index($resource, $index_name, $index_uuid)
{
    $result = [];
    $base_path = data_path($resource);
    $index_path = _data_index_path($resource, $index_name, $index_uuid);

    if (!is_dir($index_path)) {
        return $result;
    }

    $index_files = @scandir($index_path);
    if (!is_array($index_files)) {
        return $result;
    }

    load_library('util');
    load_library('log');

    foreach ($index_files as $index_file) {
        if ($index_file[0] === '.') {
            continue;
        }

        $file_path = $base_path . '/' . $index_file;
        if (is_dir($file_path)) {
            continue;
        }

        $item = data_read($resource, $index_file);

        if (isset($item[$index_name]) && in_array($index_uuid, data_index_uuids($item[$index_name]), true)) {
            $result[$index_file] = $item;
        } else {
            // Remove stale index entry
            log_system('removed index ' . $index_path . '/' . $index_file . ': not matching ' . $index_name);
            @unlink($index_path . '/' . $index_file);
        }
    }

    return $result;
}

function data_index_uuids($value)
{
    load_library('util');

    $result = [];
    $stack = is_array($value) ? $value : [$value];
    foreach ($stack as $item) {
        if (is_array($item)) {
            foreach (data_index_uuids($item) as $nested) {
                $result[$nested] = true;
            }
            continue;
        }
        if (!is_scalar($item) || (string)$item === '') {
            continue;
        }
        $result[md5_uuid((string)$item)] = true;
    }

    return array_keys($result);
}


/**
 * Recursively merges two arrays, distinctively.
 *
 * Unlike PHP’s native array_merge_recursive, this function:
 * - Merges only associative arrays recursively.
 * - Overwrites non-associative or scalar values instead of merging into arrays.
 *
 * @param array $array1 Base array to merge into.
 * @param array $array2 Array to merge from.
 * @return array Resulting merged array.
 */
function array_merge_recursive_distinct(array &$array1, array &$array2)
{
    $merged = $array1;

    foreach ($array2 as $key => &$value) {
        if (
            is_array($value) &&
            $value !== [] &&
            isset($merged[$key]) &&
            is_array($merged[$key]) &&
            array_keys($value) !== range(0, count($value) - 1) // associative check
        ) {
            $merged[$key] = array_merge_recursive_distinct($merged[$key], $value);
        } else {
            $merged[$key] = $value;
        }
    }

    return $merged;
}

/**
 * Takes the write lock of a resource, so that reading a record, merging and
 * writing it back is one step for every process on the same data folder.
 * A process that holds the lock may take it again.
 *
 * @param string $resource Resource name.
 * @return bool False when the lock file cannot be opened; the write then goes ahead without it.
 */
function _data_lock($resource)
{
    $key = (string)$resource;
    if (isset($GLOBALS['SYSTEM']['data_locks'][$key])) {
        $GLOBALS['SYSTEM']['data_locks'][$key]['depth']++;
        return true;
    }

    // Under .state, which no project tracks in git
    $dir = data_path('.state') . '/.locks';
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    $file = $dir . '/' . preg_replace('/[^A-Za-z0-9._-]/', '_', $key) . '.lock';
    $handle = @fopen($file, 'c');
    if ($handle === false) {
        // Another user's lock file can still be locked through a read handle
        $handle = @fopen($file, 'r');
    }
    if ($handle === false || !flock($handle, LOCK_EX)) {
        if (is_resource($handle)) {
            fclose($handle);
        }
        if (empty($GLOBALS['SYSTEM']['data_lock_reported'])) {
            // Once per request: the write still happens, without the lock
            $GLOBALS['SYSTEM']['data_lock_reported'] = true;
            error_log('Nimbly: no write lock for ' . $key . ', cannot open ' . $file);
        }
        return false;
    }

    $GLOBALS['SYSTEM']['data_locks'][$key] = ['handle' => $handle, 'depth' => 1];
    return true;
}

/**
 * Gives a resource's write lock back. Lifecycle events that were held while
 * a lock was taken run once this process holds none.
 *
 * @param string $resource Resource name.
 * @return void
 */
function _data_unlock($resource)
{
    $key = (string)$resource;
    if (!isset($GLOBALS['SYSTEM']['data_locks'][$key])) {
        return;
    }
    if (--$GLOBALS['SYSTEM']['data_locks'][$key]['depth'] > 0) {
        return;
    }
    $handle = $GLOBALS['SYSTEM']['data_locks'][$key]['handle'];
    unset($GLOBALS['SYSTEM']['data_locks'][$key]);
    flock($handle, LOCK_UN);
    fclose($handle);

    if (!empty($GLOBALS['SYSTEM']['data_locks']) || empty($GLOBALS['SYSTEM']['data_events'])) {
        return;
    }
    $events = $GLOBALS['SYSTEM']['data_events'];
    $GLOBALS['SYSTEM']['data_events'] = [];
    load_library('event');
    foreach ($events as $event) {
        event_resource_lifecycle(...$event);
    }
}

/**
 * Emits a resource lifecycle event. While this process holds a write lock the
 * event waits, so a handler never runs inside it.
 */
function _data_lifecycle($action, $resource, $uuid, $data)
{
    if (!empty($GLOBALS['SYSTEM']['data_locks'])) {
        $GLOBALS['SYSTEM']['data_events'][] = [$action, $resource, $uuid, $data];
        return;
    }
    load_library('event');
    event_resource_lifecycle($action, $resource, $uuid, $data);
}

/** Who is writing: the user an agent works for, or the logged-in user. */
function _data_actor()
{
    if (!empty($GLOBALS['SYSTEM']['data_actor']['username'])) {
        return (string)$GLOBALS['SYSTEM']['data_actor']['username'];
    }
    load_library('username');
    return (string)username_get();
}

/**
 * Updates a specific data object file or multiple objects.
 *
 * If `$uuid` is empty, updates multiple records given in `$data_update_ls`.
 * Otherwise, merges the existing record with the update data and writes it.
 * Handles updates to the primary key (PK) field by renaming the file accordingly.
 * Automatically updates `_modified` and `_modified_by` fields.
 *
 * @param string $resource Resource name.
 * @param string $uuid UUID of the record to update; empty string to update multiple.
 * @param array $data_update_ls Data to update, or array of multiple updates if `$uuid` is empty.
 * @return array|false Updated data array on success, false on failure.
 */
function data_update($resource, $uuid, $data_update_ls)
{
    data_error_clear();

    if (empty($data_update_ls)) {
        return false;
    }

    if ((string)$uuid === '') { // update multiple
        if (!data_exists($resource)) {
            return false;
        }
        $result = [];
        foreach ($data_update_ls as $pk => $updates) {
            $id = empty($pk) ? $updates['uuid'] : $pk;
            if (empty($id)) {
                continue;
            }
            $r = data_update($resource, $id, $updates);
            if (!is_array($r)) {
                return false;
            }
            $result[$id] = $r;
        }
        return $result;
    }

    _data_lock($resource);
    try {
        if (!data_exists($resource, $uuid)) {
            $meta = data_meta($resource);
            if (empty($meta['upsert'])) {
                return false;
            }
            data_create($resource, $uuid, []);
            if (!data_exists($resource, $uuid)) {
                return false;
            }
        }

        $data_ls = data_read($resource, $uuid);

        if (empty($data_ls)) {
            $data_merged_ls = $data_update_ls;
        } else {
            $data_merged_ls = array_merge_recursive_distinct($data_ls, $data_update_ls);
        }

        $meta = data_meta($resource);
        // Update modification metadata
        load_library('util');
        $data_merged_ls['_modified_by'] = md5_uuid(_data_actor());
        $data_merged_ls['_modified'] = time();
        if (_data_validate($resource, $uuid, $data_merged_ls) !== true) {
            return false;
        }

        if (data_create($resource, $uuid, $data_merged_ls)) {
            if (!empty($data_ls)) {
                _data_delete_stale_indexes($resource, $uuid, $data_ls, $data_merged_ls);
            }
            return $data_merged_ls;
        }

        return false;
    } finally {
        _data_unlock($resource);
    }
}


/**
 * Creates or rewrites a data object file.
 *
 * Writes the JSON-encoded $data_ls to the file identified by $resource and $uuid.
 * Automatically manages creation/modification metadata.
 * Updates indexes defined in the resource metadata.
 * Emits configured resource lifecycle events on success.
 *
 * @param string $resource Resource name.
 * @param string $uuid UUID of the record to create or update.
 * @param array $data_ls Data array to store.
 * @return bool True on success, false on failure.
 */
function data_create($resource, $uuid, $data_ls)
{
    data_error_clear();
    $path = data_path($resource, $uuid);

    if ($uuid === null || $uuid === '') {
        if (!file_exists($path) && !mkdir($path, 0750, true) && !is_dir($path)) {
            return false;
        }
        return is_dir($path);
    }

    $dir = dirname($path);
    if (!file_exists($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
        return false;
    }

    $file = $path;
    $exists = file_exists($file);

    $meta = $uuid === '.meta' && is_array($data_ls) ? $data_ls : data_meta($resource);
    _data_lock($resource);
    try {

        if (_data_validate($resource, $uuid, $data_ls) !== true) {
            return false;
        }

        if (isset($data_ls['form-key'])) {
            unset($data_ls['form-key']);
        }

        if (!isset($data_ls['_created_by'])) {
            load_library('util');
            $data_ls['_created_by'] = md5_uuid(_data_actor());
        }

        if (!isset($data_ls['_created'])) {
            $data_ls['_created'] = time();
            $data_ls['_modified'] = time();
        }

        $data_ls['uuid'] = $uuid;

        _data_history($exists ? 'update' : 'create', $resource, $uuid, $meta, $data_ls);

        $json_data = json_encode($data_ls, JSON_UNESCAPED_UNICODE);
        if (_data_write_file_atomically($file, $json_data) !== false) {
            touch($dir); // Update directory modification time to signal change and invalidate caches
            _data_clear_cache('_data_read_all', $resource);

            if (isset($meta['index']) && is_array($meta['index'])) {
                load_library('util');
                foreach ($meta['index'] as $index_name) {
                    if (empty($data_ls[$index_name])) {
                        continue;
                    }
                    foreach (data_index_uuids($data_ls[$index_name]) as $index_uuid) {
                        _data_create_index($resource, $file, $index_name, $index_uuid);
                    }
                }
            }

            if ($uuid !== '.meta') {
                _data_lifecycle($exists ? 'update' : 'create', $resource, $uuid, $data_ls);
            }
            return true;
        }
    } finally {
        _data_unlock($resource);
    }

    return false;
}

/** Removes the index entries of values a record had before a write and no longer has. */
function _data_delete_stale_indexes($resource, $uuid, $old_ls, $new_ls)
{
    $meta = data_meta($resource);
    if (!isset($meta['index']) || !is_array($meta['index'])) {
        return;
    }
    $file = data_path($resource, $uuid);
    foreach ($meta['index'] as $index_name) {
        if (empty($old_ls[$index_name])) {
            continue;
        }
        $old_index_uuids = data_index_uuids($old_ls[$index_name]);
        $new_index_uuids = data_index_uuids($new_ls[$index_name] ?? '');
        foreach (array_diff($old_index_uuids, $new_index_uuids) as $old_index_uuid) {
            _data_delete_index($resource, $file, $index_name, $old_index_uuid);
        }
    }
}

/**
 * Keeps the change that is about to happen in the record history, when the
 * resource has one and a person or an agent makes the change. What the site
 * writes by itself (scheduled tasks, webhooks, visitors' forms) is not kept.
 * Called inside the write lock, before the file changes.
 *
 * @param array|null $new_ls The record about to be written; null for a delete.
 */
function _data_history($action, $resource, $uuid, $meta, $new_ls = null)
{
    if ($uuid === '.meta' || _data_actor() === 'anonymous') {
        return;
    }
    require_once __DIR__ . '/history.php';
    if (!history_enabled($resource, $meta)) {
        return;
    }
    $old_ls = $action === 'create' ? null : json_decode((string)@file_get_contents(data_path($resource, $uuid)), true);
    if ($action === 'update' && history_same_record($old_ls, $new_ls)) {
        return;
    }
    history_capture($action, $resource, $uuid, $old_ls, $action === 'update' ? history_changed_fields($old_ls, $new_ls) : []);
}

/** Keeps every record of a resource that is about to be emptied or removed. */
function _data_history_all($resource)
{
    $meta = data_meta($resource);
    foreach (data_list($resource) ?: [] as $uuid) {
        _data_history('delete', $resource, $uuid, $meta);
    }
}

function _data_index_path($resource, $index_name, $index_uuid)
{
    $base = data_path($resource) . '/.index/' . $index_name . '/';
    $meta = data_meta($resource);
    if (!empty($meta['splitdir']) && strlen($index_uuid) >= 4) {
        $id = strtolower($index_uuid);
        $base .= substr($id, 0, 2) . '/' . substr($id, 2, 2) . '/';
    }
    return $base . $index_uuid . '/';
}

/**
 * Creates an index entry by creating an empty file as a virtual link.
 *
 * This function creates a directory structure for the index name and UUID,
 * then creates an empty file with the same basename as the original data file.
 * This serves as a virtual link for indexing without using symbolic links.
 *
 * @param string $file Full path to the original data file.
 * @param string $index_name Name of the index field.
 * @param string $index_uuid UUID derived from the index field value.
 * @return void
 */
function _data_create_index($resource, $file, $index_name, $index_uuid)
{
    $path = _data_index_path($resource, $index_name, $index_uuid);
    if (!file_exists($path)) {
        @mkdir($path, 0750, true);
    }
    // Create an empty file to act as a virtual link to the indexed record
    touch($path . basename($file));
}

/**
 * Deletes an index entry file linking a data record to an index.
 *
 * The index is represented as a file inside:
 * (resource directory)/(index name)/[(splitdir)/](index uuid)/(record filename)
 *
 * @param string $resource Resource name.
 * @param string $file Full path to the original data file.
 * @param string $index_name Name of the index field.
 * @param string $index_uuid UUID of the index value.
 * @return int Returns 1 if the index file was deleted, 0 if it did not exist.
 */
function _data_delete_index($resource, $file, $index_name, $index_uuid)
{
    $path = _data_index_path($resource, $index_name, $index_uuid) . basename($file);
    if (!file_exists($path)) {
        return 0;
    }
    return (int) unlink($path);
}

/**
 * Deletes resource/id or entire resource
 * 
 * If $uuid is given (not empty), deletes only that record.
 * If $uuid is omitted or empty, deletes the entire resource directory including the .meta file.
 * 
 * @param string $resource Resource name.
 * @param string|null $uuid UUID of the record to delete (optional).
 * @return int Number of deleted items (files/directories).
 */
function data_delete($resource, $uuid = null)
{
    $result = 0;
    $dir = data_path($resource); // resource directory path

    if (!file_exists($dir)) {
        return $result;
    }

    if ((string)$uuid !== '') {
        // Delete a single record file
        _data_lock($resource);
        try {
            $file = data_path($resource, $uuid);
            if (!file_exists($file)) {
                return $result;
            }
            $meta = data_meta($resource);
            if (isset($meta['index']) && is_array($meta['index'])) {
                $data_ls = data_read($resource, $uuid);
                load_library('util');
                foreach ($meta['index'] as $index_name) {
                    if (empty($data_ls[$index_name])) {
                        continue;
                    }
                    foreach (data_index_uuids($data_ls[$index_name]) as $index_uuid) {
                        $result += _data_delete_index($resource, $file, $index_name, $index_uuid);
                    }
                }
            }
            $data_ls = $data_ls ?? data_read($resource, $uuid);
            _data_history('delete', $resource, $uuid, $meta);
            $result += (int)unlink($file);
            if ($result > 0) {
                // Unlike data_create()/data_update(), a delete never rewrites a
                // surviving file, so it produces no newer max-mtime for
                // _data_read_all()'s cache-vs-data_modified() comparison to
                // detect. touch() alone is not reliable here: filemtime() has
                // 1-second resolution, and a create-then-delete within the same
                // second (e.g. a script, or two quick requests) leaves the
                // touched dir's mtime equal to — not greater than — the cache
                // file's, so the stale-check ("cache_time < modified") misses
                // it. Clear the cache file directly instead.
                touch($dir);
                _data_clear_cache('_data_read_all', $resource);
            }
            if ($result > 0 && $uuid !== '.meta') {
                _data_lifecycle('delete', $resource, $uuid, $data_ls);
            }
            return $result;
        } finally {
            _data_unlock($resource);
        }
    }

    // Delete entire resource: all files including .meta, and resource directory
    load_library('util');
    _data_history_all($resource);
    $files = @scandir($dir);
    if (!is_array($files)) {
        return $result;
    }
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        $path = $dir . '/' . $file;
        if (is_file($path)) {
            $result += (int)unlink($path);
        } elseif (is_dir($path)) {
            $result++; // count this directory deletion
            @rrmdir($path);
        }
    }
    @rmdir($dir);
    _data_clear_cache('_data_read_all', $resource);
    return $result;
}

/**
 * Deletes all records and indexes inside a resource directory,
 * but keeps the resource directory and .meta file intact.
 *
 * @param string $resource Resource name.
 * @return int Number of deleted files and directories.
 */
function data_empty($resource)
{
    $result = 0;
    $dir = data_path($resource);

    if (!file_exists($dir)) {
        return $result;
    }

    load_library('util');
    _data_history_all($resource);

    $files = @scandir($dir);
    if (!is_array($files)) {
        return $result;
    }

    foreach ($files as $file) {
        if ($file === '.' || $file === '..' || $file === '.meta') {
            continue; // keep .meta and special dirs
        }
        $path = $dir . '/' . $file;
        if (is_file($path)) {
            $result += (int)unlink($path);
        } elseif (is_dir($path)) {
            $result++; // count directory deletion
            @rrmdir($path);
        }
    }

    return $result;
}

/**
 * Filters an array of records by field conditions.
 *
 * Supports multiple filters separated by commas, with multiple allowed values separated by '||'.
 * Special filter values:
 * - (exists): passes if the field exists in the record.
 * - (num): passes if the field exists and is numeric.
 * - !value: negated match, passes if the field value is not 'value'.
 *
 * Example filter strings:
 * - permission:yes,status:new||todo
 * - published:(exists),count:(num)
 *
 * @param array $data Array of records (associative arrays) to filter.
 * @param string $filter_str Filter string, e.g. "permission:yes,status:new||todo".
 * @return array Filtered array of records.
 */
function data_filter($data, $filter_str)
{
    if (empty($data)) {
        return [];
    }

    $filter_str_parts = explode(',', $filter_str);
    $filters = [];
    foreach ($filter_str_parts as $f) {
        $parts = explode(':', $f, 2);
        if (count($parts) !== 2) {
            continue;
        }
        $filters[trim($parts[0])] = trim($parts[1]);
    }

    foreach ($data as $key => $record) {
        foreach ($filters as $field => $val) {
            $allowed_values = explode('||', $val);
            $passed = false;
            foreach ($allowed_values as $v) {
                if ($v === '(exists)' && isset($record[$field])) {
                    $passed = true;
                    break;
                }
                if ($v === '(num)' && isset($record[$field]) && is_numeric($record[$field])) {
                    $passed = true;
                    break;
                }
                if (isset($record[$field]) && $record[$field] == $v) {
                    $passed = true;
                    break;
                }
                if ($v !== '' && $v[0] === '!' && isset($record[$field]) && $record[$field] != substr($v, 1)) {
                    $passed = true;
                    break;
                }
            }
            if (!$passed) {
                unset($data[$key]);
                break; // no need to check other filters if one fails
            }
        }
    }

    return $data;
}

/**
 * Lists all resource types (directories) in the data base with counts of their entities.
 *
 * @return array Associative array of resources with keys 'name' and 'count'.
 */
function data_resources_list()
{
    $base = $GLOBALS['SYSTEM']['data_base'];
    $rs = @scandir($base);
    $result = array();
    if (is_array($rs)) {
        foreach ($rs as $r) {
            if ($r[0] === '.') {
                continue;
            }
            $dir = data_path($r); // Using data_path for consistency
            if (!is_dir($dir)) {
                continue;
            }
            $entities = data_list($r);
            $result[$r] = array(
                "name" => $r,
                "count" => count($entities)
            );
        }
    }
    return $result;
}

/**
 * Returns metadata configuration for a resource.
 * Uses static cache to avoid repeated disk reads.
 * If no metadata file exists, builds default metadata and creates `.meta`.
 *
 * When the resource's own `.meta` defines no fields and a `$uuid` is given,
 * falls back to the record's own embedded `_fields`/`_languages` keys, so a
 * record can carry its own schema instead of requiring an external `.meta`.
 *
 * @param string $resource Resource name.
 * @param string|null $uuid Optional record UUID, for the embedded-schema fallback.
 * @return array Resource metadata array.
 */
function data_meta($resource, $uuid = null)
{
    static $meta_result = [];
    $cache_key = $resource . '|' . $uuid;
    if (!empty($meta_result[$cache_key])) {
        $meta = $meta_result[$cache_key];
        if (($meta['languages'] ?? null) === 'site') {
            $languages = data_lookup('.config', 'site', 'languages', ['en']);
            $meta['languages'] = is_array($languages) ? array_values($languages) : ['en'];
        }
        return $meta;
    }
    if (data_exists($resource, ".meta")) {
        $meta = data_read($resource, ".meta");
    } else {
        $meta = ['fields' => false];
        data_create($resource, ".meta", $meta);
    }
    if (empty($meta['fields']) && $uuid !== null && data_exists($resource, $uuid)) {
        $record = data_read($resource, $uuid);
        if (!empty($record['_fields']) && is_array($record['_fields'])) {
            $meta['fields'] = $record['_fields'];
            if (!empty($record['_languages'])) {
                $meta['languages'] = $record['_languages'];
            }
        }
    }
    $meta_result[$cache_key] = $meta;
    if (($meta['languages'] ?? null) === 'site') {
        $languages = data_lookup('.config', 'site', 'languages', ['en']);
        $meta['languages'] = is_array($languages) ? array_values($languages) : ['en'];
    }
    return $meta;
}

/**
 * Returns the last modification time of a resource or specific record.
 *
 * @param string $resource Resource name.
 * @param string|null $uuid Optional UUID of the record.
 * @return int Unix timestamp of last modification, or 0 if not found.
 */
function data_modified($resource, $uuid = null)
{
    $path = data_path($resource, $uuid);
    if (!file_exists($path)) {
        return 0;
    }

    if ($uuid === null && is_dir($path)) {
        return _data_modified_recursive($path);
    }

    return filemtime($path);
}

/**
 * Returns the newest modification time of the folders that make up a resource.
 *
 * A record is never rewritten in place: the data API and Git both put a new
 * file where the old one was, which changes the time of the folder it is in.
 * So the folders tell when a collection changed, and the check costs one
 * lookup per folder instead of one per record, which matters on a network
 * volume. `.meta` is edited by hand and is looked at itself. A record edited
 * in place by hand is not seen until the next write to the resource, or until
 * the cache is cleared (data_cache_clear(), `./nimbly data:cache:clear`).
 *
 * @param string $dir Resource or split-directory path.
 * @return int Newest Unix modification timestamp.
 */
function _data_modified_recursive($dir)
{
    $modified = max(filemtime($dir) ?: 0, @filemtime("$dir/.meta") ?: 0);

    foreach (scandir($dir) as $entry) {
        if (strlen($entry) !== 2 || $entry[0] === '.') {
            continue;
        }

        $path = "$dir/$entry";
        if (is_dir($path)) {
            $modified = max($modified, _data_modified_recursive($path));
        }
    }

    return $modified;
}

/**
 * Creates a new resource by ensuring the `.meta` file exists.
 * 
 * @param string $resource Resource name.
 * @param array $meta Metadata for the resource.
 * @return bool True if resource exists or was successfully created, false otherwise.
 */
function data_create_resource($resource, $meta)
{
    if (data_exists($resource, ".meta")) {
        return true;
    }
    return data_create($resource, ".meta", $meta) === true;
}

/**
 * Excludes records from an array where a specific field matches a given value.
 *
 * @param array $records Array of associative arrays (records).
 * @param string $key Field name to check in each record.
 * @param mixed $value Value to exclude records by (strict equality).
 * @return array Filtered array of records without the excluded ones.
 */
function data_exclude($records, $key, $value)
{
    if (!empty($records)) {
        foreach ($records as $i => $r) {
            if (isset($r[$key]) && $r[$key] === $value) {
                unset($records[$i]);
            }
        }
    }
    return $records;
}

function data_search($data, $term, $level = 0)
{
    if ($level === 0) {
        $result = array();
    }
    if (empty($term) || strlen($term) < 2) {
        return $result;
    }
    foreach ($data as $key => $record) {
        $score = 0;
        foreach ($record as $field_name => $field_value) {
            if (is_scalar($field_value)) {
                $v = trim($field_value);
                $p = stripos($v, $term);
                if ($p === false) {
                    // nothing
                } else if ($p === 0) {
                    $score += 2;
                } else {
                    $score += 1;
                }
            } else if (is_array($field_value)) {
                $score += data_search($field_value, $term, $level + 1);
            }
            if ($score > 0 && $level === 0) {
                $result[$key] = $record;
                $result[$key]['search_score'] = $score;
            } else if ($score > 0 && $level > 0) {
                return $score;
            }
        }
    }
    if ($level === 0) {
        load_library('data-sort');
        $result = data_sort_numeric($result, 'search_score', SORT_DESC);
        return $result;
    }
    return 0;
}

function _data_validate($resource, $uuid, &$data_ls)
{
    if ($uuid === '.meta') {
        return true;
    }
    $meta = data_meta($resource);
    if (_data_validate_field_definitions($meta, $data_ls) !== true) {
        data_error_set('VALIDATION_FAILED', data_error_detail_get());
        return false;
    }
    if (!empty($meta['validate'])) {
        $validation_rules = $meta['validate'];
        foreach ($validation_rules as $rule => $fields) {
            foreach ($fields as $field) {
                if ($rule === 'natural-short-text') {
                    if (_validate_natural_short_text($data_ls[$field] ?? '') !== true) {
                        data_error_set('VALIDATION_FAILED', $field . ':natural-short-text');
                        return false;
                    }
                } else if ($rule === 'natural-text') {
                    if (_validate_natural_text($data_ls[$field] ?? '') !== true) {
                        data_error_set('VALIDATION_FAILED', $field . ':natural-text');
                        return false;
                    }
                }
            }
        }
    }

    if (!empty($meta['unique']) && _data_validate_unique($resource, $uuid, $data_ls, $meta['unique']) !== true) {
        data_error_set('RESOURCE_EXISTS');
        return false;
    }

    if (!empty($meta['validate_library']) && !empty($meta['validate_function'])) {
        load_library((string)$meta['validate_library']);
        $validator = (string)$meta['validate_function'];
        if (!function_exists($validator) || $validator($resource, $uuid, $data_ls) !== true) {
            if (data_error_get() === null) {
                data_error_set('VALIDATION_FAILED', 'custom');
            }
            return false;
        }
    }

    return true;
}

function _data_validate_field_definitions($meta, $data_ls)
{
    $fields = is_array($meta) && isset($meta['fields']) && is_array($meta['fields'])
        ? $meta['fields']
        : [];
    foreach ($fields as $field => $definition) {
        $has_value = array_key_exists($field, $data_ls) && _data_value_is_present($data_ls[$field]);
        if (!empty($definition['required']) && !$has_value) {
            $GLOBALS['SYSTEM']['data_error_detail'] = $field . ':required';
            return false;
        }
        if (!$has_value || (!isset($definition['min']) && !isset($definition['max']))) {
            continue;
        }
        if (in_array($definition['type'] ?? '', ['group', 'gallery'], true) && is_array($data_ls[$field])) {
            $value_count = count($data_ls[$field]);
            if (isset($definition['min']) && $value_count < (int)$definition['min']) {
                $GLOBALS['SYSTEM']['data_error_detail'] = $field . ':min';
                return false;
            }
            if (isset($definition['max']) && $value_count > (int)$definition['max']) {
                $GLOBALS['SYSTEM']['data_error_detail'] = $field . ':max';
                return false;
            }
            continue;
        }
        if (!is_numeric($data_ls[$field])) {
            $GLOBALS['SYSTEM']['data_error_detail'] = $field . ':numeric';
            return false;
        }
        $value = (float)$data_ls[$field];
        if (isset($definition['min']) && $value < (float)$definition['min']) {
            $GLOBALS['SYSTEM']['data_error_detail'] = $field . ':min';
            return false;
        }
        if (isset($definition['max']) && $value > (float)$definition['max']) {
            $GLOBALS['SYSTEM']['data_error_detail'] = $field . ':max';
            return false;
        }
    }
    return true;
}

function _data_value_is_present($value)
{
    if (is_array($value)) {
        foreach ($value as $item) {
            if (_data_value_is_present($item)) {
                return true;
            }
        }
        return false;
    }
    return $value !== null && (!is_string($value) || trim($value) !== '');
}

function _data_validate_unique($resource, $uuid, $data_ls, $fields)
{
    if (!is_array($fields)) {
        return true;
    }

    $meta = data_meta($resource);
    $indexed_fields = isset($meta['index']) && is_array($meta['index']) ? $meta['index'] : [];

    foreach ($fields as $field) {
        if (!array_key_exists($field, $data_ls) || !is_scalar($data_ls[$field])) {
            continue;
        }

        $value = trim((string)$data_ls[$field]);
        if ($value === '') {
            continue;
        }

        if (in_array($field, $indexed_fields, true)) {
            load_library('util');
            $matches = data_read_index($resource, $field, md5_uuid($value));
        } else {
            $matches = [];
            foreach (data_read($resource) as $record_uuid => $record) {
                if (!isset($record[$field]) || !is_scalar($record[$field])) {
                    continue;
                }
                if ((string)$record[$field] === $value) {
                    $matches[$record_uuid] = $record;
                }
            }
        }

        foreach ($matches as $match_uuid => $record) {
            if ($match_uuid !== $uuid) {
                return false;
            }
        }
    }

    return true;
}

function _validate_natural_language($val, $max_length = 255)
{
    if (!is_string($val)) {
        return true;
    }

    $raw = trim($val);
    if ($raw === '') {
        return true;
    }

    $s = mb_strtolower(mb_substr($raw, 0, $max_length, 'UTF-8'), 'UTF-8');
    $s = preg_replace('/[^\p{L}]/u', '', $s);

    $len = mb_strlen($s, 'UTF-8');

    if ($len === 0 || $len < 4) {
        return true;
    }

    if (preg_match('/(asdf|qwer|zxcv|hjkl){2,}/u', $s)) {
        return false;
    }

    preg_match_all('/[aeiouyàáâãäåèéêëìíîïòóôõöùúûü]/u', $s, $m);
    $vowel_count = count($m[0]);

    if ($vowel_count === 0) {
        return false;
    }

    // Consonant run — 7+ to be safe for Dutch compounds
    if (preg_match('/[bcdfghjklmnpqrstvwxz]{7,}/u', $s)) {
        return false;
    }

    if ($len >= 12 && ($vowel_count / $len) < 0.20) {
        return false;
    }

    return true;
}

function _validate_natural_text($val)
{
    return _validate_natural_language($val);
}

function _validate_natural_short_text($val)
{
    if (is_string($val) && strlen(trim($val)) > 255) {
        return false;
    }
    return _validate_natural_language($val);
}

function data_lookup($resource, $uuid, $key, $default = '')
{
    $var = "data." . trim($resource, '.');
    if (isset($GLOBALS['SYSTEM']['variables'][$var])) {
        $data = $GLOBALS['SYSTEM']['variables'][$var];
        if (isset($data[$uuid][$key])) {
            return $data[$uuid][$key];
        }
    }
    $result = data_read($resource, $uuid, $key);
    return $result ?? $default;
}
