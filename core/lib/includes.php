<?php

/**
 * @doc `[includes needle haystack]` returns true if needle exists in haystack, false otherwise
 */
function includes_sc($params) {
    if (count($params) !== 2) {
        return false;
    }
    $needle = get_param_value($params, 'needle', current($params));
    $haystack = get_param_value($params, 'haystack', end($params));
    $needle = get_variable($needle);
    $haystack = get_variable($haystack);
    if (!is_scalar($needle)) {
        return false;
    }
    if ($needle == $haystack) {
        return true;
    }
    if (is_array($haystack)) {
        return in_array($needle, $haystack);
    }
    if (is_object($haystack)) {
        return property_exists($haystack, $needle);
    }
    return false;
}