<?php

/**
 * @doc `[date input format]` outputs input date using [php format](http://php.net/manual/en/datetime.formats.php)
 */
function date_sc($params) {
    $date = get_param_value($params, 'date', current($params));
    $fmt = get_param_value($params, 'fmt', count($params) > 1? end($params) : 'd-m-Y');
    return date($fmt, is_numeric($date)? $date : strtotime($date));
}
