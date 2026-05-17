<?php

load_library('get');

/**
 * Shortcode: [#first-img-uuid var=record.main_text#]
 * @doc Extracts the UUID of the first image embedded in an HTML variable.
 * @doc Accepts a variable name as the first positional param or via var=.
 * @doc Returns (empty) when no image is found.
 */
function first_img_uuid_sc($params)
{
    $var = get_param_value($params, 'var', current($params));
    $value = get_variable($var);

    preg_match('/<img .+ data-img-uuid="([0-9a-f]+)"[^>]+>/i', $value, $matches);
    if (!isset($matches[1])) {
        preg_match('/<img.+src=[\'"].*\/img\/([a-f0-9]+).+[\'"].*>/i', $value, $matches);
    }
    if (!isset($matches[1])) {
        return '(empty)';
    }

    return $matches[1];
}
