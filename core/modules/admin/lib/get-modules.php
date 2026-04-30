<?php

function get_modules_sc() {
	load_library("set");
    load_library("data");
    $result = array();

    global $SYSTEM;
	foreach ($SYSTEM['env_paths'] as $env_path) {
        $path = $SYSTEM['file_base'] . $env_path . '/modules';
        if (!file_exists($path)) {
        	continue;
        }
        $modules = scandir($path);
        unset($modules[0]);
        unset($modules[1]);
        foreach ($modules as $module) {
            if ($module[0] === '.') {
                continue;
            }
            $k = $module . $env_path;
        	if (isset($modules[$k])) {
        		continue;
        	}
        	$install_file = $path . '/' . $module . '/.install.inc';
            if (!file_exists($install_file)) {
                continue;
            }
        	$result[$k] = array(
        		"name" => $module,
        		"env" => $env_path,
        		"path" => $path . '/' . $module
        	);
        }
	}
	set_variable("data_modules", $result);
}
