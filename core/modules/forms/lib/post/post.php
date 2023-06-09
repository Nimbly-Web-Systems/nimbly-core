<?php

function post_sc($params) {
    if ($_SERVER['REQUEST_METHOD'] != 'POST' || empty($_POST['form_key'])) {
        if (empty($_POST) && empty($_FILES) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
            // file exceeds upload limit
            $GLOBALS['SYSTEM']['validation_errors']['_global'][] = "[text validate_file_exceeds_limit]";
        }
        return; 
    }
    load_library("session");
    $csrf_pass = false;
    if (session_resume()) {
        $csrf_pass = isset($_SESSION['key']) && $_SESSION['key'] === post_get('form_key');
    } else {
        $csrf_pass = isset($_COOKIE['key']) && $_COOKIE['key'] === post_get('form_key');
    }
    if ($csrf_pass !== true) {
        load_library("log");
        log_system("Post: session key or cookie key does not match form key");
        return; //suspicious, could be a CSRF attack.. do nothing.
    }



    load_library("validate");
    $id_suffix = "";
    if (isset($_POST['form_id'])) {
        load_library("sanitize");
        $id_suffix = '_' . sanitize_id(post_get("form_id"));
    }

    $validate_include_file = $GLOBALS['SYSTEM']['uri_path'] . '/validate' . $id_suffix . '.inc';
    if (!file_exists($validate_include_file)) {
        //force handling validation by ensuring the validation file existst
        $GLOBALS['SYSTEM']['validation_errors']['_global'][] = "[text validate_missing]";
        return;
    }

    include_once($validate_include_file);
    if (validate_error_count() == 0) {
        //only handle the form post if there are no validation errors
        @include_once($GLOBALS['SYSTEM']['uri_path'] . '/post' . $id_suffix . '.inc');
    }
}

function post_get($input_name, $default = null) {
    if (isset($_POST[$input_name])) {
        return filter_input(INPUT_POST, $input_name, FILTER_SANITIZE_STRING);
    }
    return $default;
}

function post_get_all() {
    $result = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);
    unset($result["form_id"]);
    unset($result["form_key"]);
    return $result;
}
