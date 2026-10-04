<?php

/**
 * Makes a request value safe to pass through the template parser: a value from
 * the URL, a cookie or a form may contain shortcode tags, and shortcode output
 * is parsed again. The opening and closing tags are replaced by HTML entities,
 * so the value still displays as typed.
 */
function request_input_escape($value) {
    if (is_array($value)) {
        return array_map('request_input_escape', $value);
    }
    if (!is_string($value)) {
        return $value;
    }
    return str_replace(['[#', '#]'], ['&#91;#', '#&#93;'], $value);
}
