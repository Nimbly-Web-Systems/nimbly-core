<?php

const SITEMAP_CACHE_VERSION = 1;

function sitemap_sc($params = null): string
{
    load_library('data');
    load_library('seo-page');
    load_library('set');

    $state = sitemap_source_state();
    $cache_dir = $GLOBALS['SYSTEM']['file_base'] . 'ext/data/.tmp/cache/_sitemap';
    $manifest_file = $cache_dir . '/manifest.json';
    $xml_file = $cache_dir . '/sitemap.xml';
    $manifest = sitemap_read_json($manifest_file);

    if (sitemap_manifest_matches($manifest, $state) && sitemap_cached_xml_valid($xml_file, $manifest)) {
        return sitemap_send_cached($xml_file, $manifest);
    }

    $cache_ready = is_dir($cache_dir) || @mkdir($cache_dir, 0755, true);
    $lock = $cache_ready ? @fopen($cache_dir . '/regenerate.lock', 'c') : false;
    if ($lock && flock($lock, LOCK_EX)) {
        clearstatcache();
        $state = sitemap_source_state();
        $manifest = sitemap_read_json($manifest_file);
        if (sitemap_manifest_matches($manifest, $state) && sitemap_cached_xml_valid($xml_file, $manifest)) {
            flock($lock, LOCK_UN);
            fclose($lock);
            return sitemap_send_cached($xml_file, $manifest);
        }
    }

    [$xml, $manifest] = sitemap_build($state, $cache_ready ? $cache_dir : null, $manifest);
    if ($cache_ready) {
        if (!sitemap_atomic_write($xml_file, $xml)
            || !sitemap_atomic_write($manifest_file, json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT))) {
            error_log('Nimbly sitemap cache could not be written; serving generated XML from memory.');
        }
    } else {
        error_log('Nimbly sitemap cache directory is unavailable; serving generated XML from memory.');
    }
    if ($lock) {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
    sitemap_headers($manifest);
    return $xml;
}

function sitemap_source_state(): array
{
    $root = seo_site_root();
    $languages = data_lookup('.config', 'site', 'languages', ['en']);
    if (!is_array($languages) || $languages === []) {
        $languages = ['en'];
    }
    $routes = sitemap_static_route_files();
    $resources = [];
    $data_root = $GLOBALS['SYSTEM']['file_base'] . 'ext/data';
    foreach (glob($data_root . '/*/.meta') ?: [] as $meta_file) {
        $meta = sitemap_read_json($meta_file);
        if (!is_array($meta['sitemap'] ?? null) || empty($meta['sitemap']['url'])) {
            continue;
        }
        $resource = basename(dirname($meta_file));
        $resources[$resource] = [
            'modified' => data_modified($resource),
            'meta' => hash_file('sha256', $meta_file),
            'config' => $meta['sitemap'],
        ];
    }
    ksort($resources);
    return [
        'version' => SITEMAP_CACHE_VERSION,
        'root' => hash('sha256', $root),
        'languages' => hash('sha256', json_encode(array_values($languages))),
        'routes' => hash('sha256', json_encode($routes)),
        'resources' => $resources,
        '_root' => $root,
        '_languages' => array_values($languages),
        '_route_files' => $routes,
    ];
}

function sitemap_static_route_files(): array
{
    $uri_root = $GLOBALS['SYSTEM']['file_base'] . 'ext/uri';
    if (!is_dir($uri_root)) {
        return [];
    }
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uri_root, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getFilename() !== 'index.tpl') {
            continue;
        }
        $relative = trim(substr(dirname($file->getPathname()), strlen($uri_root)), '/');
        $parts = $relative === '' ? [] : explode('/', $relative);
        $excluded = ['api', 'nb-admin', 'login', 'logout', 'forgot-password', 'password-reset', 'change-email', 'errors', 'img', 'video', 'download', 'sitemap.xml', 'robots.txt'];
        if (array_filter($parts, fn($part) => $part === '' || $part[0] === '.' || str_contains($part, '('))
            || ($parts && in_array($parts[0], $excluded, true))) {
            continue;
        }
        $files[$relative] = $file->getMTime();
    }
    ksort($files);
    return $files;
}

function sitemap_manifest_matches(?array $manifest, array $state): bool
{
    if (!$manifest) {
        return false;
    }
    foreach (['version', 'root', 'languages', 'routes', 'resources'] as $key) {
        if (($manifest[$key] ?? null) !== ($state[$key] ?? null)) {
            return false;
        }
    }
    return !empty($manifest['etag']);
}

function sitemap_build(array $state, ?string $cache_dir, ?array $old_manifest): array
{
    $entries = [];
    $static_file = $cache_dir ? $cache_dir . '/static.json' : null;
    $reuse_static = $old_manifest && ($old_manifest['routes'] ?? null) === $state['routes']
        && ($old_manifest['root'] ?? null) === $state['root'] && $static_file && is_file($static_file);
    $static_entries = $reuse_static ? sitemap_read_json($static_file) : null;
    if (!is_array($static_entries)) {
        $reuse_static = false;
        $static_entries = sitemap_static_entries($state);
    }
    if (!$reuse_static && $static_file) {
        sitemap_atomic_write($static_file, json_encode($static_entries, JSON_UNESCAPED_SLASHES));
    }
    $entries = array_merge($entries, $static_entries ?: []);

    foreach ($state['resources'] as $resource => $resource_state) {
        $fragment_file = $cache_dir ? $cache_dir . '/resource-' . hash('sha256', $resource) . '.json' : null;
        $old_state = $old_manifest['resources'][$resource] ?? null;
        $reuse = $old_state === $resource_state && $fragment_file && is_file($fragment_file)
            && ($old_manifest['root'] ?? null) === $state['root']
            && ($old_manifest['languages'] ?? null) === $state['languages'];
        $fragment = $reuse ? sitemap_read_json($fragment_file) : null;
        if (!is_array($fragment)) {
            $reuse = false;
            $fragment = sitemap_resource_entries($resource, $resource_state['config'], $state);
        }
        if (!$reuse && $fragment_file) {
            sitemap_atomic_write($fragment_file, json_encode($fragment, JSON_UNESCAPED_SLASHES));
        }
        $entries = array_merge($entries, $fragment ?: []);
    }

    $deduplicated = [];
    foreach ($entries as $entry) {
        $deduplicated[$entry['loc']] = isset($deduplicated[$entry['loc']])
            ? sitemap_newer_entry($deduplicated[$entry['loc']], $entry) : $entry;
    }
    ksort($deduplicated, SORT_STRING);
    $xml = sitemap_xml(array_values($deduplicated));
    $modified = time();
    $manifest = array_intersect_key($state, array_flip(['version', 'root', 'languages', 'routes', 'resources']));
    $manifest['etag'] = '"' . hash('sha256', $xml) . '"';
    $manifest['last_modified'] = $modified;
    return [$xml, $manifest];
}

function sitemap_static_entries(array $state): array
{
    $entries = [];
    foreach (array_keys($state['_route_files']) as $route) {
        $entries[] = ['loc' => seo_canonical_url($route)];
    }
    return $entries;
}

function sitemap_resource_entries(string $resource, array $config, array $state): array
{
    $records = data_read($resource);
    $entries = [];
    foreach ($records ?: [] as $record) {
        $published = $config['published'] ?? null;
        if ($published && empty($record[$published])) {
            continue;
        }
        $items = [null];
        if (!empty($config['each'])) {
            $items = $record[$config['each']] ?? [];
            if (!is_array($items)) {
                continue;
            }
        }
        $templates = is_array($config['url']) ? $config['url'] : [null => $config['url']];
        foreach ($templates as $language => $template) {
            if ($language !== null && !in_array($language, $state['_languages'], true)) {
                continue;
            }
            foreach ($items as $item) {
                set_variable('record', $record);
                set_variable('language', $language ?? $state['_languages'][0]);
                set_variable('sitemap_item', $item);
                ob_start();
                run_template((string)$template);
                $url = trim(ob_get_clean());
                if ($url === '') {
                    continue;
                }
                $loc = seo_canonical_url($url);
                if (!filter_var($loc, FILTER_VALIDATE_URL)) {
                    continue;
                }
                $entry = ['loc' => $loc];
                if (!empty($record['_modified'])) {
                    $timestamp = is_numeric($record['_modified']) ? (int)$record['_modified'] : strtotime($record['_modified']);
                    if ($timestamp) {
                        $entry['lastmod'] = gmdate('c', $timestamp);
                    }
                }
                $entries[] = $entry;
            }
        }
    }
    return $entries;
}

function sitemap_newer_entry(array $left, array $right): array
{
    return strcmp($right['lastmod'] ?? '', $left['lastmod'] ?? '') > 0 ? $right : $left;
}

function sitemap_xml(array $entries): string
{
    $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    $xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
    foreach ($entries as $entry) {
        $xml .= '  <url><loc>' . htmlspecialchars($entry['loc'], ENT_XML1, 'UTF-8') . '</loc>';
        if (!empty($entry['lastmod'])) {
            $xml .= '<lastmod>' . htmlspecialchars($entry['lastmod'], ENT_XML1, 'UTF-8') . '</lastmod>';
        }
        $xml .= "</url>\n";
    }
    return $xml . "</urlset>\n";
}

function sitemap_send_cached(string $file, array $manifest): string
{
    sitemap_headers($manifest);
    if (trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === ($manifest['etag'] ?? '')) {
        http_response_code(304);
        return '';
    }
    return (string)file_get_contents($file);
}

function sitemap_cached_xml_valid(string $file, array $manifest): bool
{
    return is_file($file)
        && isset($manifest['etag'])
        && '"' . hash_file('sha256', $file) . '"' === $manifest['etag'];
}

function sitemap_headers(array $manifest): void
{
    if (headers_sent()) {
        return;
    }
    header('Content-Type: application/xml; charset=utf-8');
    header('Cache-Control: public, no-cache, must-revalidate');
    header('ETag: ' . $manifest['etag']);
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $manifest['last_modified']) . ' GMT');
}

function sitemap_read_json(string $file): ?array
{
    if (!is_file($file)) {
        return null;
    }
    $decoded = json_decode((string)@file_get_contents($file), true);
    return is_array($decoded) ? $decoded : null;
}

function sitemap_atomic_write(string $file, string $contents): bool
{
    $temporary = $file . '.tmp-' . bin2hex(random_bytes(6));
    if (@file_put_contents($temporary, $contents, LOCK_EX) === false) {
        return false;
    }
    if (!@rename($temporary, $file)) {
        @unlink($temporary);
        return false;
    }
    return true;
}
