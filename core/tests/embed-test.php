<?php

$fixture = sys_get_temp_dir() . '/nimbly-embed-' . bin2hex(random_bytes(5));
$GLOBALS['SYSTEM'] = ['file_base' => $fixture . '/', 'env_paths' => ['ext', 'core'], 'variables' => [], 'request_time' => time()];
$_SERVER['HTTP_HOST'] = 'nimbly.test';
$_SESSION['features'] = [];
function load_library($name) {
    if (in_array($name, ['json', 'log'], true)) return;
    $file = dirname(__DIR__) . '/lib/' . $name . '.php';
    if (is_file($file)) require_once $file;
}
function honeypot_field_name() { return 'website'; }
function json_input() { return $GLOBALS['embed_request']; }
function json_result($data, $code = 200) { return ['data' => $data, 'code' => $code]; }
function log_system($message) {}
function username_get() { return 'tester'; }
require_once dirname(__DIR__) . '/lib/data.php';
require_once dirname(__DIR__) . '/lib/embed.php';
function check($value, $message) { if (!$value) throw new RuntimeException($message); }
function remove_fixture($path) {
    if (is_dir($path)) {
        foreach (array_diff(scandir($path), ['.', '..']) as $name) remove_fixture($path . '/' . $name);
        rmdir($path);
    } elseif (is_file($path)) unlink($path);
}
function frame_info($html) { return embed_info(embed_document($html)->getElementsByTagName('iframe')->item(0)); }

try {
    mkdir($fixture . '/ext/data/posts', 0750, true);
    data_create('posts', '.meta', ['fields' => ['body' => ['type' => 'html']]]);
    $plain = '<iframe src="https://w.soundcloud.com/player/?url=https%3A//api.soundcloud.com/tracks/123" width="100%" height="166" onload="bad()" style="position:fixed" title="SoundCloud"></iframe>';
    $safe = frame_info($plain);
    $safe['sandbox'] = true;
    $safe_html = embed_markup($safe);
    check(data_create('posts', 'safe', ['body' => ['en' => $safe_html]]), 'editor may create sandboxed SoundCloud');
    check(!str_contains($safe_html, 'onload') && !str_contains($safe_html, 'position:fixed'), 'discard hostile attributes');
    check(str_contains($safe_html, 'height:166px') && str_contains($safe_html, 'width:100%'), 'pasted height and full width');
    check(!data_create('posts', 'forged', ['body' => ['en' => $plain]]), 'unmarked unsandboxed frame denied');
    check(data_error_detail_get() === 'manage-unsandboxed-embeds', 'permission failure detail');
    $_SESSION['features'] = ['(all)' => true];
    $unsafe = $safe;
    $unsafe['sandbox'] = false;
    $unsafe['scripts'] = ['https://embed.example/a.js', 'https://embed.example/a.js', 'https://embed.example/b.js'];
    $unsafe_html = embed_markup($unsafe);
    check(data_create('posts', 'privileged', ['body' => ['en' => $unsafe_html]]), 'admin creates privileged embed');
    $stored = data_read('posts', 'privileged')['body']['en'];
    $_SESSION['features'] = ['edit-posts' => true];
    $legacy = ['body' => ['en' => $plain]];
    $legacy_update = ['body' => ['en' => '<p>Before</p>' . $plain]];
    check(embed_validate_fields(data_meta('posts'), $legacy_update, $legacy), 'retain an existing unmarked iframe');
    $legacy_update = ['body' => ['en' => str_replace('w.soundcloud.com', 'other.example', $plain)]];
    check(!embed_validate_fields(data_meta('posts'), $legacy_update, $legacy), 'changing an unmarked iframe source denied');
    check(is_array(data_update('posts', 'privileged', ['body' => ['en' => '<p>before</p>' . $stored . '<p>after</p>']])), 'retain privileged embed with text');
    check(!data_update('posts', 'privileged', ['body' => ['en' => $stored . $stored]]), 'duplicate denied');
    check(!data_update('posts', 'privileged', ['body' => ['en' => str_replace('height="166"', 'height="167"', $stored)]]), 'dimension change denied');
    check(!data_update('posts', 'privileged', ['body' => ['en' => str_replace('embed.example/a.js', 'embed.example/changed.js', $stored)]]), 'script change denied');
    check(!data_update('posts', 'privileged', ['body' => ['nl' => $stored]]), 'copy to another language denied');
    check(!data_update('posts', null, ['safe' => ['body' => ['en' => $stored]]]), 'bulk bypass denied');
    check(!data_create('posts', 'copied', ['body' => ['en' => $stored]]), 'create with forged metadata denied');
    check(is_array(data_update('posts', 'privileged', ['body' => ['en' => '<p>removed</p>']])), 'remove allowed');
    $safe['scripts'] = $unsafe['scripts'];
    $outer = embed_markup($safe, true);
    $outer_frame = embed_document($outer)->getElementsByTagName('iframe')->item(0);
    check(!str_contains($outer_frame->getAttribute('sandbox'), 'allow-same-origin'), 'script document opaque origin');
    check(!str_contains($outer_frame->getAttribute('sandbox'), 'allow-top-navigation'), 'top navigation blocked');
    check(substr_count($outer_frame->getAttribute('srcdoc'), 'https:\/\/embed.example\/a.js') === 1, 'scripts deduplicated');
    foreach (['javascript:alert(1)', 'data:text/html,x', '//evil.test/', 'http://evil.test', 'https://name:pw@evil.test', "https://evil.test/\n"] as $url) check(!embed_url_allowed($url), 'reject unsafe URL');
    check(frame_info('<iframe src="https://nimbly.test/test" sandbox></iframe>') === null, 'same origin sandbox rejected');
    check(frame_info('<iframe src="https://other.test" srcdoc="bad"></iframe>') === null, 'author srcdoc rejected');
    check(embed_normalize_html('<iframe src="javascript:bad()">') === '', 'malformed frame removed');
    $GLOBALS['_collected_scripts'] = [];
    require_once dirname(__DIR__) . '/lib/collect-script.php';
    collect_external_script('https://embed.example/a.js');
    collect_external_script('https://embed.example/a.js');
    collect_external_script('https://embed.example/b.js');
    check(count($GLOBALS['_collected_scripts']) === 2, 'page scripts deduplicated');
    ob_start(); render_collected_scripts(); $loader = ob_get_clean();
    check(str_contains($loader, 'nb:ready') && str_contains($loader, 's.onerror=next') && str_contains($loader, 'fetchPriority="low"'), 'nonblocking loader');
    require_once dirname(__DIR__) . '/modules/api/lib/api.php';
    foreach (['resource_post', 'resource_id_post', 'resource_id_put', 'resource_id_patch', 'resource_put', 'resource_patch'] as $method) {
        $bulk = in_array($method, ['resource_put', 'resource_patch'], true);
        $GLOBALS['embed_request'] = $bulk ? ['safe' => ['body' => ['en' => $stored]]] : ['uuid' => 'api-new', 'body' => ['en' => $stored]];
        $result = str_starts_with($method, 'resource_id_') ? $method('posts', str_contains($method, 'post') ? 'api-new' : 'safe') : $method('posts');
        check($result['code'] >= 400, $method . ' refuses privileged HTML');
    }
    $preview = embed_normalize_html($stored, false, true);
    check(str_contains($preview, ' sandbox=') && !str_contains($preview, 'srcdoc='), 'server editor preview isolated');
    check(count($GLOBALS['_collected_scripts']) === 2, 'editor preview collects no scripts');
    echo "Embed tests passed\n";
} finally { remove_fixture($fixture); }
