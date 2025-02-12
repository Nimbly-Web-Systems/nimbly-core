<?php

load_library('text');

function field_name_sc($params) {
    return t(ucfirst(trim(current($params), '. ')));
}
