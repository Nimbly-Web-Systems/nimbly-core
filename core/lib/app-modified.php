<?php 

/**
 * Shortcode: [#app-modified#]
 * @doc Returns a numeric version string for cache busting static files like app.css and app.js.
 * @doc Reads ext/static/app.version if it exists (written during build).
 * @doc Falls back to file modification times of app.css and app.js if app.version is missing.
 * @doc Example usage: <link rel="stylesheet" href="/app.css?v=[#app-modified#]">
 * @doc              <script src="/app.js?v=[#app-modified#]"></script>
 */
function app_modified_sc() {
	static $result = false;
	if ($result !== false) {
		return $result;
	}

	$base = $GLOBALS['SYSTEM']['file_base'];
	$version_file = $base . 'ext/static/app.version';

	if (file_exists($version_file)) {
		$result = trim(file_get_contents($version_file));
	} else {
		$files = [
			$base . 'ext/static/app.css',
			$base . 'ext/static/app.js',
		];
		$result = 1;
		foreach ($files as $f) {
			if (file_exists($f)) {
				$t = filemtime($f);
				if ($t > $result) {
					$result = $t;
				}
			}
		}
	}

	return $result;
}
