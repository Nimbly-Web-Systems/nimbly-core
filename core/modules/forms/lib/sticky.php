<?php

/**
 * Shortcode: [#sticky fieldname#]
 * @doc Returns a form field value that persists across re-renders, useful for re-populating
 * @doc form fields after a failed submission.
 * @doc Resolution order:
 * @doc   1. POST data (what the user just submitted)
 * @doc   2. A variable named `sticky.<fieldname>` set via set_variable()
 * @doc   3. A GET parameter or session/system variable with the same name
 * @doc   4. The `default` parameter, if provided
 * @doc Example: <input name="email" value="[#sticky email#]">
 * @doc          <input name="email" value="[#sticky email default=you@example.com#]">
 */
function sticky_sc($params) {
    if (empty($params)) {
        return;
    }
    $default = get_param_value($params, "default", false);
    unset($params["default"]);
    $key = current($params);
    load_library("get");
    $value = filter_input(INPUT_POST, $key);
    if (empty($value)) {
        $value = get_variable("sticky." . $key);
    }
    if (empty($value)) {
        $value = get_sc($key);
    }
    if (!isset($value)) {
        $value = $default;
    }
    return $value;
}
