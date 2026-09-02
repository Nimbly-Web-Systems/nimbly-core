<?php

function get_system_log_sc($params)
{
	load_library('text');
	$file = $GLOBALS['SYSTEM']['file_base'] . 'ext/data/.tmp/logs/system.log';
	if (!file_exists($file)) {
		touch($file);
		chmod($file, 0640);
	}

	$lines = system_log_tail($file);
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
	foreach ($result as $index => &$record) {
		$record['_index'] = $index;
		$record['category'] = system_log_category($record);
		$record['category_label'] = system_log_category_label($record['category']);
		$display = system_log_display($record['message'] ?? '');
		$record['summary'] = $display['summary'];
		$record['details'] = $display['details'];
	}
	unset($record);
	load_library('set');
	if ($last_fatal) {
		set_variable('last_fatal', current($result));
	}
	set_variable('system_log', $result);
}

function system_log_category_label($category)
{
	return match ($category) {
		'Spam caught' => t('Spam caught'),
		'Validation failure' => t('Validation failure'),
		'System event' => t('System event'),
		default => $category,
	};
}

function system_log_display($message)
{
	$message = trim((string)$message);
	$separator = ' | details=';
	$position = strpos($message, $separator);
	if ($position === false) {
		return ['summary' => $message, 'details' => ''];
	}
	$summary = trim(substr($message, 0, $position));
	$encoded = trim(substr($message, $position + strlen($separator)));
	$decoded = json_decode($encoded, true);
	$details = is_array($decoded)
		? json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
		: $encoded;
	return ['summary' => $summary, 'details' => $details];
}

function system_log_category($record)
{
	$type = trim((string)($record['type'] ?? ''));
	$message = strtolower((string)($record['message'] ?? ''));
	if (str_contains($message, 'spam caught:')) {
		return 'Spam caught';
	}
	if (str_contains($message, 'validation failed')) {
		return 'Validation failure';
	}
	if (in_array($type, ['PHP Warning', 'PHP Fatal error', 'PHP Parse error'], true)) {
		return $type;
	}
	return 'System event';
}

function system_log_tail($file, $max_bytes = 1048576)
{
	$size = filesize($file);
	if ($size === false || $size === 0) {
		return [];
	}

	$handle = fopen($file, 'rb');
	if ($handle === false) {
		return [];
	}

	$offset = max(0, $size - $max_bytes);
	fseek($handle, $offset);
	if ($offset > 0) {
		fgets($handle);
	}
	$contents = stream_get_contents($handle);
	fclose($handle);

	if ($contents === false || $contents === '') {
		return [];
	}
	return preg_split('/\r?\n/', rtrim($contents, "\r\n"));
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
