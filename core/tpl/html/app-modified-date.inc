<?php

/**
 * The day the site's assets were last built, e.g. 2026-10-13: the readable
 * companion of [#app-modified#].
 */
function app_modified_date_sc() {
	static $result = false;
	if ($result !== false) {
		return $result;
	}

	$base = $GLOBALS['SYSTEM']['file_base'];
	$version_file = $base . 'ext/static/app.version';
	$files = file_exists($version_file)
		? [$version_file]
		: [$base . 'ext/static/app.css', $base . 'ext/static/app.js'];

	$modified = 0;
	foreach ($files as $f) {
		if (file_exists($f)) {
			$modified = max($modified, (int)filemtime($f));
		}
	}

	$result = $modified > 0 ? date('Y-m-d', $modified) : '';
	return $result;
}
