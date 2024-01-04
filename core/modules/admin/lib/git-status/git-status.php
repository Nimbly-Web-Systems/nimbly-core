<?php

load_library('get');

// @doc gets a git status from repository
function git_status_sc() {

    $dir = get_variable('dir', '');

    $response['error'] = true;
    $response['dir'] = $dir;
    $response['status'] = "";
    $response['updates'] = 0;

    /* change directory */
    $dir = $GLOBALS['SYSTEM']['file_base'] . $dir;
    if (@chdir($dir) !== true) {
        $response['status'] = "could not change directory to " . $dir;
        return json_encode($response);
    }

    /* get status */
    $sh_result = shell_exec("git fetch 2>&1");
    $sh_result = shell_exec("git status 2>&1");
    $response['cmd'] = $sh_result;

    if (stripos($sh_result, 'not found')) {
        $response['status'] = 'git not found';
    } else if (stripos($sh_result, 'your branch is up to date')) {
        $response['status'] = 'up to date';
        $response['error'] = false;
    } else if (stripos($sh_result, 'your branch is behind')) {
        $response['error'] = false;
        $response['status'] = 'behind';
        preg_match('/(by )(\d)( commit[s]*)/', $sh_result, $matches);
        $response['updates'] = $matches[2] ?? 0;
    }

    /* return status */
    return json_encode($response);
}