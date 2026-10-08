<?php

load_library('bot-tripwire');
load_library('set');

/**
 * Shortcode: [#bot-tripwire-link#]
 * @doc The link to this site's bot tripwire, out of sight for people.
 */
function bot_tripwire_link_sc($params = null): string
{
    $path = bot_tripwire_path();
    if ($path === '') {
        return '';
    }
    set_variable('_bot_tripwire_path', $path);
    return run_buffered(dirname(__FILE__) . '/link.tpl');
}
