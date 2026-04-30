<?php

load_library('url-key');

function create_settings_sc () {
    $settings_id = get_variable('uuid', url_key_sc());
    if (empty($settings_id)) {
        return;
    }
    if (data_exists('.config', $settings_id)) {
        return;
    }
    data_create('.config', $settings_id, []);
}