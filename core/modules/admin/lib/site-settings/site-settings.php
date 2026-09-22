<?php

load_library('data');
load_library('set');

/** Languages supported by the current two-letter runtime and settings UI. */
function site_settings_language_catalog(): array
{
    return [
        'da' => 'Danish', 'de' => 'German', 'en' => 'English',
        'es' => 'Spanish', 'fi' => 'Finnish', 'fr' => 'French',
        'it' => 'Italian', 'nl' => 'Dutch', 'no' => 'Norwegian',
        'pl' => 'Polish', 'pt' => 'Portuguese', 'sv' => 'Swedish',
    ];
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
        if ($old_languages !== [] && (
            count($languages) < count($old_languages)
            || count($languages) > count($old_languages) + 1
            || array_slice($languages, 0, count($old_languages)) !== $old_languages
        )) {
            data_error_set('VALIDATION_FAILED', 'languages:append-only');
            return false;
        }
        $catalog = site_settings_language_catalog();
        foreach (array_slice($languages, count($old_languages)) as $language) {
            if (!is_string($language) || !isset($catalog[$language])) {
                data_error_set('VALIDATION_FAILED', 'languages:unsupported');
                return false;
            }
        }
        $record['languages'] = $languages;
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
    set_variable('_ss.side_json', htmlspecialchars(json_encode($site['nimblybar']['side'] ?? 'left', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'));

    $catalog = site_settings_language_catalog();
    $language_rows = [];
    foreach (array_values($languages) as $index => $code) {
        $language_rows[] = ['code' => (string)$code, 'label' => $catalog[$code] ?? strtoupper((string)$code), 'fallback' => $index === 0];
    }
    set_variable('_ss.language_rows_json', htmlspecialchars(json_encode($language_rows, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'));
    set_variable('_ss.language_catalog_json', htmlspecialchars(json_encode($catalog, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'));

    load_library('managed-pages');
    $types = managed_pages_types();
    $managed_config = data_exists('.config', 'managed_pages') ? data_read('.config', 'managed_pages') : [];
    $has_policy = is_array($managed_config) && array_key_exists('enabled_page_types', $managed_config);
    $enabled = $has_policy && is_array($managed_config['enabled_page_types']) ? $managed_config['enabled_page_types'] : array_keys($types);
    $type_rows = [];
    foreach ($types as $id => $definition) {
        if (is_array($definition) && !empty($definition['name']) && !empty($definition['template'])) {
            $type_rows[] = ['id' => (string)$id, 'name' => (string)$definition['name'], 'description' => (string)($definition['description'] ?? '')];
        }
    }
    set_variable('_ss.page_types_json', htmlspecialchars(json_encode($type_rows, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'));
    set_variable('_ss.enabled_page_types_json', htmlspecialchars(json_encode(array_values($enabled), JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'));

    $section = (string)($_GET['section'] ?? 'general');
    if (!in_array($section, ['general', 'languages', 'page-templates'], true)) {
        $section = 'general';
    }
    set_variable('_ss.section', $section);
    $templates = ['general' => 'general.tpl', 'languages' => 'languages.tpl', 'page-templates' => 'page-templates.tpl'];
    set_variable('_ss.content', run_buffered(dirname(__FILE__) . '/' . $templates[$section]));

    return run_buffered(dirname(__FILE__) . '/panel.tpl');
}
