<?php

load_library('api');
load_library('text');
load_library('files-unused');

function files_usage_sc()
{
    api_method_switch('files_usage');
}

function files_usage_get()
{
    $result = get_files_usage();
    return json_result(['.files_usage' => $result], 200);
}

// Where each media file is referenced: one group per resource, one for
// inline site content and one for templates.
function get_files_usage()
{
    $base = $GLOBALS['SYSTEM']['file_base'] . 'ext/';
    $file_ids = array_fill_keys(data_list('.files_meta'), true);
    $groups = [];
    $used = [];

    $dirs = [];
    foreach (glob($base . 'data/{,.}[!.,!..]*', GLOB_ONLYDIR | GLOB_BRACE) ?: [] as $dir) {
        $resource = basename($dir);
        $content = in_array($resource, ['.content', '.config', '.navigation'], true);
        $dirs[] = [$content ? '(content)' : $resource, $dir . '/'];
    }
    foreach (['uri', 'tpl', 'modules'] as $root) {
        $dirs[] = ['(templates)', $base . $root . '/'];
    }

    foreach ($dirs as [$key, $dir]) {
        if (!is_dir($dir)) {
            continue;
        }
        $remaining = $file_ids;
        files_mark_used($remaining, $dir);
        foreach (array_keys(array_diff_key($file_ids, $remaining)) as $id) {
            $used[(string) $id][$key] = true;
            $groups[$key] = ($groups[$key] ?? 0) + 1;
        }
    }

    $group_list = [];
    foreach ($groups as $key => $count) {
        $group_list[] = ['key' => (string) $key, 'name' => files_usage_group_name((string) $key), 'count' => $count];
    }
    usort($group_list, fn($a, $b) => $b['count'] <=> $a['count']);

    return [
        'groups' => $group_list,
        'used' => (object) array_map(fn($keys) => array_map('strval', array_keys($keys)), $used),
    ];
}

function files_usage_group_name($key)
{
    if ($key === '(content)') {
        return t('Site content');
    }
    if ($key === '(templates)') {
        return t('Templates');
    }
    $meta = data_meta($key);
    $name = $meta['name']['plural'] ?? null;
    if (is_string($name) && $name !== '') {
        return t($name);
    }
    return ucfirst(str_replace(['-', '_'], ' ', trim($key, '. ')));
}
