<?php

load_library("json");
load_library("data");
load_library("access");
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

function _api_access_str($method, $perm, $uuid) {
    $method = strtolower($method);
    $operation = api_method_operation($method);
    $features = [
        sprintf('api_%1$s_%2$s_%3$s', $method, $perm, $uuid),
        sprintf('api_%1$s_%2$s', $method, $perm),
        sprintf('api_(any)_%1$s', $perm),
        sprintf('api_%1$s_(any)', $method),
        'api_(any)',
    ];
    if ($operation !== null) {
        $features[] = $operation . '-' . $perm;
        $features[] = 'manage-' . $perm;
    }
    return implode(',', $features);
}

function api_method_operation($method): ?string {
    $map = [
        'get' => 'view',
        'post' => 'create',
        'put' => 'edit',
        'patch' => 'edit',
        'delete' => 'delete',
    ];
    return $map[strtolower($method)] ?? null;
}

function api_access($feature='api', $resource=false) {
    return api_public_access($feature) || api_user_access($feature, $resource) || api_token_access($feature, $resource);
}

function api_public_access($feature) {
    $key = filter_input(INPUT_GET, 'key', FILTER_SANITIZE_SPECIAL_CHARS);
    if (empty($key)) {
        $key = get_variable('key');
    }
    if (empty($key)) {
        return false;
    }
    if ($key !== $_SERVER['PEPPER']) {
        load_library('form-key');
        if ($key !== form_key_get()) {
            return false;
        }
    }
    $api_allowed = explode(',', get_variable('api.allow', ''));
    $features = explode(',', $feature);
    $grants = array_intersect($api_allowed, $features);
    return count($grants) >= 1;
}

function api_user_access($feature, $resource = false) {
     if (access_by_feature($feature)) {
        return true;
    }
    return false;
}

function api_token_access($feature, $resource = false) {
    $headers = getallheaders();

    if (empty($headers["Authorization"])) {
        return false;
    }

    list($type, $token) = explode(" ", $headers["Authorization"], 2);

    if (empty($token) || strcasecmp($type, 'Bearer') !== 0) {
        return false;
    }

    $users = data_filter(data_read('users', null, ['email', 'api']), 'api:(exists)');
    $user = null;

    foreach ($users as $uuid => $u) {
        if (!is_array($u) || !is_array($u['api'] ?? null)) {
            continue;
        }
        if (!empty($u['api']['access']) && ($u['api']['token'] ?? '') === $token) {
            $user = $u;
            $user['uuid'] = $uuid;
            break;
        }
    }

    if (empty($user) || empty($user['api']['expires']) || !is_numeric($user['api']['expires'])) {
        return false;
    }

    if (time() > $user['api']['expires']) {
        return false;
    }

    run_library('session');
    if (!_persist_user_roles($user['email'])) {
        return false;
    }

    _persist_user_features($user['email']);
    $_SESSION['username'] = $user['email'];
    $_SESSION['user_uuid'] = $user['uuid'];
    return api_user_access($feature, $resource);
}

/*
 * Creates data array from json input
 * + Encrypts fields
 */
function api_json_input($resource) {
    $meta = data_meta($resource);
    $data = json_input();
    if (isset($meta['encrypt'])) {
        load_library('util');
        load_library('encrypt');
        $salt = generate_salt();
        $fs = explode(',', $meta['encrypt']);
        foreach ($fs as $f) {
            if (!isset($data[$f])) {
                continue;
            }
            $data['salt'] = $salt;
            $data[$f] = encrypt($data[$f], $salt);
        }
    }
    if (isset($meta['encrypt2way'])) {
        load_library('util');
        load_library('encrypt');
        $salt = generate_salt();
        $fs = explode(',', $meta['encrypt2way']);
        foreach ($fs as $f) {
            if (!isset($data[$f])) {
                continue;
            }
            $data['salt'] = $salt;
            $data[$f] = encrypt_2way($data[$f], $salt);
        }
    }
    return $data;
}

/***
 * the honeypot anti-spam field, if it is there, should be empty.
 * only bots are tricked to fill it in.
 */
function api_honeypot_check(array &$data): bool {
    load_library('honeypot-field');
    $field = honeypot_field_name();
    if (!isset($data[$field])) {
        return false;
    }
    if (!empty($data[$field])) {
        load_library("log");
        log_system("honeypot field was filled");
        return true;
    }
    unset($data[$field]);
    return false;
}

/***
 * csrf check: if form_key is set, it should match the one in cookie or session.
 */
function api_check_csrf(&$data) {
    if (api_honeypot_check($data)) { 
        return false;
    }
    if (!isset($data['form_key'])) {
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

/***
 * Default implementations on resource get, post, put, delete:
 */

function resource_get($resource) { // get all
    $export = filter_input(INPUT_GET, 'export', FILTER_SANITIZE_SPECIAL_CHARS);
    if (!empty($export)) {
        load_library('api_export_resource');
        return api_export_resource($resource, $export);
    }
    $modified = data_modified($resource);
    http_header_not_modified($modified);
    $result = data_read($resource);

    $search = trim((string)filter_input(INPUT_GET, 'search', FILTER_SANITIZE_SPECIAL_CHARS));
    if ($search !== '') {
        load_library('resource-title');
        $title_field = resource_title_field($resource);
        $result = $title_field !== null
            ? array_filter($result, fn($record) => stripos((string)($record[$title_field] ?? ''), $search) !== false)
            : data_search($result, $search);
    }

    $limit = (int)filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT);
    if ($limit > 0 && count($result) > $limit) {
        $result = array_slice($result, 0, $limit, true);
    }

    return json_result([$resource => $result, 'count' => count($result)], 200, $modified);
}

function resource_post($resource) { // create new
    if (!empty($_FILES)) {
        load_library('api_import_resource');
        return api_import_resource($resource);
    }
    $data = api_json_input($resource);
    $csrf_check = api_check_csrf($data);
    if ($csrf_check === false) { //can also be null, if no key is set
        return json_result(array('message' => 'INVALID_DATA'), 400);   
    }
    $uuid = $data['uuid'];
    if (data_exists($resource, $uuid)) {
        return json_result(array('message' => 'RESOURCE_EXISTS'), 409);
    }
    if (data_create($resource, $uuid, $data)) {
        return json_result(array(
            $resource => array($uuid => $data),
            'count' => 1,
            'message' => 'RESOURCE_CREATED'),
        201);
    }
    if (data_error_get() === 'RESOURCE_EXISTS') {
        return json_result(array('message' => 'RESOURCE_EXISTS'), 409);
    }
    load_library("log");
    $error = data_error_get();
    if ($error === 'VALIDATION_FAILED') {
        log_system("resource create failed for " . $resource . ": validation failed");
    } else {
        log_system("resource create failed for " . $resource . ": write failed");
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
        ), 200);
    }
    if (data_error_get() === 'RESOURCE_EXISTS') {
        return json_result(array('message' => 'RESOURCE_EXISTS'), 409);
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

/***
 * Default implementations on resource item get, post, put, delete:
 */

function resource_id_get($resource, $uuid) { // read one
    $modified = data_modified($resource, $uuid);
    http_header_not_modified($modified);
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
    if (data_error_get() === 'RESOURCE_EXISTS') {
        return json_result(array('message' => 'RESOURCE_EXISTS'), 409);
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
        ), 200);
    }
    if (data_error_get() === 'RESOURCE_EXISTS') {
        return json_result(array('message' => 'RESOURCE_EXISTS'), 409);
    }
    return json_result(array('message' => 'RESOURCE_UPDATE_FAILED'), 500);
}

function resource_id_delete($resource, $uuid) { // delete one
    if (!data_exists($resource, $uuid)) {
        return json_result(array('message' => 'RESOURCE_NOT FOUND'), 404);
    } 
    if (data_delete($resource, $uuid)) {
        return json_result(array('message' => 'RESOURCE_DELETED', 'count' => 1), 200);
    }
    return json_result(array('message' => 'RESOURCE_DELETE_FAILED'), 500);
}
