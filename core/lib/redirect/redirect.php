<?php

/**
 * @doc `[redirect url]` redirects to url
 */
function redirect_sc($params) {
    $url = is_array($params)? current($params) : $params;
    if (empty($url)) {
        return;
    }
    if ($url === $GLOBALS['SYSTEM']['request_uri']) {
        return;
    }
    redirect($url);
}

function redirect($url, $status=303) {
    if (strpos($url, "http") !== 0) {
        load_library("url");
        $url = url_absolute($url);
    }
    header('Location:' . $url, true, $status);
    echo "redirecting to <a href=\"{$url}\">{$url}</a>"; 
    exit();
}
