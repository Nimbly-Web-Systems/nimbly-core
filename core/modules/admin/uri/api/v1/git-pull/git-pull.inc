<?php

load_library('get');

function git_pull_sc() {

    $response['error'] = true;
    $response['status'] = "";

    // Only the project itself or its ext repository, each with its own right.
    $ext = get_variable('dir', '', 'url') === 'ext';
    if (!function_exists('access_by_feature')) {
        load_library('access');
    }
    if (!access_by_feature($ext ? 'pull-ext-updates' : 'pull-core-updates')) {
        $response['status'] = 'not allowed';
        return json_encode($response);
    }
    $dir = $GLOBALS['SYSTEM']['file_base'] . ($ext ? 'ext' : '');
    if (@chdir($dir) !== true) {
        $response['status'] = "could not change directory to " . $dir;
        return json_encode($response);
    }

    exec('git pull 2>&1', $pull_output, $pull_code);
    $response['status'] .= implode("\n", $pull_output);
    $response['error'] = $pull_code !== 0;

    load_library('app-build');
    $response['assets_stale'] = app_build_stale();

    return json_encode($response);
}
