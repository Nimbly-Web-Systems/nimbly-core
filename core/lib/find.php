<?php

global $SYSTEM;
$SYSTEM['modules']['root'] = '/';

/**
 * @doc Core functions for finding implementations of uri's, templates and libraries. 
 */

function find_uri($name, $tpl_name = "index.tpl") {
    return find_path($name, 'uri', $tpl_name);
}

/**
 * file_exists() for a path inside ext, core or one of their modules. A page asks
 * for thousands of names that no module has, so the second folder level
 * (tpl/<name>, uri/<name>) is checked against one listing per folder and the
 * disk is only asked when the name is there.
 */
function find_exists($root, $rel) {
    global $SYSTEM;
    $parts = array_values(array_filter(explode('/', $rel), 'strlen'));
    if (count($parts) < 2) {
        return file_exists($root . $rel);
    }
    $dir = $root . $parts[0];
    if (!isset($SYSTEM['find_listings'][$dir])) {
        $entries = is_dir($dir) ? @scandir($dir) : [];
        // false: a folder that cannot be listed is asked on disk every time
        $SYSTEM['find_listings'][$dir] = $entries === false ? false : array_flip($entries);
    }
    $listing = $SYSTEM['find_listings'][$dir];
    if ($listing !== false && !isset($listing[$parts[1]])) {
        return false;
    }
    return file_exists($root . $rel);
}

/** Call after creating a template, route or library folder that is looked up later in the same run. */
function find_forget() {
    unset($GLOBALS['SYSTEM']['find_listings']);
}

function find_modules_once() {
    static $modules_discovered = false;
    if (!$modules_discovered) {
        find_all_modules();
        $modules_discovered = true;
    }
}

function find_template($name, $dir = null) {
    global $SYSTEM;

    if (isset($dir)) {
        foreach ($SYSTEM['env_paths'] as $env_path) {
            foreach ($SYSTEM['modules'] as $module_path) {
                $root = $SYSTEM['file_base'] . $env_path . $module_path;
                if (find_exists($root, $dir . '/' . $name . ".tpl")) {
                    return $root . $dir . '/' . $name . ".tpl";
                }
            }
        }
    }

    $path = find_path($SYSTEM['uri'] ?? '', 'uri', $name . ".tpl");
    if ($path !== false) {
        return $path;
    }
    $path = find_path($name, 'tpl');
    if ($path !== false) {
        return $path;
    }
    if (!empty($SYSTEM['sc_stack']) && count($SYSTEM['sc_stack']) > 0) {
        foreach ($SYSTEM['sc_stack'] as $sc_stack_item) {
            $sc_name = key($sc_stack_item);
            $path = find_path($sc_name, 'tpl', $name . ".tpl");
            if ($path !== false) {
                return $path;
            }
        }
    }
    return false;
}

function find_library($name) {
    global $SYSTEM;
    find_modules_once();

    if (!empty($SYSTEM['uri_path'])) {
        $local = $SYSTEM['uri_path'] . '/' . $name . '.inc';
        if (file_exists($local) && !infinite_loop($local)) {
            return $local;
        }
    }

    if (!empty($SYSTEM['sc_stack'])) {
        foreach (array_reverse($SYSTEM['sc_stack']) as $sc_stack_item) {
            $local = dirname(current($sc_stack_item)) . '/' . $name . '.inc';
            if (file_exists($local) && !infinite_loop($local)) {
                return $local;
            }
        }
    }

    foreach ($SYSTEM['env_paths'] as $env_path) {
        foreach ($SYSTEM['modules'] as $module_path) {
            // asked on disk every time: a library missing now may exist on a later call
            $result = $SYSTEM['file_base'] . $env_path . $module_path . 'lib/' . $name . ".php";
            if (file_exists($result) && !infinite_loop($result)) {
                return $result;
            }
        }
    }

    return find_path($name, 'lib', $name . ".php");
}

function infinite_loop($path) {
    global $SYSTEM;
    if (!empty($SYSTEM['sc_stack'])) {
        foreach ($SYSTEM['sc_stack'] as $sc_level) {
            $parent_path = current($sc_level);
            if ($parent_path == $path) {
                return true;
            }
        }
    }
    return false;
}

function find_path($name, $path, $target = "index.tpl") {
    global $SYSTEM;
    find_modules_once();
    foreach ($SYSTEM['env_paths'] as $env_path) {
        foreach ($SYSTEM['modules'] as $module_path) {
            $root = $SYSTEM['file_base'] . $env_path . $module_path;
            $rel = $path . '/' . $name . '/' . $target;
            $result = $root . $rel;
            if (find_exists($root, $rel) && !infinite_loop($result)) {
                return $result;
            }
        }
    }
    return false;
}

function find_all_modules($sub = '') {
    global $SYSTEM;
    foreach ($SYSTEM['env_paths'] as $env_path) {
        $path = $SYSTEM['file_base'] . $env_path . '/modules';
        if (file_exists($path)) {
            $modules = scandir($path);
            unset($modules[0]);
            unset($modules[1]);
            foreach ($modules as $module) {
                if (empty($sub) || file_exists($path . '/' . $module . '/' . $sub)) {
                    $SYSTEM['modules'][$module] = '/modules/' . $module . '/';
                }
            }
        }
    }
}

function load_library($name) {
    static $loaded = [];
    if (!empty($loaded[$name])) {
        return;
    }
    $path = find_library($name);
    if ($path === false) {
        return false;
    }

    require_once($path);
    $loaded[$name] = true;
    return $path;
}

function load_libraries($libs) {
    if (is_string($libs)) {
        return load_library($libs);
    }
    foreach ($libs as $lib) {
        if (is_string($lib)) {
            load_library($lib);
        }
    }
}

function load_module($name) {
    // No-op — all modules are auto-discovered by find_path().
    // Kept for backward compatibility with [#module name#] shortcode usage.
}

function find_sc($params) {
    $dir = "";
    $name = "";
    foreach ($params as $key => $value) {
        if ($key == "dir") {
            $dir = $value;
        } else {
            $name = $value;
        }
    }
    return find_template($name, $dir);
}
