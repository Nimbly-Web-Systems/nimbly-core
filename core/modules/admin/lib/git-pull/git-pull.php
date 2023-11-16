<?php

load_library('get');

// @doc pulls version from git repository
function git_pull_sc() {

    $response['error'] = true;
    $response['status'] = "";

    $dir = get_variable('dir', '');
    if (@chdir($dir) !== true) {
        $response['status'] = "could not change directory to " . $dir;
        return json_encode($response);
    }

    /* pull latest version */
    $pull_cmd = "git pull";
    $pull_result = shell_exec($pull_cmd . " 2>&1");
    $response['status'] .= $pull_result;

    /* return status */
    return json_encode($response);
}
