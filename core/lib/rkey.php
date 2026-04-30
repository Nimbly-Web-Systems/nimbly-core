<?php

function rkey_sc($params) {
    return rkey(current($params));
}

function rkey($s) {
    if (is_string($s)) {
        return strtolower(trim($s));
    }
    return '_unknown_';
}