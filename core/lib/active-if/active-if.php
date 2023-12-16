<?php

/** 
 *  @doc * `[#active-if news#]` stores 'active_if=true' if the current url starts with _news_
 *  @doc * `[#active-if news =#]` outputs 'active_if=true' if the current url is exactly _news_
 */

load_library('set');

function active_if_sc($params) {
    global $SYSTEM;
    $url_prefix = current($params);
    $url_current = $SYSTEM['request_uri'];
    if (count($params) > 1 && next($params) === '=') {
        $active = $url_current === $url_prefix;
    } else {
        $active = !empty($url_prefix) && substr($url_current, 0, strlen($url_prefix)) === $url_prefix;
    }
    set_variable('active-if', $active, true);
}
