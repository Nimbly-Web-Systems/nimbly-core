<?php

/**
 * @doc `[module (module names)]` loads one or more modules by name, e.g.: `[module user forms]`
 */
function module_sc($params) {
    foreach ($params as $key => $value) {
        if ($key === $value) {
            load_module($key);
        }
    }
    return null;
}

/**
 * Whether a module's install step (php core/cli/nimbly.php module:install <name>)
 * has been run in this environment. Backed by the .state resource, the same
 * key-value pattern already used for scheduler and fatal-alert state.
 */
function module_is_installed($name) {
    load_library('data');
    return data_exists('.state', 'module:' . $name);
}
