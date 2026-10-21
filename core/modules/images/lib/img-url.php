<?php

load_library('url');
load_library('get');

/**
 * Shortcode: [#img-url UUID-or-path-or-URL#]
 * @doc Returns an absolute URL for an image given a UUID, a relative path, or an existing absolute URL.
 * @doc A UUID (20–32 hex chars) is expanded to /img/UUID/1200w by default.
 * @doc Pass width= to override the image width (default 1200).
 * @doc Returns empty string for empty / (empty) input.
 */
function img_url_sc($params)
{
    $value = trim(current($params) ?? '');

    if ($value === '' || $value === '(empty)') {
        return '';
    }

    if (preg_match('/^https?:\/\//', $value)) {
        return $value;
    }

    if (preg_match('/^[0-9a-f]{20,32}$/i', $value)) {
        $width = get_param_value($params, 'width', '1200');
        $format = get_param_value($params, 'format', '');
        $query = in_array($format, ['jpg', 'png'], true) ? '?format=' . $format : '';
        return url_absolute('img/' . $value . '/' . $width . 'w' . $query);
    }

    return url_absolute(ltrim($value, '/'));
}
