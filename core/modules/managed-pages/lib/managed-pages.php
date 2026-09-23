<?php

/**
 * Optional record-backed pages. Core provides a default page type;
 * applications opt in with URL areas and may add or override page types.
 */

function managed_pages_declaration(string $file): array
{
    $path = $GLOBALS['SYSTEM']['file_base'] . 'ext/modules/managed-pages/' . $file;
    if (!is_file($path)) {
        return [];
    }
    $value = json_decode((string)file_get_contents($path), true);
    return is_array($value) ? $value : [];
}

function managed_pages_config(): array
{
    $config = data_exists('.config', 'managed_pages')
        ? data_read('.config', 'managed_pages')
        : [];
    return is_array($config) ? $config : [];
}

/** Whether the optional custom-pages feature is enabled. Disabled by default. */
function managed_pages_feature_enabled(): bool
{
    return (managed_pages_config()['enabled'] ?? false) === true;
}

function managed_pages_types(): array
{
    $config = managed_pages_config();
    $application_types = is_array($config['page_types'] ?? null)
        ? $config['page_types']
        : [];
    return array_replace([
        'default' => [
            'name' => 'Default page',
            'description' => 'A standard editorial page with a title and rich content.',
            'template' => 'managed-page-default',
        ],
    ], $application_types);
}

function managed_pages_type_options(): array
{
    $options = [];
    $types = function_exists('get_variable') && get_variable('nb_form_edit') === 'true'
        ? managed_pages_types()
        : managed_pages_creation_types();
    foreach ($types as $id => $definition) {
        if (is_array($definition) && !empty($definition['name']) && !empty($definition['template'])) {
            $options[(string)$id] = (string)$definition['name'];
        }
    }
    return $options;
}

/** Page types currently available when creating a page. */
function managed_pages_creation_types(): array
{
    if (!managed_pages_feature_enabled()) {
        return [];
    }
    $types = managed_pages_types();
    $config = managed_pages_config();
    if (!is_array($config) || !array_key_exists('enabled_page_types', $config)) {
        return $types;
    }
    if (!is_array($config['enabled_page_types'])) {
        return [];
    }
    $enabled = array_values(array_filter($config['enabled_page_types'], 'is_string'));
    return array_intersect_key($types, array_fill_keys($enabled, true));
}

function managed_pages_default_creation_type(): ?string
{
    $types = managed_pages_creation_types();
    if (isset($types['default'])) {
        return 'default';
    }
    $first = array_key_first($types);
    return $first === null ? null : (string)$first;
}

function managed_pages_url_config(): array
{
    $config = managed_pages_declaration('url-areas.json');
    if (!empty($config['include_site_languages'])) {
        $languages = data_lookup('.config', 'site', 'languages', []);
        $enabled = is_array($config['enabled'] ?? null) ? $config['enabled'] : [];
        $config['enabled'] = array_values(array_unique(array_merge(
            $enabled,
            is_array($languages) ? $languages : []
        )));
    }
    return $config;
}

function managed_pages_enabled(): bool
{
    $config = managed_pages_url_config();
    return managed_pages_feature_enabled() && managed_pages_types() !== [] && !empty($config['enabled']);
}

function managed_pages_normalize_path($path): ?string
{
    if (!is_scalar($path)) {
        return null;
    }
    $path = trim((string)$path);
    $url_path = parse_url($path, PHP_URL_PATH);
    if (!is_string($url_path) || str_contains($path, '?') || str_contains($path, '#')) {
        return null;
    }
    $path = trim($url_path, '/');
    if ($path === '' || str_contains($path, '//') || str_contains($path, '\\')) {
        return null;
    }
    $segments = explode('/', $path);
    foreach ($segments as $segment) {
        if ($segment === '' || $segment === '.' || $segment === '..'
            || rawurldecode($segment) !== $segment
            || !preg_match('/^[\pL\pN][\pL\pN._~-]*$/u', $segment)) {
            return null;
        }
    }
    return implode('/', $segments);
}

function managed_pages_path_in_area(string $path): bool
{
    $config = managed_pages_url_config();
    foreach (($config['reserved'] ?? []) as $reserved) {
        $reserved = trim((string)$reserved, '/');
        if ($reserved !== '' && ($path === $reserved || str_starts_with($path, $reserved . '/'))) {
            return false;
        }
    }
    foreach (($config['enabled'] ?? []) as $enabled) {
        $enabled = trim((string)$enabled, '/');
        if ($enabled !== '' && ($path === $enabled || str_starts_with($path, $enabled . '/'))) {
            return true;
        }
    }
    return false;
}

function managed_pages_path_language(string $path): string
{
    return explode('/', $path, 2)[0];
}

function managed_pages_path_has_code_route(string $path): bool
{
    if (!function_exists('find_uri')) {
        return false;
    }
    return find_uri($path) !== false || find_uri($path, 'route.inc') !== false;
}

function managed_pages_all_addresses(array $record): array
{
    $result = [];
    foreach (($record['path'] ?? []) as $language => $path) {
        $normalized = managed_pages_normalize_path($path);
        if ($normalized !== null) {
            $result[$language][$normalized] = 'path';
        }
    }
    foreach (($record['previous_paths'] ?? []) as $language => $paths) {
        foreach (is_array($paths) ? $paths : [] as $path) {
            $normalized = managed_pages_normalize_path($path);
            if ($normalized !== null) {
                $result[$language][$normalized] = 'alias';
            }
        }
    }
    return $result;
}

/** Resource validator used by the pages schema on every write path. */
function managed_pages_validate_record($resource, $uuid, &$record): bool
{
    if ($resource !== 'pages' || !is_array($record)) {
        return true;
    }
    $types = managed_pages_types();
    $type = (string)($record['type'] ?? '');
    if ($type === '' || empty($types[$type]['template'])) {
        data_error_set('VALIDATION_FAILED', 'type:undeclared');
        return false;
    }
    $exists = data_exists('pages', $uuid);
    if (!$exists && !isset(managed_pages_creation_types()[$type])) {
        data_error_set('VALIDATION_FAILED', 'type:disabled');
        return false;
    }
    if ($exists) {
        $existing = data_read('pages', $uuid);
        if (!empty($existing['type']) && $existing['type'] !== $type) {
            data_error_set('VALIDATION_FAILED', 'type:immutable');
            return false;
        }
        foreach (($existing['path'] ?? []) as $language => $old_path) {
            $new_path = $record['path'][$language] ?? null;
            if ($old_path && $new_path && $old_path !== $new_path) {
                $record['previous_paths'][$language][] = $old_path;
            }
        }
    }
    foreach (($record['path'] ?? []) as $language => $raw_path) {
        $path = managed_pages_normalize_path($raw_path);
        if ($path === null || $path !== trim((string)$raw_path, '/')
            || managed_pages_path_language($path) !== (string)$language
            || !managed_pages_path_in_area($path)
            || managed_pages_path_has_code_route($path)) {
            data_error_set('VALIDATION_FAILED', 'path:' . $language);
            return false;
        }
        $record['path'][$language] = $path;
    }
    foreach (($record['previous_paths'] ?? []) as $language => $paths) {
        $clean = [];
        foreach (array_unique(is_array($paths) ? $paths : []) as $raw_path) {
            $path = managed_pages_normalize_path($raw_path);
            if ($path === null || managed_pages_path_language($path) !== (string)$language
                || !managed_pages_path_in_area($path) || managed_pages_path_has_code_route($path)) {
                data_error_set('VALIDATION_FAILED', 'previous_paths:' . $language);
                return false;
            }
            if (($record['path'][$language] ?? null) !== $path) {
                $clean[] = $path;
            }
        }
        $record['previous_paths'][$language] = array_values($clean);
    }
    $candidate = managed_pages_all_addresses($record);
    foreach (data_list('pages') as $other_uuid) {
        if ((string)$other_uuid === (string)$uuid) {
            continue;
        }
        $other = data_read('pages', $other_uuid);
        if (!is_array($other)) {
            continue;
        }
        $other_addresses = managed_pages_all_addresses($other);
        foreach ($candidate as $language => $addresses) {
            if (array_intersect_key($addresses, $other_addresses[$language] ?? [])) {
                data_error_set('RESOURCE_EXISTS', 'path:' . $language);
                return false;
            }
        }
    }
    return true;
}

function managed_pages_localized_value(array $record, string $field, string $language, $default = null)
{
    $value = $record[$field] ?? null;
    return is_array($value) && array_key_exists($language, $value) ? $value[$language] : $default;
}

function managed_pages_is_published(array $record, string $language): bool
{
    return filter_var(managed_pages_localized_value($record, 'published', $language, false), FILTER_VALIDATE_BOOLEAN);
}

function managed_pages_find(string $path, bool $published_only = true): ?array
{
    $path = managed_pages_normalize_path($path);
    if ($path === null || !managed_pages_path_in_area($path) || !data_exists('pages')) {
        return null;
    }
    $language = managed_pages_path_language($path);
    foreach (data_read('pages') as $uuid => $record) {
        if ($published_only && !managed_pages_is_published($record, $language)) {
            continue;
        }
        if (managed_pages_localized_value($record, 'path', $language) === $path) {
            return ['uuid' => $uuid, 'record' => $record, 'language' => $language, 'alias' => false];
        }
        $aliases = $record['previous_paths'][$language] ?? [];
        if (is_array($aliases) && in_array($path, $aliases, true)) {
            return ['uuid' => $uuid, 'record' => $record, 'language' => $language, 'alias' => true];
        }
    }
    return null;
}

function managed_pages_url(string $uuid, string $language, bool $published_only = true): ?string
{
    $record = data_read('pages', $uuid);
    if (!is_array($record) || ($published_only && !managed_pages_is_published($record, $language))) {
        return null;
    }
    $path = managed_pages_localized_value($record, 'path', $language);
    return is_string($path) && managed_pages_normalize_path($path) === $path ? $path : null;
}

/** Validate all declarations and addresses in a candidate checkout. */
function managed_pages_check(): array
{
    if (!managed_pages_enabled()) {
        return [];
    }
    $types = managed_pages_types();
    $claimed = [];
    $errors = [];
    foreach (data_list('pages') as $uuid) {
        $record = data_read('pages', $uuid);
        if (!is_array($record)) {
            $errors[] = "Page {$uuid} cannot be read.";
            continue;
        }
        $type = (string)($record['type'] ?? '');
        if (empty($types[$type]['template']) || find_template((string)($types[$type]['template'] ?? '')) === false) {
            $errors[] = "Page {$uuid} uses unavailable type {$type}.";
        }
        foreach (managed_pages_all_addresses($record) as $language => $addresses) {
            foreach ($addresses as $path => $kind) {
                if (managed_pages_normalize_path($path) !== $path
                    || managed_pages_path_language($path) !== (string)$language
                    || !managed_pages_path_in_area($path)) {
                    $errors[] = "Page {$uuid} has invalid {$kind} {$path}.";
                    continue;
                }
                if (managed_pages_path_has_code_route($path)) {
                    $errors[] = "Page {$uuid} {$kind} {$path} collides with a code route.";
                }
                $key = $language . ':' . $path;
                if (isset($claimed[$key]) && $claimed[$key] !== $uuid) {
                    $errors[] = "Pages {$claimed[$key]} and {$uuid} both claim {$path}.";
                }
                $claimed[$key] = $uuid;
            }
        }
    }
    return array_values(array_unique($errors));
}

function managed_pages_run(string $uri): bool
{
    if (!managed_pages_enabled()) {
        return false;
    }
    load_libraries(['access', 'data']);
    $can_preview_unpublished = access_by_feature('edit-pages');
    $match = managed_pages_find($uri, !$can_preview_unpublished);
    if ($match === null) {
        return false;
    }
    $record = $match['record'];
    $language = $match['language'];
    if ($match['alias']) {
        load_library('redirect');
        redirect(managed_pages_url($match['uuid'], $language, !$can_preview_unpublished), 301);
    }
    $type = managed_pages_types()[$record['type']] ?? null;
    if (!is_array($type) || empty($type['template'])) {
        return false;
    }
    $template_name = (string)$type['template'];
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $template_name)) {
        return false;
    }
    $template = find_template($template_name);
    if ($template === false) {
        return false;
    }
    load_library('set');
    $record['uuid'] = $match['uuid'];
    set_variable('page', $record);
    set_variable_dot('page', $record);
    set_variable('language', $language);
    $title = managed_pages_localized_value($record, 'seo_title', $language)
        ?: managed_pages_localized_value($record, 'title', $language);
    $description = managed_pages_localized_value($record, 'seo_description', $language, '');
    set_variable('page-title', htmlspecialchars(strip_tags((string)$title), ENT_QUOTES, 'UTF-8'));
    set_variable('page-description', htmlspecialchars(strip_tags((string)$description), ENT_QUOTES, 'UTF-8'));
    set_variable('page-canonical-url', managed_pages_url($match['uuid'], $language, !$can_preview_unpublished));
    $languages = data_lookup('.config', 'site', 'languages', ['en']);
    foreach (is_array($languages) ? $languages : ['en'] as $variant_language) {
        set_variable('i18n-url.' . $variant_language, '(hide)');
    }
    foreach (($record['path'] ?? []) as $variant_language => $variant_path) {
        if (managed_pages_is_published($record, (string)$variant_language)) {
            set_variable('i18n-url.' . $variant_language, $variant_path);
        }
    }
    if (!managed_pages_is_published($record, $language)) {
        load_libraries(['system-messages', 'text']);
        system_message(text_translate($language, "You're previewing an unpublished page. Only editors can see this."));
    }
    $GLOBALS['SYSTEM']['uri'] = $uri;
    $GLOBALS['SYSTEM']['uri_key'] = preg_replace('/[^ \w]+/', '_', $uri);
    $GLOBALS['SYSTEM']['uri_path'] = dirname($template);
    foreach (['header', 'footer'] as $region) {
        $region_template = find_uri($language, $region . '.tpl');
        if ($region_template !== false) {
            set_variable($region, run_buffered($region_template));
        }
    }
    $main_template = find_template($template_name . '-main');
    if ($main_template !== false) {
        set_variable('main', run_buffered($main_template));
    }
    run($template);
    return true;
}
