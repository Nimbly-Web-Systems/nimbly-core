<?php

/**
 * @doc `[date input format]` outputs input date using [php format](http://php.net/manual/en/datetime.formats.php)
 */
function date_sc($params) {
	if (count($params) === 1 && !isset($params['fmt'])) {
		$default_date = current($params);
	} else {
		$default_date = time();
	}
    $date = get_param_value($params, 'date', $default_date);
    $fmt = get_param_value($params, 'fmt', count($params) > 1? end($params) : 'd-m-Y');
    return date($fmt, is_numeric($date)? $date : strtotime($date));
}
