<?php

function memory_usage_sc() {
	echo memory_usage();
}

function memory_usage() {
	return round(memory_get_usage()/1024) . 'kb';
}