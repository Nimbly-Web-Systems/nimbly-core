<?php

// @doc pulls version from git repository
function pull_sc($params) {

    $response['error'] = true;
    $response['status'] = "";

    /* change directory */
    $dir = $_POST['path'];
    $response['status'] = 'cd ' . $dir . PHP_EOL;
    if (@chdir($dir) !== true) {
        $response['status'] .= "\ncould not change directory to " . $dir;
        return json_encode($response);
    }

    /* pull latest version */
    $pull_cmd = "git pull";
    $response['status'] .= $pull_cmd . PHP_EOL;
    $pull_result = shell_exec($pull_cmd . " 2>&1");
    $response['status'] .= $pull_result;
    $response['status'] .= "...done";

    /* return status */
    return json_encode($response);
}
