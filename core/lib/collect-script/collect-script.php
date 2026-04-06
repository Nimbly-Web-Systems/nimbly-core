<?php

$GLOBALS['_collected_scripts'] = [];

function collect_script_sc($params)
{
    $path = get_param_value($params, 'path', current($params));
    if (empty($path)) {
        render_collected_scripts();
    } else {
        collect_script($path);
    }
    
}

function collect_script($path)
{
    if (!in_array($path, $GLOBALS['_collected_scripts'])) {
        $GLOBALS['_collected_scripts'][] = $path;
    }
}

function render_collected_scripts() {
    foreach ($GLOBALS['_collected_scripts'] as $path) {
        echo run_buffered($path);
    }   
}
