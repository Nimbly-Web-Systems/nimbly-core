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

/**
 * The values of a record that is about to be stored, with shortcode tags kept
 * as text. A stored value is parsed as template text when it is printed inside
 * another shortcode, as in [#set page-title="[#get record.title#]"#]. One-way
 * encrypted fields stay as typed: login hashes the password exactly as it was
 * entered.
 */
function request_input_escape_record($meta, $data) {
    if (!is_array($data)) {
        return $data;
    }
    $as_typed = isset($meta['encrypt']) ? explode(',', $meta['encrypt']) : [];
    foreach ($data as $key => $value) {
        if (in_array((string)$key, $as_typed, true)) {
            continue;
        }
        $data[$key] = is_array($value) ? request_input_escape_record($meta, $value) : request_input_escape($value);
    }
    return $data;
}

/**
 * A request value printed inside a pending shortcode call, as [#get q from=url#]
 * in [#foo x=[#get q from=url#]#], must stay one parameter value. Whitespace and
 * `=` are swapped for markers that run_single_sc() turns back once it has split
 * the call into parameters. Outside a pending call the value is returned as is.
 */
function request_input_mark($value) {
    if (!is_string($value) || empty($GLOBALS['SYSTEM']['sc_pending'])) {
        return $value;
    }
    return strtr(str_replace(NB_MARK_CHAR, '', $value), NB_MARKS);
}
