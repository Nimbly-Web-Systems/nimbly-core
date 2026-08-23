<?php 

$parts = router_match(__FILE__);
if ($parts === false || count($parts) !== 2) {
    return;
}

load_libraries(['data', 'password-reset']);
$uuid = $parts[0];
if (!(data_exists("users", $uuid))) {
    return;
}

$user = data_read("users", $uuid);
$key = $parts[1];
if (!password_reset_token_matches($user, $key)) {
	return;
}

$GLOBALS['SYSTEM']['validated_request_route'] = [
    'route_pattern' => '/password-reset/(uuid)/(key)',
    'reason' => 'validated_reset_route_not_accepted',
];

load_library('get-user');
$loggedin_user = get_user();
if (!empty($loggedin_user['email'])) {
	return; //user already logged in
}

load_library('set');
set_variable('username', $user['name']);
set_variable('useruuid', $uuid);
set_variable('password_reset_token', $key);

router_accept();
