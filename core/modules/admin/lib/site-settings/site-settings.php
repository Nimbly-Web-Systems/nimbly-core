<?php

load_library('data');
load_library('set');

function site_settings_sc($params)
{
    $site = data_read('.config', 'site');
    if (!is_array($site)) {
        $site = [];
    }

    $languages = $site['languages'] ?? [];
    if (!is_array($languages)) {
        $languages = [];
    }

    set_variable('_ss.name_json', htmlspecialchars(json_encode((string)($site['name'] ?? ''), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'));
    set_variable('_ss.description_json', htmlspecialchars(json_encode($site['description'] ?? '', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'));
    set_variable('_ss.languages_json', htmlspecialchars(json_encode(array_values($languages), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'));
    set_variable('_ss.side_json', htmlspecialchars(json_encode((string)($site['nimblybar']['side'] ?? 'left'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'));

    return run_buffered(dirname(__FILE__) . '/panel.tpl');
}
