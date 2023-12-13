<?php

load_library('data');
load_library('base-url');

function get_img_html_sc($params)
{
    $uuid = current($params);
    set_variable('img-uuid', $uuid);
    $img_meta = data_read('.files_meta', $uuid);
    if (empty($img_meta)) {
        return;
    }
    $w = intval($img_meta['width']);
    set_variable('img-height', $img_meta['height']);
    set_variable('img-width', $img_meta['width']);
    $size_options = [];
    static $tw_breakpoints = [
        "sm" => 640,
        "md"  => 768,
        "lg"  => 1024,
        "xl"  => 1280,
        "xxl" => 1536
    ];
    static $sizes = [120, 180, 240, 320, 480, 640, 800, 960, 1120, 1280, 1440, 1600, 1760, 1920];
    $img_url = base_url_sc() . '/img/';
    foreach ($sizes as $i => $size) {
        if ($i > 0 && $size > $w) {
            break;
        }
        $size_options[] = $img_url . $uuid . '/' . $size . 'w ' . $size . 'w';
    }
    set_variable('img-srcset', join(', ', $size_options));

    $resp_sizes = explode(',', (string)get_param_value($params, 'sizes', ''));
    $resp_size_list = ['100vw'];
    foreach ($resp_sizes as $resp_size) {
        $set = explode('-', $resp_size);
        if (count($set) !== 2) {
            continue;
        }
        $bp = strtolower(trim($set[0]));
        if (!isset($tw_breakpoints[$bp])) {
            continue;
        }
        $vw = strtolower(trim($set[1]));
        array_unshift($resp_size_list, sprintf('(min-width: %dpx) %svw', $tw_breakpoints[$bp], $vw));
    }

    set_variable('img-sizes', join(', ', $resp_size_list));
    return run_buffered(dirname(__FILE__) . '/image.tpl');
}
