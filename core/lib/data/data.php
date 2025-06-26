<?php

$GLOBALS['SYSTEM']['data_base'] = $GLOBALS['SYSTEM']['file_base'] . 'ext/data';

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
 * @doc - filter: filtering instructions, e.g. `published:yes,status:new`.
 * @doc - search: search term to filter records by any matching field.
 * @doc
 * @doc (*): Mandatory. 
 * @doc
 * @doc **Examples:**
 * @doc - `[#data users#]` loads all users into the frontend variable `data.users`.
 * @doc - `[#data users.123#]` loads the single user with UUID `123` into `data.users.123`.
 * @doc - `[#data users var=all_users#]` loads all users into the custom variable `all_users`.
 * @doc - `[#data projects sort=date|desc,title|asc#]` loads projects sorted by date descending, then title ascending.
 * @doc - `[#data blog-items filter=published:yes#]` loads blog items filtered where `published` equals `yes`.
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

    $filter = get_param_value($params, "filter", false);
    if ($filter !== false) {
        $result = data_filter($result, $filter);
    }

    $data_var = $var_id ?? data_var($resource, $uuid, $op);
    load_library('set');
    set_variable($data_var, $result);
    if ((string)$uuid !== '' && (string)$var_id !== '') {
        set_variable_dot($var_id, $result);
    }
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
    $base = $GLOBALS['SYSTEM']['data_base'] . '/' . $resource;

    if ((string)$uuid === '') {
        // return resource directory path if uuid is empty
        return $base;
    }

    $flat_path = "$base/$uuid";

    if (file_exists($flat_path)) {
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

    if ($cache_time < $modified) {
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
    return file_put_contents($cache_file, $json_data);
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
 * Checks if an index exists for a given resource, index name, and index UUID.
 *
 * @param string $resource Resource name.
 * @param string $index_name Name of the index field.
 * @param string $index_uuid UUID representing the indexed value.
 * @return bool True if the index path exists, false otherwise.
 */
function data_indexed($resource, $index_name, $index_uuid)
{
    $path = data_path($resource) . '/' . $index_name . '/' . $index_uuid;
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
    $index_path = $base_path . '/' . $index_name . '/' . $index_uuid;

    if (!is_dir($index_path)) {
        return $result;
    }

    $index_files = @scandir($index_path);
    if (!is_array($index_files)) {
        return $result;
    }

    load_library('md5');
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

        if (isset($item[$index_name]) && md5_uuid($item[$index_name]) === $index_uuid) {
            $result[$index_file] = $item;
        } else {
            // Remove stale index entry
            log_system('removed index ' . $index_path . '/' . $index_file . ': not matching ' . $index_name);
            @unlink($index_path . '/' . $index_file);
        }
    }

    return $result;
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
    if (empty($data_update_ls) || !data_exists($resource, $uuid)) {
        return false;
    }

    if ((string)$uuid === '') { // update multiple
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

    $data_ls = data_read($resource, $uuid);

    if (empty($data_ls)) {
        $data_merged_ls = $data_update_ls;
    } else {
        $data_merged_ls = array_merge_recursive_distinct($data_ls, $data_update_ls);
    }

    // Handle primary key changes
    $meta = data_meta($resource);
    $pk_field = $data_update_ls['pk-field-name'] ?? ($meta['pk'] ?? false);
    if ($pk_field) {
        $pk_value = $data_update_ls[$pk_field] ?? false;
        $uuid = data_update_pk($resource, $uuid, $pk_value);
        if ((string)$uuid === '') {
            return false;
        }
        $data_merged_ls['uuid'] = $uuid;
    }

    // Update modification metadata
    load_library('md5');
    load_library('username', 'user');
    $data_merged_ls['_modified_by'] = md5_uuid(username_get());
    $data_merged_ls['_modified'] = time();

    if (data_create($resource, $uuid, $data_merged_ls)) {
        return $data_merged_ls;
    }

    return false;
}

/**
 * Updates the primary key (UUID) of a resource by renaming its data file.
 *
 * Generates a new UUID from the given primary key value,
 * and if different from the current UUID, renames the data file accordingly.
 *
 * **Deprecated:** Updating the primary key is discouraged and will be phased out.
 * It is recommended to use stable UUIDs as immutable primary keys and manage
 * alternate keys or slugs via indexing instead.
 *
 * @param string $resource Resource name.
 * @param string $uuid Current UUID of the record.
 * @param string $pk_value New primary key value used to generate new UUID.
 * @return string|false New UUID if renamed successfully, original UUID if no change,
 *                      or false if renaming failed or new UUID already exists.
 */
function data_update_pk($resource, $uuid, $pk_value)
{
    if (empty($pk_value)) {
        return $uuid;
    }
    load_library('md5');
    $new_uuid = md5_uuid($pk_value);
    if ($new_uuid === $uuid) {
        return $uuid;
    }
    if (data_exists($resource, $new_uuid)) {
        return false;
    }
    $path = data_path($resource) . '/';
    if (rename($path . $uuid, $path . $new_uuid) === true) {
        return $new_uuid;
    }
    return false;
}

/**
 * Creates or rewrites a data object file.
 *
 * Writes the JSON-encoded $data_ls to the file identified by $resource and $uuid.
 * Automatically manages creation/modification metadata.
 * Updates indexes defined in the resource metadata.
 * Triggers 'data-create' event on success.
 *
 * @param string $resource Resource name.
 * @param string $uuid UUID of the record to create or update.
 * @param array $data_ls Data array to store.
 * @return bool True on success, false on failure.
 */
function data_create($resource, $uuid, $data_ls)
{
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

    if (isset($data_ls['form-key'])) {
        unset($data_ls['form-key']);
    }

    if (!isset($data_ls['_created_by'])) {
        load_library('md5');
        load_library('username', 'user');
        $data_ls['_created_by'] = md5_uuid(username_get());
    }

    if (!isset($data_ls['_created'])) {
        $data_ls['_created'] = time();
        $data_ls['_modified'] = time();
    }

    $data_ls['uuid'] = $uuid;

    $json_data = json_encode($data_ls, JSON_UNESCAPED_UNICODE);
    if (@file_put_contents($file, $json_data) !== false) {
        touch($dir); // Update directory modification time to signal change and invalidate caches

        $meta = data_meta($resource);
        if (isset($meta['index']) && is_array($meta['index'])) {
            load_library('md5');
            foreach ($meta['index'] as $index_name) {
                if (empty($data_ls[$index_name])) {
                    continue;
                }
                $index_uuid = md5_uuid($data_ls[$index_name]);
                if ($index_uuid === $uuid) {
                    continue; // no need to index self
                }
                _data_create_index($file, $index_name, $index_uuid);
            }
        }

        load_library('trigger');
        trigger('data-create', ['resource' => $resource, 'uuid' => $uuid, 'data' => $data_ls]);
        return true;
    }

    return false;
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
function _data_create_index($file, $index_name, $index_uuid)
{
    $path = dirname($file) . '/' . $index_name . '/' . $index_uuid . '/';
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
 * (resource directory)/(index name)/(index uuid)/(record filename)
 *
 * @param string $file Full path to the original data file.
 * @param string $index_name Name of the index field.
 * @param string $index_uuid UUID of the index value.
 * @return int Returns 1 if the index file was deleted, 0 if it did not exist.
 */
function _data_delete_index($file, $index_name, $index_uuid)
{
    $path = dirname($file) . '/' . $index_name . '/' . $index_uuid . '/' . basename($file);
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
        $file = data_path($resource, $uuid);
        if (!file_exists($file)) {
            return $result;
        }
        $meta = data_meta($resource);
        if (isset($meta['index']) && is_array($meta['index'])) {
            $data_ls = data_read($resource, $uuid);
            load_library('md5');
            foreach ($meta['index'] as $index_name) {
                if (empty($data_ls[$index_name])) {
                    continue;
                }
                $index_uuid = md5_uuid($data_ls[$index_name]);
                $result += _data_delete_index($file, $index_name, $index_uuid);
            }
        }
        $result += (int)unlink($file);
        return $result;
    }

    // Delete entire resource: all files including .meta, and resource directory
    load_library('util');
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
 * @param string $resource Resource name.
 * @return array Resource metadata array.
 */
function data_meta($resource)
{
    static $meta_result = [];
    if (!empty($meta_result[$resource])) {
        return $meta_result[$resource];
    }
    if (data_exists($resource, ".meta")) {
        $meta = data_read($resource, ".meta");
    } else {
        $meta = ['fields' => false];
        data_create($resource, ".meta", $meta);
    }
    $meta_result[$resource] = $meta;
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
    return filemtime($path);
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
