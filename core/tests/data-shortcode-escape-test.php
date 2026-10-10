<?php

// Whoever writes a record (the API, an import, an agent, a form handler, a
// library), a shortcode tag in a value that comes in is stored as text. Values
// the record already holds, resource definitions and resources that keep their
// text as written are left alone.

$fixture = sys_get_temp_dir() . '/nimbly-data-escape-' . bin2hex(random_bytes(6));

$GLOBALS['SYSTEM'] = [
    // data.php derives the data directory from file_base
    'file_base' => $fixture . '/',
    'env_paths' => ['ext', 'core'],
    'variables' => [],
    'request_time' => time(),
];

function load_library($library)
{
    if (in_array($library, ['data', 'util', 'event', 'request-input'], true)) {
        require_once dirname(__DIR__) . '/lib/' . $library . '.php';
    }
}

function username_get()
{
    return 'tester';
}

require_once dirname(__DIR__) . '/lib/data.php';

function data_escape_assert($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function data_escape_stored($resource, $uuid)
{
    return json_decode((string)file_get_contents(data_path($resource, $uuid)), true);
}

function data_escape_remove($path)
{
    if (is_dir($path) && !is_link($path)) {
        foreach (array_diff(scandir($path), ['.', '..']) as $entry) {
            data_escape_remove($path . '/' . $entry);
        }
        rmdir($path);
    } elseif (file_exists($path)) {
        unlink($path);
    }
}

$live = 'see [#env PEPPER#] here';
$text = 'see &#91;#env PEPPER#&#93; here';

try {
    mkdir($fixture . '/ext/data', 0750, true);

    $meta = [
        'encrypt' => 'password',
        'fields' => [
            'title' => ['type' => 'text'],
            'name' => ['type' => 'text'],
            'url' => ['type' => 'text', 'url' => '[#base-url#]/notes/[#record.uuid#]'],
        ],
    ];
    data_escape_assert(data_create_resource('notes', $meta) === true, 'the resource was not created');
    $stored_meta = data_escape_stored('notes', '.meta');
    data_escape_assert(
        $stored_meta['fields']['url']['url'] === '[#base-url#]/notes/[#record.uuid#]',
        'a resource definition lost its shortcodes'
    );

    // a new record
    data_escape_assert(data_create('notes', 'n1', [
        'title' => $live,
        'name' => ['en' => $live, 'nl' => 'gewoon & <b>vet</b>'],
        'count' => 3,
        'password' => 'pw[#env A#]',
    ]) === true, 'the record was not created');
    $record = data_escape_stored('notes', 'n1');
    data_escape_assert($record['title'] === $text, 'a new record kept a live shortcode');
    data_escape_assert($record['name']['en'] === $text, 'a translated value kept a live shortcode');
    data_escape_assert($record['name']['nl'] === 'gewoon & <b>vet</b>', 'a value without tags was changed');
    data_escape_assert($record['count'] === 3, 'a non-string value was changed');
    data_escape_assert($record['password'] === 'pw[#env A#]', 'a one-way encrypted field was not kept as given');

    // an update: what comes in is escaped, what the record already holds is not touched
    data_escape_assert(_data_store('notes', 'n2', ['title' => 'old', 'url' => '[#base-url#]/kept']) === true, 'the fixture record was not stored');
    data_escape_assert(is_array(data_update('notes', 'n2', ['title' => $live])), 'the record was not updated');
    $record = data_escape_stored('notes', 'n2');
    data_escape_assert($record['title'] === $text, 'an updated value kept a live shortcode');
    data_escape_assert($record['url'] === '[#base-url#]/kept', 'a value the record already held was changed by an update of another field');

    // saving what was read changes nothing
    data_escape_assert(is_array(data_update('notes', 'n1', ['title' => $text])), 'the record was not saved again');
    data_escape_assert(data_escape_stored('notes', 'n1')['title'] === $text, 'saving an escaped value again changed it');

    // several records in one update
    data_escape_assert(is_array(data_update('notes', '', ['n1' => ['title' => 'a ' . $live], 'n2' => ['title' => 'plain']])), 'the records were not updated together');
    data_escape_assert(data_escape_stored('notes', 'n1')['title'] === 'a ' . $text, 'an update of several records kept a live shortcode');
    data_escape_assert(data_escape_stored('notes', 'n2')['title'] === 'plain', 'an update of several records changed a value without tags');

    // a record created through an update (upsert)
    data_escape_assert(data_create_resource('settings', ['fields' => false, 'upsert' => true]) === true, 'the upsert resource was not created');
    data_escape_assert(is_array(data_update('settings', 'site', ['title' => $live])), 'the record was not upserted');
    data_escape_assert(data_escape_stored('settings', 'site')['title'] === $text, 'an upserted record kept a live shortcode');

    // a resource that holds its text exactly as written
    data_escape_assert(data_create_resource('log', ['keep_shortcodes' => true, 'fields' => false]) === true, 'the verbatim resource was not created');
    data_escape_assert(data_create('log', 'l1', ['payload' => $live]) === true, 'the verbatim record was not created');
    data_escape_assert(is_array(data_update('log', 'l1', ['more' => $live])), 'the verbatim record was not updated');
    $record = data_escape_stored('log', 'l1');
    data_escape_assert($record['payload'] === $live && $record['more'] === $live, 'a resource with keep_shortcodes did not keep its text as written');

    echo "data-shortcode-escape-test passed\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    data_escape_remove($fixture);
    exit(1);
}

data_escape_remove($fixture);
