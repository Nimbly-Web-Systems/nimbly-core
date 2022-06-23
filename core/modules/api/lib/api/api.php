<?php

load_library("json", "api");
load_library("data");
load_library("access", "user");
load_library("get");

function api_method_switch($func_prefix, $resource = null, $uuid = null) {
    $method = strtolower($_SERVER['REQUEST_METHOD']);
    $perm = $resource ?? $func_prefix;
    $access_feature = _api_access_str($method, $perm, $uuid);
    if (!api_access($access_feature, $perm)) {
        return json_result(array('message' => 'ACCESS_DENIED', 'needs' => $access_feature), 403);
    }
    $func_name = "{$func_prefix}_{$method}";
    if (function_exists($func_name)) {
        if (empty($uuid)) {
            return call_user_func($func_name, $resource);
        } else {
            return call_user_func($func_name, $resource, $uuid);
        }
    }
    return json_result(array('message' => 'METHOD_NOT_ALLOWED'), 405);
}

function api_method_switch_with_subkey($func_prefix, $resource, $subkey, $uuid) {
    $method = strtolower($_SERVER['REQUEST_METHOD']);
    $perm = $resource ?? $func_prefix;
    $access_feature = _api_access_str($method, $perm, $uuid);
    if (!api_access($access_feature, $perm)) {
        return json_result(array('message' => 'ACCESS_DENIED', 'needs' => $access_feature), 403);
    }
    $func_name = "{$func_prefix}_{$method}";
    if (function_exists($func_name)) {
        if (empty($uuid)) {
            return call_user_func($func_name, $resource . '/' . $subkey);
        } else {
            return call_user_func($func_name, $resource . '/' . $subkey, $uuid);
        }
    }
    return json_result(array('message' => 'METHOD_NOT_ALLOWED'), 405);
}

function _api_access_str($method, $perm, $uuid) {
    return sprintf('api_%1$s_%2$s_%3$s,api_%1$s_%2$s,api_(any)_%2$s,api_%1$s_(any),api_(any)', strtolower($method), $perm, $uuid);
}

function api_access($feature='api', $resource=false) {
    return get_variable('api_public', false) || api_key_access($feature) || api_user_access($feature, $resource);
}

function api_key_access($feature) {
    $key = get_variable('key', false);
    if (empty($key)) {
        return false;
    }
    // todo: check if api key exists and allows feature
    return false;
}

function api_user_access($feature, $resource = false) {
    if (access_by_feature($feature)) {
        return true;
    }
    if (access_by_feature('manage-content') && $resource && !in_array($resource, ['users', 'roles'])) {
        return true;
    }
    return false;
}

/*
 * Creates data array from json input
 * + Resolves primary key field
 * + Encrypts fields
 */
function api_json_input($resource) {
    $meta = data_meta($resource);
    if (isset($meta['pk'])) {
        $data = json_input(true, $meta['pk']);
    } else {
        $data = json_input();
    }
    if (isset($meta['encrypt'])) {
        load_library('salt');
        load_library('encrypt');
        $salt = salt_sc();
        $fs = explode(',', $meta['encrypt']);
        foreach ($fs as $f) {
            if (!isset($data[$f])) {
                continue;
            }
            $data['salt'] = $salt;
            $data[$f] = encrypt($data[$f], $salt);
        }
    }
    return $data;
}

function api_check_csrf(&$data) {
    if (!isset($data['form_key'])) {
        load_library('log');
        log_system('api: no csrf key set');
        return null;
    }
    $key = $data['form_key'];
    load_library("session");
    $csrf_pass = false;
    if (session_resume()) {
        $csrf_pass = isset($_SESSION['key']) && $_SESSION['key'] === $key;
    } else {
        $csrf_pass = isset($_COOKIE['key']) && $_COOKIE['key'] === $key;
    }
    if ($csrf_pass !== true) {
        load_library("log");
        log_system("session key or cookie key does not match form key");
        return false; //suspicious, could be a CSRF attack.. do nothing.
    }
    unset($data['form_key']);
    if (isset($data['form_id'])) {
        unset($data['form_id']);
    }
    return true;
}

/*
 * Basic implementation on resource:
 */

function resource_get($resource) { // get all
    $modified = data_modified($resource);
    header_not_modified($modified);
    $result = data_read($resource);
    return json_result([$resource => $result, 'count' => count($result)], 200, $modified);
}

function resource_post($resource) { // create new
    $data = api_json_input($resource);
    $csrf_check = api_check_csrf($data);
    if ($csrf_check === false) { //can also be null, if no key is set
        return json_result(array('message' => 'INVALID_DATA'), 400);   
    }
    $uuid = $data['uuid'];
    if (data_exists($resource, $uuid)) {
        return json_result(array('message' => 'RESOURCE_EXISTS'), 409);
    }
    if (!_data_validate($resource, $uuid, $data)) {
        return json_result(array('message' => 'INVALID_DATA'), 400);  
    }
    if (data_create($resource, $uuid, $data)) {
        return json_result(array(
            $resource => array($uuid => $data),
            'count' => 1,
            'message' => 'RESOURCE_CREATED'),
        201);
    }
    return json_result(array('message' => 'RESOURCE_CREATE_FAILED'), 500);
}

function resource_put($resource) { // update multiple
    $data = api_json_input($resource);
    $csrf_check = api_check_csrf($data);
    if ($csrf_check === false) { //can also be null, if no key is set
        return json_result(array('message' => 'INVALID_DATA'), 400);   
    }
    $result = data_update($resource, null, $data);

    if (is_array($result)) {
        return json_result(array(
            $resource => $result,
            'count' => count($result),
            'message' => 'RESOURCE_UPDATED'
        ), 201);
    }
    return json_result(array('message' => 'RESOURCE_UPDATE_FAILED'), 500);
}

function resource_delete($resource) { // delete all
    $delete_count = data_delete($resource);
    if ($delete_count !== false) {
        return json_result(array('message' => 'RESOURCE_DELETED', 'count' => (int)$delete_count), 200);
    }
    return json_result(array('message' => 'RESOURCE_DELETE_FAILED'), 500);
}

/*
 * Basic implementation on resource item:
 */

function resource_id_get($resource, $uuid) { // read one
    $modified = data_modified($resource, $uuid);
    header_not_modified($modified);
    return json_result(array($resource => array($uuid => data_read($resource, $uuid)), 'count' => 1), 200, $modified);
}

function resource_id_post($resource, $uuid) { // create new with uuid
    if (data_exists($resource, $uuid)) {
        return json_result(array('message' => 'RESOURCE_EXISTS'), 409);
    }
    $data = api_json_input($resource);
    $csrf_check = api_check_csrf($data);
    if ($csrf_check === false) { //can also be null, if no key is set
        return json_result(array('message' => 'INVALID_DATA'), 400);   
    }
    $data['uuid'] = $uuid;
    $result = data_create($resource, $uuid, $data);
    if ($result) {
        return json_result(array(
            $resource => array($uuid => $result),
            'count' => 1,
            'message' => 'RESOURCE_CREATED'
        ), 201);
    }
    return json_result(array('message' => 'RESOURCE_CREATE_FAILED'), 500);
}

function resource_id_put($resource, $uuid) { // update one
    $data = api_json_input($resource);
    $csrf_check = api_check_csrf($data);
    if ($csrf_check === false) { //can also be null, if no key is set
        return json_result(array('message' => 'INVALID_DATA'), 400);   
    }
    $data['uuid'] = $uuid;
    $result = data_update($resource, $uuid, $data);
    if (is_array($result)) {
        return json_result(array(
            $resource => array($uuid => $result),
            'count' => 1,
            'message' => 'RESOURCE_UPDATED'
        ), 201);
    }
    return json_result(array('message' => 'RESOURCE_UPDATE_FAILED'), 500);
}

function resource_id_delete($resource, $uuid) { // delete one
    if (data_delete($resource, $uuid)) {
        return json_result(array('message' => 'RESOURCE_DELETED', 'count' => 1), 200);
    }
    return json_result(array('message' => 'RESOURCE_DELETE_FAILED'), 500);
}
