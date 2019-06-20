<?php

function execution_time_sc() {
	echo execution_time();
}

function execution_time() {
	return sprintf("%01.3fs", microtime(true) - $GLOBALS['SYSTEM']['request_time']);
}