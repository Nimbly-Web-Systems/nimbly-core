<?php

load_library('managed-pages');
load_library('set');

/** Admin panel for the custom pages feature, shown above the Pages overview. */
function managed_pages_toggle_sc($params)
{
    $config = managed_pages_config();
    $types = [];
    foreach (managed_pages_types() as $id => $definition) {
        if (is_array($definition) && !empty($definition['name']) && !empty($definition['template'])) {
            $types[] = ['id' => (string)$id, 'name' => (string)$definition['name'], 'description' => (string)($definition['description'] ?? '')];
        }
    }
    $enabled_types = is_array($config['enabled_page_types'] ?? null)
        ? $config['enabled_page_types']
        : array_column($types, 'id');
    $flags = JSON_UNESCAPED_UNICODE;
    $attribute = fn($value) => htmlspecialchars(json_encode($value, $flags), ENT_QUOTES, 'UTF-8');
    set_variable('_mp.page_types_json', $attribute($types));
    set_variable('_mp.enabled_page_types_json', $attribute(array_values($enabled_types)));
    set_variable('_mp.enabled_json', managed_pages_feature_enabled() ? 'true' : 'false');
    return run_buffered(dirname(__FILE__) . '/toggle.tpl');
}
