<?php

load_library('get-user');
load_library("json");

$user = get_user();
if (!empty($user)) {
	return json_result(['message' => 'You are logged in'], 400);
}