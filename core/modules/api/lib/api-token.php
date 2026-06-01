<?php

function api_token_sc() {
	load_library('api');
    load_library('set');
    set_variable('key', $_SERVER['PEPPER']);
    set_variable('api.allow', 'api_post_api_token');
	api_method_switch('api_token');
}

function api_token_post() {
    load_library('set');
    load_library('get-user');
    $data = json_input(false);

    // 1. validate request
    if (empty($data['email']) || empty($data['password'])) {
        return json_result(['message' => 'INVALID_DATA'], 400);
    }

    // 2. lookup user 
    $email = trim((string)$data['email']);
    run_library('session');
    $user_data = find_user_by_email($email);
    if (empty($user_data) || empty($user_data['uuid'])) {
        return json_result(['message' => 'ACCESS_DENIED'], 403);
    }
    if (!isset($user_data['api']) || empty($user_data['api']['access'])) {
        return json_result(['message' => 'ACCESS_DENIED'], 403);
    }
    if (empty($user_data['salt']) || empty($user_data['password'])) {
        return json_result(['message' => 'ACCESS_DENIED'], 403);
    }

    // 3. authenticate user
    load_library('encrypt');
    $pw_user = encrypt($data['password'], $user_data['salt']);
    $pw_stored = $user_data['password'];
    if (hash_equals($pw_stored, $pw_user) !== true) { //hash_equals: time safe 
        return json_result(['message' => 'INVALID_CREDENTIALS'], 401);
    } 
    run_library('session');
    if (!_persist_user_roles($user_data['email'])) {
        return json_result(['message' => 'INVALID_CREDENTIALS'], 401);
    }

     // 4. create a token for this user, store in user session and return result
     $now = time();
     $expires = $now + 600; // 10 mins should be enough for any app use case (including wav upload)
     $created =  $user_data['api']['created'] ?? $now;
     if (!empty($user_data['api']['token']) 
        && ($user_data['api']['expires'] ?? 0) > $now) {
        $token = $user_data['api']['token'];
     } else {
        load_library('util');
        $token = generate_salt();
     }
     $_SESSION['token'] = $token;
     $_SESSION['token_created'] = $created;
     $_SESSION['token_expires'] = $expires;  
     data_update('users', $user_data['uuid'], ['api' => 
        ['access' => true, 'token' => $token, 'created' => $created, 'expires' => $expires]
    ]);   
     return json_result([
         'token' => $token,  
         'token_created' => $created,  
         'token_expires' => $expires,
         'count' => 1
     ]);
}

function api_token_get() {
    load_library('set');
    
    $hs = getallheaders();
    
    if (empty($hs["Authorization"])) {
        return json_result(['message' => 'ACCESS_DENIED'], 403);
    }

    list($type, $token) = explode(" ", $hs["Authorization"], 2);

    if (empty($token) || strcasecmp($type, 'Bearer') !== 0) {
        return json_result(['message' => 'ACCESS_DENIED'], 403);
    }


    // 2. lookup user by token
    
    load_library('data');
    $users = data_filter(data_read('users', null, ['email', 'api']), 'api:(exists)');
    $user = null;

    foreach ($users as $u) {
        if ($u['api']['access'] && $u['api']['token'] === $token) {
            $user = $u;
            break;
        }
    }

    if (empty($user) || empty($user['api']['expires']) || !is_numeric($user['api']['expires'])) {
        return json_result(['message' => 'ACCESS_DENIED'], 403);
    }

    if (time() > $user['api']['expires']) {
        return false;
    }
 
    $api_data = $user['api'];
    $api_data['expires'] = time() + 600; // renew for 10 mins
    data_update('users', $user['uuid'], ['api' => $api_data]);
          
     return json_result([
         'token' => $token,  
         'token_created' => $api_data['created'],
         'token_expires' => $api_data['expires'],
         'count' => 1
     ]);
}
