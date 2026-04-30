<?php

function form_key_sc($params) { 
    $key = form_key_get();
    if (get_single_param_value($params, "plain", true, false)) {
        return $key;
    }
    $do_x_init = get_single_param_value($params, "x-init", true, false);
    $x_init_key = $do_x_init? 'x-init="form_data.form_key=\'' . $key . '\'"' : '';
    $result = '<input type="hidden" name="form_key" value="' . $key . '"' . $x_init_key . ' />';
    if (!empty($params)) {
        if (isset($params['name'])) {
            $name = $params['name'];
        } else {
            $name = current($params);
        }
        $x_init_name = $do_x_init? 'x-init="form_data.form_id=\'' . $name . '\'"' : '';
        $result .= '<input type="hidden" name="form_id" value="' . $name  . '"' . $x_init_name . ' />';
    }
    return $result;
}

function form_key_get() {
    load_library('session');
     if (session_resume() && isset($_SESSION['key'])) {
        $key = $_SESSION['key'];
    } else if (isset($_COOKIE['key'])) {
        $key = $_COOKIE['key'];
    } else {
        $key = md5(uniqid(rand(), true));
        setcookie('key', $key, time() + (30*86400), "/");
    }
    return $key;
}
