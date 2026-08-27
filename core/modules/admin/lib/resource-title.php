<?php

load_library('data');

function resource_title_field(string $resource): ?string
{
    $meta = data_meta($resource);
    if (!empty($meta['title_field']) && isset($meta['fields'][$meta['title_field']])) {
        return $meta['title_field'];
    }
    foreach (['name', 'title'] as $candidate) {
        if (isset($meta['fields'][$candidate])) {
            return $candidate;
        }
    }
    return null;
}

function resource_title_value(array $record, string $field): string
{
    $value = $record[$field] ?? '';
    if (is_array($value)) {
        load_library('get');
        $value = get_i18n_resolve($value);
    }
    return is_array($value) ? '' : trim((string)$value);
}

function resource_title(string $resource, array $record): string
{
    $meta = data_meta($resource);
    if (!empty($meta['title_fields']) && is_array($meta['title_fields'])) {
        $parts = array_filter(array_map(
            fn($field) => resource_title_value($record, (string)$field),
            $meta['title_fields']
        ), fn($value) => $value !== '');
        if (!empty($parts)) {
            return implode(' — ', $parts);
        }
    }
    $field = resource_title_field($resource);
    if ($field !== null) {
        $value = resource_title_value($record, $field);
        if ($value !== '') {
            return $value;
        }
    }
    return (string)($record['uuid'] ?? '');
}

function resource_title_sc($params)
{
    $resource = get_param_value($params, 'resource', current($params));
    $uuid = get_param_value($params, 'uuid', end($params));
    if (empty($resource) || empty($uuid) || !data_exists($resource, $uuid)) {
        return htmlspecialchars((string)$uuid, ENT_QUOTES, 'UTF-8');
    }
    return htmlspecialchars(resource_title($resource, data_read($resource, $uuid)), ENT_QUOTES, 'UTF-8');
}
