<?php

/**
 * Lists of a resource, answered without reading every record.
 *
 * For each field a list filters or sorts on, the values of all records are
 * kept together in one cache file: a line per record, in the order a full read
 * returns them. A query reads the files of the fields it needs, filters, sorts
 * and cuts its page there, and reads only the records of that page.
 *
 * The files are a cache: derived from the records, never in Git, built the
 * first time a field is asked for, and safe to remove at any time
 * (data_cache_clear(), `./nimbly data:cache:clear`). A write through the data
 * functions adds one line instead of throwing the file away; a later line wins
 * and an empty value removes the record. A file knows the Git commit of the
 * project it was built on, so a pull makes it build again. A record edited in
 * place by hand is not seen until the cache is cleared.
 *
 * File: `#1 <commit> <lines in order>` followed by `<path>\t<JSON value>` lines,
 * where the path is the record's place below the resource folder.
 */

/**
 * Reads a list of records: filtered, sorted and cut to a page.
 *
 * `filter`, `sort`, `search` and `fields` take what `[#data#]` takes and give
 * the same records in the same order; `limit` and `offset` cut the page.
 *
 * @param string $resource Resource name.
 * @param array $options filter, sort, search, fields, limit, offset.
 * @return array ['records' => uuid => record, 'total' => number of records before the cut].
 */
function data_query($resource, $options = [])
{
    $sort = $options['sort'] ?? false;
    $search = $options['search'] ?? false;
    $filter = $options['filter'] ?? false;
    $fields = $options['fields'] ?? false;
    $limit = max(0, (int)($options['limit'] ?? 0));
    $offset = max(0, (int)($options['offset'] ?? 0));

    $rows = $search === false ? _data_list_rows($resource, $filter, $sort) : false;

    if ($rows === false) {
        // Search looks in every field, so it reads every record
        $records = data_read($resource);
        if (!is_array($records)) {
            $records = [];
        }
        $records = _data_list_apply($records, $sort, $search, $filter);
        $total = count($records);
        if ($limit > 0 || $offset > 0) {
            $records = array_slice($records, $offset, $limit > 0 ? $limit : null, true);
        }
    } else {
        $rows = _data_list_rows_apply($rows, $sort, $filter);
        $total = count($rows);
        $records = [];
        foreach (array_slice(array_keys($rows), $offset, $limit > 0 ? $limit : null) as $key) {
            $uuid = basename((string)$key);
            $record = data_read($resource, $uuid);
            if (is_array($record)) {
                $records[$uuid] = $record;
            }
        }
    }

    if ($fields !== false) {
        $records = data_select_fields($records, $fields);
    }

    return ['records' => $records, 'total' => $total];
}

/**
 * When the lists of a resource last changed, without walking its folders:
 * every write adds a line to every list file, so the time of one tells. While
 * the resource has no list file this is data_modified().
 *
 * @return int Unix timestamp.
 */
function data_list_modified($resource)
{
    $dir = _data_list_dir($resource);
    foreach (@scandir($dir) ?: [] as $field) {
        $file = $dir . '/' . $field;
        if ($field[0] !== '.' && is_writable($file) && _data_list_is_current($file)) {
            return max((int)filemtime($file), (int)@filemtime(data_path($resource) . '/.meta'));
        }
    }
    return data_modified($resource);
}

/** Whether a list file was built on the commit the project is on now. */
function _data_list_is_current($file)
{
    $handle = @fopen($file, 'r');
    if ($handle === false) {
        return false;
    }
    $header = explode(' ', rtrim((string)fgets($handle)));
    fclose($handle);
    return count($header) === 3 && $header[0] === '#1' && $header[1] === _data_list_signature();
}

/** Sort, search and filter in the order `[#data#]` applies them. */
function _data_list_apply($records, $sort, $search, $filter)
{
    if ($sort) {
        load_library('data-sort');
        $records = data_sort_param($records, $sort);
    }
    if ($search !== false) {
        $records = data_search($records, $search);
    }
    if ($filter !== false) {
        $records = data_filter($records, $filter);
    }
    return $records;
}

/**
 * Filters and sorts reduced records, with the result data_sort_param() and
 * data_filter() give on the full list.
 *
 * Text sorts, number sorts over whole numbers, and sorts without a type over
 * values that are all numbers or all text, compare the same way on any part of
 * the list, so the filter goes first and the sort compares plain values. Other
 * sorts (numbers with fractions, which the sort functions compare by whole
 * difference, or numbers and text mixed) are left to those functions on the
 * whole list.
 */
function _data_list_rows_apply($rows, $sort, $filter)
{
    $rules = [];
    if ($sort) {
        load_library('data-sort');
        foreach (data_sort_rules($sort) as $rule) {
            $type = trim(strtolower($rule['flags']));
            if (in_array($type, ['num', 'int', 'numeric'], true)) {
                $type = 'number';
            } elseif ($type === 'text' || $type === 'string') {
                $type = 'text';
            } else {
                $type = 'plain';
            }
            $rules[] = [$rule['field'], $type, trim(strtolower($rule['order'])) === 'desc'];
        }
    }

    $language = null;
    $keys = [];
    foreach ($rules as $number => [$field, $type, $descending]) {
        $keys[$number] = [];
        $kinds = [];
        foreach ($rows as $key => $row) {
            // As _get_key() reads a value
            $value = $row[$field] ?? '-';
            if (is_array($value)) {
                $language = $language ?? detect_language_sc();
                if (isset($value[$language]) && is_scalar($value[$language])) {
                    $value = $value[$language];
                } else {
                    $first = current($value);
                    $value = is_scalar($first) ? (string)$first : '';
                }
            }
            if ($type === 'number') {
                $value = floatval($value);
                if (!is_finite($value) || floor($value) !== $value) {
                    return _data_list_apply($rows, $sort, false, $filter);
                }
            } elseif ($type === 'plain') {
                // Numbers among themselves and texts among themselves have one order
                $kinds[is_numeric($value)] = true;
                if (count($kinds) > 1 || !(is_string($value) || is_int($value) || is_float($value))) {
                    return _data_list_apply($rows, $sort, false, $filter);
                }
            } else {
                $value = (string)$value;
            }
            $keys[$number][$key] = $value;
        }
    }

    if ($filter !== false) {
        $rows = data_filter($rows, $filter);
    }
    if ($rules === [] || count($rows) < 2) {
        return $rows;
    }

    $order = array_fill_keys(array_keys($rows), true);
    foreach ($rules as $number => [$field, $type, $descending]) {
        $values = [];
        foreach ($order as $key => $ignored) {
            $values[$key] = $keys[$number][$key];
        }
        if ($type !== 'text') {
            uasort($values, $descending ? fn($a, $b) => $b <=> $a : fn($a, $b) => $a <=> $b);
        } else {
            uasort($values, $descending ? fn($a, $b) => strcasecmp($b, $a) : fn($a, $b) => strcasecmp($a, $b));
        }
        $order = $values;
    }

    $result = [];
    foreach ($order as $key => $ignored) {
        $result[$key] = $rows[$key];
    }
    return $result;
}

/**
 * The records of a resource reduced to the fields a filter and a sort look at,
 * keyed by record path, in the order of a full read.
 *
 * @return array|false False when the list files cannot answer this.
 */
function _data_list_rows($resource, $filter, $sort)
{
    if (!is_dir(data_path($resource))) {
        return false;
    }

    $fields = [];
    if ($filter !== false) {
        foreach (explode(',', (string)$filter) as $part) {
            $pair = explode(':', $part, 2);
            if (count($pair) === 2) {
                $fields[trim($pair[0])] = true;
            }
        }
    }
    if ($sort) {
        load_library('data-sort');
        foreach (data_sort_rules($sort) as $rule) {
            $fields[$rule['field']] = true;
        }
    }
    $fields = array_map('strval', array_keys($fields));
    $with_uuid = in_array('uuid', $fields, true);
    if ($fields === []) {
        $fields = ['uuid'];
    }
    foreach ($fields as $field) {
        if (preg_match('/^[A-Za-z0-9_][A-Za-z0-9_.-]*$/', $field) !== 1) {
            return false;
        }
    }

    $columns = _data_list_columns($resource, $fields);
    if ($columns === false) {
        return false;
    }

    $rows = [];
    foreach (reset($columns) as $key => $ignored) {
        // The sort remembers values per record id
        $row = $with_uuid ? [] : ['uuid' => basename((string)$key)];
        foreach ($fields as $field) {
            if (isset($columns[$field][$key])) {
                $row[$field] = $columns[$field][$key];
            }
        }
        $rows[$key] = $row;
    }
    return $rows;
}

/**
 * Loads the list files of some fields, building the ones that are missing or
 * were built on another commit.
 *
 * @return array|false field => (record path => value), or false when a record id cannot be kept in a line.
 */
function _data_list_columns($resource, $fields)
{
    $columns = [];
    $missing = [];
    foreach ($fields as $number => $field) {
        // The first field gives the order of the list
        $column = _data_list_load($resource, $field, $number === 0);
        if ($column === false) {
            $missing[] = $field;
        } else {
            $columns[$field] = $column;
        }
    }

    if ($missing !== []) {
        // Inside the write lock: a write waits and then adds its line to the new file
        _data_lock($resource);
        try {
            $build = [];
            foreach ($missing as $field) {
                $column = _data_list_load($resource, $field, $field === $fields[0]);
                if ($column === false) {
                    $build[] = $field;
                } else {
                    $columns[$field] = $column;
                }
            }
            if ($build !== []) {
                $built = _data_list_build($resource, $build);
                if ($built === false) {
                    return false;
                }
                $columns += $built;
            }
        } finally {
            _data_unlock($resource);
        }
    }

    $ordered = [];
    foreach ($fields as $field) {
        $ordered[$field] = $columns[$field];
    }
    return $ordered;
}

function _data_list_dir($resource)
{
    return $GLOBALS['SYSTEM']['file_base'] . 'ext/data/.tmp/cache/_data/list/' . rawurlencode((string)$resource);
}

/**
 * What the project's Git checkout is on. A pull or a checkout changes it, and
 * with it the records a list file was built from.
 */
function _data_list_signature()
{
    static $signature = null;
    if ($signature !== null) {
        return $signature;
    }

    $git = $GLOBALS['SYSTEM']['file_base'] . 'ext/.git';
    if (is_file($git) && preg_match('/^gitdir: (.+)$/m', (string)@file_get_contents($git), $match) === 1) {
        $git = $match[1][0] === '/' ? trim($match[1]) : dirname($git) . '/' . trim($match[1]);
    }
    $head = trim((string)@file_get_contents($git . '/HEAD'));
    if ($head === '') {
        return $signature = 'none';
    }
    if (strpos($head, 'ref: ') === 0) {
        $ref = trim((string)@file_get_contents($git . '/' . substr($head, 5)));
        // A packed branch has no file of its own
        $head .= ' ' . ($ref !== '' ? $ref : (string)@filemtime($git . '/packed-refs'));
    }
    return $signature = md5($head);
}

/**
 * Reads one field's list file.
 *
 * @param bool $in_order Whether the result has to be in the order of a full read; costs a sort when records were added since the file was written.
 * @return array|false record path => value; false when there is no usable file.
 */
function _data_list_load($resource, $field, $in_order = true)
{
    $file = _data_list_dir($resource) . '/' . $field;
    // A file this user cannot add to did not get this user's writes
    $contents = is_writable($file) ? @file_get_contents($file) : false;
    if ($contents === false || $contents === '') {
        return false;
    }
    $end = strrpos($contents, "\n");
    if ($end === false) {
        return false;
    }
    if ($end !== strlen($contents) - 1) {
        // A line that is still being written
        $contents = substr($contents, 0, $end + 1);
    }

    $lines = explode("\n", $contents);
    array_pop($lines);
    $header = explode(' ', (string)array_shift($lines));
    if (count($header) !== 3 || $header[0] !== '#1' || $header[1] !== _data_list_signature()) {
        return false;
    }
    $in_order = (int)$header[2];

    $column = [];
    $added = false;
    foreach ($lines as $number => $line) {
        $tab = strpos($line, "\t");
        if ($tab === false) {
            continue;
        }
        $key = substr($line, 0, $tab);
        $value = substr($line, $tab + 1);
        if ($value === '') {
            unset($column[$key]);
            continue;
        }
        if ($number >= $in_order && !$added && !array_key_exists($key, $column)) {
            $added = true;
        }
        $column[$key] = json_decode($value, true);
    }

    if ($added && $in_order) {
        $column = _data_list_in_read_order($column);
    }
    $extra = count($lines) - $in_order;
    if ($extra > max(200, intdiv($in_order, 20))) {
        _data_list_compact($resource, $field);
    }
    return $column;
}

/**
 * Puts record paths in the order a full read finds them: names in a folder
 * alphabetically, a split folder at the place of its two characters.
 */
function _data_list_in_read_order($column)
{
    $keys = array_keys($column);
    $order = [];
    foreach ($keys as $key) {
        $order[] = strtr((string)$key, '/', "\0");
    }
    array_multisort($order, SORT_ASC, SORT_STRING, $keys);
    $result = [];
    foreach ($keys as $key) {
        $result[$key] = $column[$key];
    }
    return $result;
}

/** Writes a list file again without the lines that were replaced or removed. */
function _data_list_compact($resource, $field)
{
    static $busy = false;
    if ($busy) {
        return;
    }
    $busy = true;
    _data_lock($resource);
    try {
        $column = _data_list_load($resource, $field);
        if ($column !== false) {
            _data_list_write($resource, $field, $column);
        }
    } finally {
        _data_unlock($resource);
        $busy = false;
    }
}

function _data_list_line($key, $value)
{
    return $key . "\t" . json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
}

function _data_list_write($resource, $field, $column)
{
    $dir = _data_list_dir($resource);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $contents = '#1 ' . _data_list_signature() . ' ' . count($column) . "\n";
    foreach ($column as $key => $value) {
        $contents .= _data_list_line($key, $value);
    }
    if (_data_write_file_atomically($dir . '/' . $field, $contents) !== false) {
        return true;
    }
    if (empty($GLOBALS['SYSTEM']['data_list_reported'])) {
        // Once per request: the list is still answered, and built again every time
        $GLOBALS['SYSTEM']['data_list_reported'] = true;
        error_log('Nimbly: list cache ' . $dir . '/' . $field . ' cannot be written');
    }
    return false;
}

/**
 * Builds the list files of some fields in one pass over the records. Called
 * inside the resource's write lock.
 *
 * @return array|false field => (record path => value); false when a record id cannot be kept in a line.
 */
function _data_list_build($resource, $fields)
{
    $columns = array_fill_keys($fields, []);
    if (_data_list_scan(data_path($resource), '', $fields, $columns) === false) {
        return false;
    }
    foreach ($columns as $field => $column) {
        _data_list_write($resource, $field, $column);
    }
    return $columns;
}

function _data_list_scan($dir, $prefix, $fields, &$columns)
{
    foreach (scandir($dir) ?: [] as $entry) {
        if ($entry[0] === '.') {
            continue;
        }
        $path = "$dir/$entry";
        if (is_file($path)) {
            if (strpbrk($entry, "\t\n") !== false) {
                return false;
            }
            $record = json_decode((string)file_get_contents($path), true);
            // What data_read() adds to a record that has no times of its own
            if (!isset($record['_modified'])) {
                $record['_modified'] = filemtime($path);
            }
            if (!isset($record['_created'])) {
                $record['_created'] = filectime($path);
            }
            foreach ($fields as $field) {
                $columns[$field][$prefix . $entry] = $record[$field] ?? null;
            }
        } elseif (strlen($entry) === 2 && is_dir($path)) {
            if (_data_list_scan($path, $prefix . $entry . '/', $fields, $columns) === false) {
                return false;
            }
        }
    }
    return true;
}

/**
 * Tells the list files of a resource that a record was written or removed.
 * Called inside the write lock, after the record file changed.
 *
 * @param string $file Full path of the record file.
 * @param bool $removed Whether the record is gone.
 */
function _data_list_note($resource, $uuid, $file, $removed = false)
{
    $dir = _data_list_dir($resource);
    if ((string)$uuid === '' || ((string)$uuid)[0] === '.' || !is_dir($dir)) {
        return;
    }
    $base = data_path($resource) . '/';
    if (strpos($file, $base) !== 0 || strpbrk((string)$uuid, "\t\n") !== false) {
        _data_list_drop($resource);
        return;
    }
    $key = substr($file, strlen($base));
    $record = $removed ? null : data_read($resource, $uuid);

    foreach (scandir($dir) ?: [] as $field) {
        if ($field[0] === '.') {
            continue;
        }
        $list = $dir . '/' . $field;
        if (!_data_list_is_current($list)) {
            @unlink($list);
            continue;
        }
        $line = $removed ? $key . "\t\n" : _data_list_line($key, $record[$field] ?? null);
        if (@file_put_contents($list, $line, FILE_APPEND | LOCK_EX) !== strlen($line) && !@unlink($list)) {
            // Another user's file: that user keeps reading a list without this write
            error_log('Nimbly: list cache ' . $list . ' cannot be updated or removed; clear the data cache as its owner');
        }
    }
}

/** Removes the list files of a resource; the next list builds them again. */
function _data_list_drop($resource)
{
    $dir = _data_list_dir($resource);
    $removed = 0;
    foreach (@scandir($dir) ?: [] as $field) {
        if ($field[0] !== '.' && @unlink($dir . '/' . $field)) {
            $removed++;
        }
    }
    return $removed;
}
