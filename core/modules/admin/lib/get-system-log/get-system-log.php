<?php

function get_system_log_sc($params)
{
	$file = $GLOBALS['SYSTEM']['file_base'] . 'ext/data/.tmp/logs/system.log';
	if (!file_exists($file)) {
		touch($file);
		chmod($file, 0640);
	}

	$lines = file($file);
	$result = [];

	$last_fatal = current($params) === 'last-fatal';

	foreach ($lines as $line) {
		$parts = parse_log_entry($line);
		if (empty($parts) || $last_fatal && $parts['type'] !== 'PHP Fatal error') {
			continue;
		}
		array_unshift($result, $parts);
		if ($last_fatal) {
			break;
		}
	}
	load_library('set');
	if ($last_fatal) {
		set_variable('last_fatal', current($result));
	}
	set_variable('system_log', $result);
}

function parse_log_entry($line)
{
	$a = strpos($line, "]");
	if ($a < 1) {
		return false;
	}
	$result['time'] = strtotime(substr($line, 1, $a - 1));
	$b = strpos($line, ":", $a + 1);
	if ($b < $a) {
		return false;
	}
	$type = trim(substr($line, $a + 1, $b - $a - 1));
	$result['type'] = $type;
	$result['message'] = trim(substr($line, $b + 1));
	return $result;
}
