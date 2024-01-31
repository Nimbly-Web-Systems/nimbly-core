<?php

/**
 * @doc * `[if condition(s) action]` performs action if condition(s) are true
 * @doc * action can be "tpl=nameoftemplate" to load a template, "echo=text to show" to display text or "redirect=somepage" to redirect to another page
 * @doc * condition compares a variable with a value, x=3, x=(empty), x=(not-empty)
 * @doc * `[if user=(empty) redirect=login]` redirects to login if variable user is empty
 */
function if_sc($params) {
    $condition = array();
    $negate = false;
    $or = false;
    $action = array();
    foreach ($params as $key => $value) {
        if ($key === "tpl" || $key === "tpl_else" || $key === "echo" || $key === "redirect")  {
            $action[$key] = $value;
        } else if ($key === "not") {
            $negate = true;
        } else if ($key === "or") {
            $or = true;
        } else {
            $condition[] = [$key => $value];
            if ($or) {
                //var_dump($condition);
            }
        }
    }
    if (empty($action)) {
        return;
    }
    $pass = !$or;
    load_library("get");
    foreach ($condition as $kv) {
        //loop through and test all conditions
        $b = if_condition(key($kv), current($kv), $negate);
        if ($or === true && $b) {
            $pass = true;
            break;
        } else if ($or !== true && $b !== true) {
            $pass = false;
            break;
        }
    }
    if ($pass) {
        //perform the action
        if_action($action);
    } else if (isset($action['tpl_else'])) {
        run_single_sc($action['tpl_else']);
    }
}

function if_action($action) {
    foreach ($action as $key => $value) {
        switch ($key) {
            case 'tpl': //run a template
                run_single_sc($value);
                break;
            case 'echo': //echo a value
                echo $value;
                break;
            case 'redirect': //redirect to url
                load_library("redirect");
                redirect($value);
                break;
        }
    }
}

function if_condition($key, $value, $negate = false) {
    $result = null;
    $eval = get_sc($key);
    if ($value === "(not-empty)") {
        $result = !empty($eval);
    } else if ($value === "(empty)") {
        $result = $eval === $value || empty($eval);
    } else {
        $result = $eval == $value;
    }
    return $negate? !$result : $result;
}
