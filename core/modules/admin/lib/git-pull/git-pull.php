<?php

load_library('get');

// @doc pulls version from git repository
function git_pull_sc() {

    $response['error'] = true;
    $response['status'] = "";

    $dir = $GLOBALS['SYSTEM']['file_base'] . get_variable('dir', '');
    if (@chdir($dir) !== true) {
        $response['status'] = "could not change directory to " . $dir;
        return json_encode($response);
    }

    /* pull latest version */
    $pull_cmd = "git pull";
    $pull_result = shell_exec($pull_cmd . " 2>&1");
    $response['status'] .= $pull_result;

    if (stripos($pull_result, 'updating')) {
        $response['error'] = false;
    }

    /* return status */
    return json_encode($response);
}
