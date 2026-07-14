<?php

/**
 * @doc `[date input format]` outputs input date using [php format](http://php.net/manual/en/datetime.formats.php)
 */
function date_sc($params) {
    $date = get_param_value($params, 'date', null);
    $fmt = get_param_value($params, 'fmt', null);

    // bareword params (e.g. an already-resolved date value, or a format
    // like "Y-m-d") come through as $key === $value; the previous
    // count($params)-based logic dropped the date value whenever fmt=
    // was also present, silently falling back to "now".
    $positional = [];
    foreach ($params as $key => $value) {
        if ($key === $value) {
            $positional[] = $value;
        }
    }

    if ($date === null && !empty($positional)) {
        $date = array_shift($positional);
    }
    if ($fmt === null) {
        $fmt = !empty($positional) ? array_pop($positional) : 'd-m-Y';
    }
    if ($date === null) {
        $date = time();
    }

    return date($fmt, is_numeric($date)? $date : strtotime($date));
}
