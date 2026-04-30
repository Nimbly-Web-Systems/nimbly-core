<?php

function disk_space_thumbs_sc() {
    load_library('util');
    $bytes = dir_size($GLOBALS['SYSTEM']['file_base'] . 'ext/static/_thumb_');
	return intval($bytes);
}
