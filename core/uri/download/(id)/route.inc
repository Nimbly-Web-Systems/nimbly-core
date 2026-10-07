<?php

/*
 * Accept route if it follows the pattern /download/(uuid)
 */

router_deny();
$parts = router_match(__FILE__);
if ($parts === false || count($parts) !== 1) {
    return;
}

$uuid = $parts[0];
load_library("data");
if (!data_exists(".files", $uuid)) {
	http_response_code(404);
    exit();
}

$file_meta = data_read('.files_meta', $uuid);
$file_path = $GLOBALS['SYSTEM']['file_base'] . 'ext/data/.files/' . $uuid;


// Revalidate against the stored file, rather than the current request time.
load_library('download-headers');
$modified = filemtime($file_path);
header('Cache-Control: private, no-cache');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $modified) . ' GMT');
if (download_is_not_modified($modified, $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '')) {
    http_response_code(304);
    exit();
}

header('Content-Length: '. filesize($file_path));
foreach (download_headers($file_meta['type'] ?? '', $file_meta['name'] ?? '') as $download_header) {
    header($download_header);
}
readfile($file_path);
exit();