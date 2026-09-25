<?php

load_library('data');
load_library('set');

/** Languages supported by the current two-letter runtime and settings UI. */
function site_settings_language_catalog(): array
{
    return [
        'ar' => 'Arabic', 'da' => 'Danish', 'de' => 'German',
        'en' => 'English', 'es' => 'Spanish', 'fa' => 'Persian',
        'fi' => 'Finnish', 'fr' => 'French', 'he' => 'Hebrew',
        'it' => 'Italian', 'nl' => 'Dutch', 'no' => 'Norwegian',
        'pl' => 'Polish', 'pt' => 'Portuguese', 'sv' => 'Swedish',
    ];
}

/** Catalog languages written right to left; they start with rtl text and the admin sidebar on the right. */
function site_settings_rtl_languages(): array
{
    return ['ar', 'fa', 'he'];
}

/**
 * Stores a per-language value for every site language. A missing language would
 * otherwise show another language's value. A plain string keeps applying to the
 * languages it covered; languages without a value get their ltr or rtl default.
 */
function site_settings_per_language($value, array $languages, array $new_languages, array $defaults): ?array
{
    $values = is_array($value) ? $value : [];
    foreach ($languages as $language) {
        if (!isset($values[$language]) && is_string($value) && !in_array($language, $new_languages, true)) {
            $values[$language] = $value;
        }
        $values[$language] ??= $defaults[in_array($language, site_settings_rtl_languages(), true) ? 1 : 0];
        if (!in_array($values[$language], $defaults, true)) {
            return null;
        }
    }
    return $values;
}

/** Validator for the application .config resource. Unrelated records pass through. */
function site_settings_validate_config($resource, $uuid, &$record): bool
{
    if ($resource !== '.config' || !is_array($record)) {
        return true;
    }
    if ($uuid === 'site') {
        $languages = $record['languages'] ?? null;
        if (!is_array($languages) || $languages === [] || count($languages) !== count(array_unique($languages))) {
            data_error_set('VALIDATION_FAILED', 'languages:list');
            return false;
        }
        $existing = data_exists('.config', 'site') ? data_read('.config', 'site') : [];
        $old_languages = is_array($existing['languages'] ?? null) ? array_values($existing['languages']) : [];
        $languages = array_values($languages);
        // A language may be added, or the same languages put in a new order
        // (the first one is the default); removing one would orphan its content.
        $reordered = count($languages) === count($old_languages) && array_diff($languages, $old_languages) === [];
        $appended = count($languages) === count($old_languages) + 1
            && array_slice($languages, 0, count($old_languages)) === $old_languages;
        if ($old_languages !== [] && !$reordered && !$appended) {
            data_error_set('VALIDATION_FAILED', 'languages:append-or-reorder');
            return false;
        }
        if ($reordered && $languages[0] !== $old_languages[0]) {
            // A plain-text name or description shows in every language today; keep it that way, but
            // as per-language values, so it does not silently become the new default language's text.
            foreach (['name', 'description'] as $field) {
                if (isset($record[$field]) && is_string($record[$field])) {
                    $record[$field] = array_fill_keys($languages, $record[$field]);
                }
            }
        }
        $catalog = site_settings_language_catalog();
        foreach (array_slice($languages, count($old_languages)) as $language) {
            if (!is_string($language) || !isset($catalog[$language])) {
                data_error_set('VALIDATION_FAILED', 'languages:unsupported');
                return false;
            }
        }
        $record['languages'] = $languages;
        $new_languages = array_values(array_diff($languages, $old_languages));
        $direction = site_settings_per_language($record['direction'] ?? null, $languages, $new_languages, ['ltr', 'rtl']);
        $side = site_settings_per_language($record['nimblybar']['side'] ?? null, $languages, $new_languages, ['left', 'right']);
        if ($direction === null || $side === null) {
            data_error_set('VALIDATION_FAILED', $direction === null ? 'direction:value' : 'nimblybar.side:value');
            return false;
        }
        $record['direction'] = $direction;
        $record['nimblybar'] = array_merge(is_array($record['nimblybar'] ?? null) ? $record['nimblybar'] : [], ['side' => $side]);
    }
    if ($uuid === 'managed_pages') {
        $existing = data_exists('.config', 'managed_pages') ? data_read('.config', 'managed_pages') : [];
        $changed = site_settings_managed_pages_changed_keys(is_array($existing) ? $existing : [], $record);
        if ($changed !== [] && !site_settings_is_system_manager()) {
            data_error_set('VALIDATION_FAILED', 'managed_pages:forbidden');
            return false;
        }
        $error = site_settings_managed_pages_structure_error($record);
        if ($error !== null) {
            data_error_set('VALIDATION_FAILED', $error);
            return false;
        }
    }
    foreach (['enabled', 'navigation_enabled'] as $flag) {
        if ($uuid === 'managed_pages' && array_key_exists($flag, $record) && !is_bool($record[$flag])) {
            data_error_set('VALIDATION_FAILED', $flag . ':boolean');
            return false;
        }
    }
    if ($uuid === 'managed_pages' && array_key_exists('enabled_page_types', $record)) {
        $enabled = $record['enabled_page_types'];
        if (!is_array($enabled) || count($enabled) !== count(array_unique($enabled))) {
            data_error_set('VALIDATION_FAILED', 'enabled_page_types:list');
            return false;
        }
        $registered = ['default' => true];
        foreach (is_array($record['page_types'] ?? null) ? $record['page_types'] : [] as $id => $definition) {
            if (is_string($id) && is_array($definition) && !empty($definition['template'])) {
                $registered[$id] = true;
            }
        }
        foreach ($enabled as $id) {
            if (!is_string($id) || !isset($registered[$id])) {
                data_error_set('VALIDATION_FAILED', 'enabled_page_types:unknown');
                return false;
            }
        }
        $record['enabled_page_types'] = array_values($enabled);
    }
    return true;
}

/** Custom pages configuration that only system managers may change; editors use the feature, not its setup. */
function site_settings_managed_pages_restricted_keys(): array
{
    return ['enabled', 'enabled_page_types', 'page_types', 'url_areas', 'navigation_slots', 'navigation_enabled'];
}

function site_settings_managed_pages_changed_keys(array $old, array $new): array
{
    $changed = [];
    foreach (site_settings_managed_pages_restricted_keys() as $key) {
        if (json_encode($old[$key] ?? null) !== json_encode($new[$key] ?? null)) {
            $changed[] = $key;
        }
    }
    return $changed;
}

function site_settings_is_system_manager(): bool
{
    load_library('managed-pages');
    return managed_pages_is_system_manager();
}

/** Returns a validation detail for a malformed url_areas or navigation_slots value, or null. */
function site_settings_managed_pages_structure_error(array $record): ?string
{
    if (array_key_exists('url_areas', $record)) {
        $areas = $record['url_areas'];
        if (!is_array($areas)) {
            return 'url_areas:object';
        }
        foreach (['enabled', 'reserved'] as $list) {
            if (isset($areas[$list]) && (!is_array($areas[$list]) || array_filter($areas[$list], fn($value) => !is_string($value)))) {
                return 'url_areas.' . $list . ':list';
            }
        }
        foreach (['include_site_languages', 'allow_unprefixed'] as $flag) {
            if (isset($areas[$flag]) && !is_bool($areas[$flag])) {
                return 'url_areas.' . $flag . ':boolean';
            }
        }
    }
    if (array_key_exists('navigation_slots', $record)) {
        $slots = $record['navigation_slots'];
        if (!is_array($slots)) {
            return 'navigation_slots:object';
        }
        foreach ($slots as $id => $slot) {
            if (!is_string($id) || !preg_match('/^[a-z0-9_-]+$/', $id)) {
                return 'navigation_slots:id';
            }
            $depth = is_array($slot) ? ($slot['depth'] ?? 1) : null;
            if (!is_array($slot) || trim((string)($slot['name'] ?? '')) === '' || !is_int($depth) || $depth < 1 || $depth > 5) {
                return 'navigation_slots.' . $id . ':definition';
            }
        }
    }
    return null;
}

function site_settings_sc($params)
{
    load_library('access');
    if (!access_by_feature('edit-.config')) {
        return run_buffered(dirname(__FILE__) . '/panel.tpl');
    }
    $site = data_read('.config', 'site');
    if (!is_array($site)) {
        $site = [];
    }

    $languages = $site['languages'] ?? [];
    if (!is_array($languages)) {
        $languages = [];
    }

    set_variable('_ss.name_json', htmlspecialchars(json_encode($site['name'] ?? '', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'));
    set_variable('_ss.description_json', htmlspecialchars(json_encode($site['description'] ?? '', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'));
    set_variable('_ss.languages_json', htmlspecialchars(json_encode(array_values($languages), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'));
    set_variable('_ss.side_json', htmlspecialchars(json_encode($site['nimblybar']['side'] ?? '', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'));
    set_variable('_ss.direction_json', htmlspecialchars(json_encode($site['direction'] ?? '', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'));
    set_variable('_ss.rtl_json', htmlspecialchars(json_encode(site_settings_rtl_languages()), ENT_QUOTES, 'UTF-8'));

    $catalog = site_settings_language_catalog();
    $language_rows = [];
    foreach (array_values($languages) as $code) {
        $language_rows[] = ['code' => (string)$code, 'label' => $catalog[$code] ?? strtoupper((string)$code)];
    }
    set_variable('_ss.language_rows_json', htmlspecialchars(json_encode($language_rows, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'));
    set_variable('_ss.language_catalog_json', htmlspecialchars(json_encode($catalog, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'));

    site_settings_set_features();
    set_variable('_ss.pages_row', site_settings_row('pages'));
    set_variable('_ss.navigation_row', site_settings_row('navigation'));
    set_variable('_ss.content', run_buffered(dirname(__FILE__) . '/settings.tpl'));

    return run_buffered(dirname(__FILE__) . '/panel.tpl');
}

/** Pages and navigation switches: system managers only, and only when there is something to switch on. */
function site_settings_set_features(): void
{
    load_library('managed-pages');
    load_library('managed-navigation');
    $manager = managed_pages_is_system_manager();
    $types = [];
    foreach (managed_pages_types() as $id => $definition) {
        if (is_array($definition) && !empty($definition['name']) && !empty($definition['template'])) {
            $types[] = ['id' => (string)$id, 'name' => (string)$definition['name'], 'description' => (string)($definition['description'] ?? '')];
        }
    }
    $config = managed_pages_config();
    $enabled_types = is_array($config['enabled_page_types'] ?? null) ? $config['enabled_page_types'] : array_column($types, 'id');
    $features = [
        'pages' => managed_pages_feature_enabled(),
        'navigation' => managed_navigation_feature_enabled(),
        'page_types' => array_values($enabled_types),
    ];
    $attribute = fn($value) => htmlspecialchars(json_encode($value, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
    set_variable('_ss.features_json', $attribute($features));
    set_variable('_ss.page_types_json', $attribute($types));
    set_variable('_ss.show_pages', $manager && data_exists('pages') ? 'true' : 'false');
    set_variable('_ss.show_navigation', $manager && managed_navigation_slots() !== [] ? 'true' : 'false');
}

function site_settings_row(string $feature): string
{
    return get_variable('_ss.show_' . $feature) === 'true'
        ? run_buffered(dirname(__FILE__) . '/settings-' . $feature . '.tpl')
        : '';
}
