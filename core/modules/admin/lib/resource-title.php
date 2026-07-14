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

function resource_title(string $resource, array $record): string
{
    $field = resource_title_field($resource);
    if ($field !== null && !empty($record[$field])) {
        $value = $record[$field];
        if (is_array($value)) {
            load_library('get');
            $value = get_i18n_resolve($value);
        }
        if (!is_array($value)) {
            return (string)$value;
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
