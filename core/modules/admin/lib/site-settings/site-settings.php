<?php

load_library('data');
load_library('set');

function site_settings_sc($params)
{
    $site = data_read('.config', 'site');
    if (!is_array($site)) {
        $site = [];
    }

    set_variable('_ss.name_json', htmlspecialchars(json_encode((string)($site['name'] ?? ''), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'));
    set_variable('_ss.description_json', htmlspecialchars(json_encode((string)($site['description'] ?? ''), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'));
    set_variable('_ss.side_json', htmlspecialchars(json_encode((string)($site['nimblybar']['side'] ?? 'right'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'));

    return run_buffered(dirname(__FILE__) . '/panel.tpl');
}
