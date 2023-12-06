<?php 

load_library('url-key');

function cfield_sc($params) {
    return '.content.' . url_key_sc() . '.' . current($params);
}