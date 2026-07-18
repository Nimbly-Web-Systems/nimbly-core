<?php

// @doc * `[url-key]` returns the url key of the current uri, e.g. admin/resources/users -> admin_resources_users

function url_key_sc() {
	$shared_key = $GLOBALS['SYSTEM']['variables']['page-content-key'] ?? '';
	if ($shared_key !== '') {
		return preg_replace('/[^a-zA-Z0-9._-]/', '_', $shared_key);
	}
	if (empty($GLOBALS['SYSTEM']['uri_key'])) {
		return '_home';
	}
	return $GLOBALS['SYSTEM']['uri_key'];
}
