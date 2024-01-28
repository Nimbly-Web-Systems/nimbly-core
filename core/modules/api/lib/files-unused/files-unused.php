<?php

load_library('set');

function files_unused_sc()
{
    load_library('api', 'api');
    api_method_switch('files_unused');
}

function files_unused_get()
{
    $ids_from_url = get_variable('_ids');
    if (!empty($ids_from_url)) {
        $file_ids = explode(',', $ids_from_url);
    } else {
        $file_ids = data_list('.files_meta');
    }
    $result = [];
    foreach ($file_ids as $id) {
        if (!file_in_use($id)) {
            $result[] = $id;
        }
    }
    return json_result(['.files_unused' => $result, 'count' => count($result)], 200);
}

function file_in_use($search, $dir = null)
{
    static $EXCLUDE = [
        '.tmp', '.files', '.files_meta', '.routes', '.i18n', '.changelog',
        '.log-entries', 'roles', 'static', '.tailwind', '.sass-cache', '.git', 'lib'
    ];
    $dir = $dir ?? $GLOBALS['SYSTEM']['file_base'] . 'ext/';
    $bdir = trim(strtolower(basename($dir)));
    if (in_array($bdir, $EXCLUDE)) {
        return false;
    }
    $files = glob($dir . '{,.}[!.,!..]*',GLOB_MARK|GLOB_BRACE);
    foreach ($files as $file) {
        if (is_dir($file)) {
            if (file_in_use($search, $file)) {
                return true;
            }
        } else if (strpos(file_get_contents($file), $search) !== false) {
            return true;
        }
    }
    return false;
}
