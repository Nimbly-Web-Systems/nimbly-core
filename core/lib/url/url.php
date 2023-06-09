<?php

function url_sc($params=null) {

	if (empty($params)) {
		return $GLOBALS['SYSTEM']['uri_base'] . $GLOBALS['SYSTEM']['request_uri'];
	} else if (current($params) === "relative") {
		return $GLOBALS['SYSTEM']['request_uri'];
	} else {
		$url = current($params) ?? $GLOBALS['SYSTEM']['request_uri'];
		return url_absolute($url);
	}
}

function url_absolute($relative_url) {
	if ($relative_url === '(empty)') {
		$relative_url = '';
	}

    return sprintf("%s://%s%s%s%s", 
            empty($_SERVER['HTTPS'])? "http" : "https",
            $_SERVER['SERVER_NAME'],
            $_SERVER['SERVER_PORT'] !== '80' && $_SERVER['SERVER_PORT'] !== '443'? ":" . $_SERVER['SERVER_PORT'] : "",
            $GLOBALS['SYSTEM']['uri_base'],
            $relative_url);
}
