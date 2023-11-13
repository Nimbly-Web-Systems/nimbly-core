<?php

// @doc * `[url-key]` returns the url key of the current uri, e.g. admin/resources/users -> admin_resources_users

function url_key_sc() {
	return $GLOBALS['SYSTEM']['uri_key'];
}
