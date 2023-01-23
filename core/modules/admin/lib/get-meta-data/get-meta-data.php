<?php

function get_meta_data_sc($params) {
    $resource = current($params);
    if (empty($resource)) {
        return;
    }
    $meta = data_meta($resource);
    set_variable("meta.fields", $meta['fields']);
    set_variables("meta.field.", $meta['fields']);
}

?>