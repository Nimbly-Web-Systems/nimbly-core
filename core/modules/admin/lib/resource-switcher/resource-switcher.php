<?php

load_library('data');
load_library('resource-title');
load_library('set');
load_library('access');

function resource_switcher_sc($params)
{
    $resource = get_param_value($params, 'resource', current($params));
    $current_uuid = (string)(get_param_value($params, 'uuid', end($params)) ?? '');
    if (empty($resource)) {
        return;
    }

    $view_mode = get_param_value($params, 'view', get_variable('resource-switcher-view', ''));
    $record_query = $view_mode === '1' ? '?view=1' : '';
    $add_url = '/nb-admin/' . $resource . '/add';
    $can_add = access_by_feature('create-' . $resource);
    $count = count(data_list($resource));

    if ($count <= 8) {
        return resource_switcher_pills($resource, $current_uuid, $add_url, $record_query, $can_add);
    }
    if ($count <= 100) {
        return resource_switcher_select($resource, $current_uuid, $add_url, $record_query, $can_add);
    }
    return resource_switcher_live_search($resource, $add_url, $record_query, $can_add);
}

function resource_switcher_pills(string $resource, string $current_uuid, string $add_url, string $record_query = '', bool $can_add = false): string
{
    $items_html = '';
    foreach (data_read($resource) as $uuid => $record) {
        set_variable_dot('_pill', [
            'url' => htmlspecialchars('/nb-admin/' . $resource . '/' . $uuid . $record_query, ENT_QUOTES, 'UTF-8'),
            'title' => htmlspecialchars(resource_title($resource, $record), ENT_QUOTES, 'UTF-8'),
            'active_class' => $uuid === $current_uuid
                ? 'bg-cnormal text-white shadow-sm'
                : 'border border-neutral-300 bg-white text-neutral-700 hover:bg-neutral-100',
        ]);
        $items_html .= run_buffered(dirname(__FILE__) . '/pill.tpl');
        clear_variable_dot('_pill');
    }

    set_variable('_rs.items', $items_html);
    resource_switcher_set_add($add_url, 'add-pill.tpl', $can_add);
    return run_buffered(dirname(__FILE__) . '/pills.tpl');
}

function resource_switcher_select(string $resource, string $current_uuid, string $add_url, string $record_query = '', bool $can_add = false): string
{
    $options_html = '';
    foreach (data_read($resource) as $uuid => $record) {
        $title = htmlspecialchars(resource_title($resource, $record), ENT_QUOTES, 'UTF-8');
        $url = htmlspecialchars('/nb-admin/' . $resource . '/' . $uuid . $record_query, ENT_QUOTES, 'UTF-8');
        $selected = $uuid === $current_uuid ? ' selected' : '';
        $options_html .= '<option value="' . $url . '"' . $selected . '>' . $title . '</option>';
    }

    set_variable('_rs.options', $options_html);
    resource_switcher_set_add($add_url, 'add-control.tpl', $can_add);
    return run_buffered(dirname(__FILE__) . '/select.tpl');
}

function resource_switcher_live_search(string $resource, string $add_url, string $record_query = '', bool $can_add = false): string
{
    set_variable('_rs.resource', htmlspecialchars($resource, ENT_QUOTES, 'UTF-8'));
    set_variable('_rs.title_field', htmlspecialchars((string)(resource_title_field($resource) ?? ''), ENT_QUOTES, 'UTF-8'));
    set_variable('_rs.record_query', htmlspecialchars($record_query, ENT_QUOTES, 'UTF-8'));
    resource_switcher_set_add($add_url, 'add-control.tpl', $can_add);
    return run_buffered(dirname(__FILE__) . '/live-search.tpl');
}

function resource_switcher_set_add(string $add_url, string $template, bool $can_add): void
{
    set_variable('_rs.add_url', htmlspecialchars($add_url, ENT_QUOTES, 'UTF-8'));
    set_variable('_rs.add', $can_add ? run_buffered(dirname(__FILE__) . '/' . $template) : '');
}
