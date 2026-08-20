<?php

load_library('get');
load_library('base-url');
load_library('fmt');
load_library('text');
load_library('util');

function view_resource_record_sc($params)
{
    $resource = get_param_value($params, 'resource', current($params)) ?? get_variable('data.resource');
    $uuid = get_param_value($params, 'uuid', end($params)) ?? get_variable('data.uuid');
    $record = get_variable('record') ?? [];
    $fields = get_variable('data.fields') ?? [];
    $languages = get_variable('data.translation_languages') ?? [];

    if (empty($resource) || empty($uuid) || !is_array($record) || !is_array($fields)) {
        return '';
    }

    $translation_languages = is_array($languages) && count($languages) > 1
        ? array_values(array_filter($languages, 'is_string'))
        : [];
    $default_language = $translation_languages[0] ?? '';
    $rows = '';
    foreach ($fields as $field_id => $field) {
        if (!is_array($field)) {
            continue;
        }
        $rows .= view_resource_record_row(
            (string)$field_id,
            $field,
            $record[$field_id] ?? null,
            $translation_languages
        );
    }

    $rows .= view_resource_record_row('uuid', ['name' => 'UUID', 'type' => 'text'], $uuid);

    $tabs = view_resource_record_translation_tabs($translation_languages, $default_language);
    if ($tabs !== '') {
        set_variable('record-view-translation-tabs', '1');
    }

    $details = '<div class="overflow-hidden rounded-md border border-neutral-200 bg-white shadow-sm">'
        . '<dl class="divide-y divide-neutral-200">'
        . $rows
        . '</dl>'
        . '</div>';
    if ($tabs === '') {
        return $details;
    }

    $alpine = $tabs === '' ? '' : ' x-data="{ lang: \''
        . htmlspecialchars($default_language, ENT_QUOTES, 'UTF-8')
        . '\' }" x-init="$store.form_language.current = lang"';

    return '<div' . $alpine . '>'
        . $tabs
        . $details
        . '</div>';
}

function view_resource_record_row(string $field_id, array $field, $value, array $languages = []): string
{
    $label = htmlspecialchars((string)($field['name'] ?? ucfirst(str_replace(['-', '_'], ' ', $field_id))), ENT_QUOTES, 'UTF-8');
    $type = (string)($field['type'] ?? 'text');
    $body = !empty($field['i18n']) && !empty($languages)
        ? view_resource_record_localized_value($type, $value, $languages)
        : view_resource_record_value($type, $value);

    return '<div class="grid gap-2 px-4 py-4 sm:grid-cols-[14rem_minmax(0,1fr)] sm:gap-6">'
        . '<dt class="text-sm font-semibold text-neutral-700">' . $label . '</dt>'
        . '<dd class="min-w-0 text-sm text-neutral-900">' . $body . '</dd>'
        . '</div>';
}

function view_resource_record_translation_tabs(array $languages, string $default_language): string
{
    if (count($languages) < 2 || $default_language === '') {
        return '';
    }

    $html = '<ul class="mb-10 flex flex-row" role="tablist">';
    foreach ($languages as $language) {
        $escaped = htmlspecialchars($language, ENT_QUOTES, 'UTF-8');
        $html .= '<li><button type="button" role="tab" class="cursor-pointer border-b-2 px-4 py-2 text-xs uppercase text-gray-600 hover:font-bold hover:text-black"'
            . ' :class="lang==\'' . $escaped . '\'? \'border-b-primary\' : \'border-b-transparent\'"'
            . ' :aria-selected="lang==\'' . $escaped . '\'"'
            . ' @click="lang=\'' . $escaped . '\'; $store.form_language.current=lang">'
            . view_resource_record_text($language)
            . '</button></li>';
    }
    return $html . '</ul>';
}

function view_resource_record_localized_value(string $type, $value, array $languages): string
{
    $translations = is_array($value) ? $value : [];
    $html = '';
    foreach ($languages as $language) {
        $escaped = htmlspecialchars($language, ENT_QUOTES, 'UTF-8');
        $html .= '<div x-cloak x-show="lang==\'' . $escaped . '\'">'
            . view_resource_record_value($type, $translations[$language] ?? null)
            . '</div>';
    }
    return $html;
}

function view_resource_record_value(string $type, $value): string
{
    if (view_resource_record_empty($value)) {
        return '<span class="text-neutral-400">' . view_resource_record_text('Empty') . '</span>';
    }

    if (is_array($value)) {
        if (view_resource_record_is_list($value)) {
            if ($type === 'gallery') {
                return view_resource_record_gallery($value);
            }
            return view_resource_record_json($value);
        }
        return view_resource_record_map($value, $type);
    }

    $string = (string)$value;
    switch ($type) {
        case 'boolean':
            return fmt_sc([
                'val' => $value,
                'type' => 'boolean',
                'boolean' => t('Yes') . '|' . t('No'),
                'style' => 'badge',
            ]);
        case 'html':
            return '<div class="prose max-w-none">' . normalize_media_base_url($string) . '</div>';
        case 'textarea':
            return '<div class="whitespace-pre-wrap">' . htmlspecialchars($string, ENT_QUOTES, 'UTF-8') . '</div>';
        case 'url':
            $url = htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
            return '<a class="text-cnormal underline-offset-2 hover:underline" href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $url . '</a>';
        case 'email':
            $email = htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
            return '<a class="text-cnormal underline-offset-2 hover:underline" href="mailto:' . $email . '">' . $email . '</a>';
        case 'image':
            return view_resource_record_image($string);
        case 'file':
        case 'upload':
            return view_resource_record_file($string);
        case 'date':
            $time = is_numeric($string) ? (int)$string : strtotime($string);
            if ($time !== false) {
                return htmlspecialchars(date('Y-m-d', $time), ENT_QUOTES, 'UTF-8');
            }
            return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
        default:
            return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}

function view_resource_record_empty($value): bool
{
    return $value === null || $value === '' || $value === [];
}

function view_resource_record_is_list(array $value): bool
{
    return array_keys($value) === range(0, count($value) - 1);
}

function view_resource_record_map(array $value, string $type): string
{
    $html = '<div class="space-y-3">';
    foreach ($value as $key => $entry) {
        $html .= '<div class="rounded border border-neutral-200 bg-neutral-50 p-3">'
            . '<div class="mb-1 font-mono text-xs font-semibold uppercase tracking-wide text-neutral-500">'
            . htmlspecialchars((string)$key, ENT_QUOTES, 'UTF-8')
            . '</div>'
            . '<div>' . view_resource_record_value($type, $entry) . '</div>'
            . '</div>';
    }
    return $html . '</div>';
}

function view_resource_record_json(array $value): string
{
    return '<pre class="overflow-x-auto rounded bg-neutral-100 p-3 text-xs text-neutral-800">'
        . htmlspecialchars(json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8')
        . '</pre>';
}

function view_resource_record_image(string $uuid): string
{
    $uuid = htmlspecialchars($uuid, ENT_QUOTES, 'UTF-8');
    return '<a href="' . view_resource_record_base_url() . '/img/' . $uuid . '" target="_blank" rel="noopener noreferrer" class="inline-block">'
        . '<img src="' . view_resource_record_base_url() . '/img/' . $uuid . '/240x240f" alt="" class="h-32 w-32 rounded border border-neutral-200 bg-neutral-100 object-cover">'
        . '</a>';
}

function view_resource_record_gallery(array $uuids): string
{
    $html = '<div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">';
    foreach ($uuids as $uuid) {
        if (!is_scalar($uuid) || (string)$uuid === '') {
            continue;
        }
        $html .= view_resource_record_image((string)$uuid);
    }
    return $html . '</div>';
}

function view_resource_record_file(string $uuid): string
{
    $uuid = htmlspecialchars($uuid, ENT_QUOTES, 'UTF-8');
    return '<a class="text-cnormal underline-offset-2 hover:underline" href="' . view_resource_record_base_url() . '/download/' . $uuid . '" target="_blank" rel="noopener noreferrer">' . view_resource_record_text('Download file') . '</a>';
}

function view_resource_record_base_url(): string
{
    return htmlspecialchars(base_url_sc(), ENT_QUOTES, 'UTF-8');
}

function view_resource_record_text(string $key): string
{
    return htmlspecialchars(t($key), ENT_QUOTES, 'UTF-8');
}
