<?php

/** 
 *  @doc * `[#is-url news#]` stores 'active_if=true' if the current url starts with _news_
 *  @doc * `[#is-url news =#]` outputs 'active_if=true' if the current url is exactly _news_
 */

load_library('set');

function is_url_sc($params) {
    global $SYSTEM;
    $url_prefix = current($params);
    $url_current = $SYSTEM['request_uri'];
    if ($url_prefix === '(home)') {
        $result = empty($url_current) || trim($url_current) === '/';
    } else if (count($params) > 1 && next($params) === '=') {
        $result = $url_current === $url_prefix;
    } else {
        $result = !empty($url_prefix) && substr($url_current, 0, strlen($url_prefix)) === $url_prefix;
    }
    set_variable('is-url', $result);
}
