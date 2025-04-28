<?php

load_library('lookup');
load_library('util');
function get_html_sc($params)
{
    $var = current($params);
    if (isset($GLOBALS['SYSTEM']['variables'][$var])) {
        $html = $GLOBALS['SYSTEM']['variables'][$var];
    } else {
        $resource_set = dot2rs(current($params));
        if (!$resource_set) {
            return;
        }
        $resource = $resource_set[0];
        $uuid = $resource_set[1];
        $field = $resource_set[2];

        if (!data_exists($resource)) {
            data_create_resource($resource, ['fields' => false]);
        }

        if (!data_exists($resource, $uuid)) {
            data_create($resource, $uuid, []);
            $default = get_param_value($params, 'default', '');
            echo $default;
            return;
        }
        $html = lookup_data($resource, $uuid, $field, get_param_value('default', ''));
    }

    if (is_array($html)) {
        load_library('detect-language');
        $language = detect_language_sc();
        if (isset($html[$language])) {
            $html = $html[$language];
        } else {
            $html = '';
        }
    }

    // remove any base_url like src="/(base-url)/img/(uuid)
    $html = preg_replace('/([" ,])\/[\w]{2,}(\/img\/[0-9a-z]{20,32}\/)/i', '$1$2', $html);

    $base_url = trim($GLOBALS['SYSTEM']['uri_base'], ' \\/');

    if (strlen($base_url) > 0) {
        // insert base_url in any src="/img/(uuid)" with base-url
        $html = preg_replace('/([", ])\/img\/([0-9a-z]{20,32}\/)/i', '$1/' . $base_url . '/img/$2', $html);
    }

    // replace legacy lazy loading images
    $legacy_img_sizes = get_param_value($params, 'legacy-img-sizes');
    if (!empty($legacy_img_sizes)) {
        load_library('get-img-html', 'images');
        $html = preg_replace_callback(
            '/<img src="data:image\/gif;base64,R0lGODl[^=]+==" data-img-uuid="([0-9a-f]+)"[^>]+>/i',
            function ($matches) use ($legacy_img_sizes) {
                return get_img_html_sc([
                    'uuid' => $matches[1],
                    'sizes' => $legacy_img_sizes,
                    'class' => 'w-full bg-neutral-100'
                ]);
            },
            $html
        );
    }

    if (get_single_param_value($params, 'plain', true) === true) {
        $result = strip_tags($html);
    } else {
        $result = strip_tags($html, "<h1><h2><h3><h4><h5><h6><b><strong><a><i><p><blockquote><ol><ul><li><br><img><iframe><figure><video><source>");
    }
    echo $result;
}
