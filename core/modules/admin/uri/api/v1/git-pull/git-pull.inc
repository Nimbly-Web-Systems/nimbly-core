<?php

load_library('get');

function git_pull_sc() {

    $response['error'] = true;
    $response['status'] = "";

    $dir = $GLOBALS['SYSTEM']['file_base'] . get_variable('dir', '');
    if (@chdir($dir) !== true) {
        $response['status'] = "could not change directory to " . $dir;
        return json_encode($response);
    }

    $pull_result = shell_exec("git pull 2>&1");
    $response['status'] .= $pull_result;

    if (stripos($pull_result, 'updating') !== false) {
        $response['error'] = false;
    }

    return json_encode($response);
}
