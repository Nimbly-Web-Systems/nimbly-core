<?php 

function cfield_sc($params) {
    return '.content.' . $GLOBALS['SYSTEM']['uri_key'] . '.' . current($params);
}