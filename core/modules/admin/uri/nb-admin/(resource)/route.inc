<?php

router_deny();
$parts = router_match(__FILE__);
if ($parts === false || count($parts) !== 1) {
    return;
}

$resource = $parts[0];
load_library("data");
if (!data_exists($resource)) {
    return;
}

$items = data_read($resource);
if (empty($items)) {
    $items = null;
}

$meta = data_meta($resource, $items);
if (!empty($items) && isset($meta['sort'])) {
    $items = data_sort_meta($items, $meta['sort']);
}

if (empty($items) && isset($meta['subkey'])) {
    $items = data_read_subkeys($resource, $meta['subkey']);
    set_variable('subkey', $meta['subkey']);
}

load_library('detect-language');
$lang = detect_language_sc();

/* set select values */
foreach ($meta['fields'] as $k => $v) {
    if (empty($v['type']) || $v['type'] !== 'select') {
        continue;
    }
    if (isset($v['resource'])) {
        $options = data_read($v['resource']);
    }
    
    // todo: other sources for options
    if (empty($options)) {
        continue;
    }
    foreach ($items as $item_ix => $i) {
        if (empty($i[$k]) || $i[$k] === '(empty)') {
            continue;
        }
        $vs = !empty($v['multi'])
            ? (is_array($i[$k]) ? $i[$k] : explode(',', $i[$k]))
            : [$i[$k]];
        $new_v = [];
        foreach ($vs as $opt_id) {
            $_v = $options[$opt_id]['name'] ?? $options[$opt_id]['title'] ?? '(unknown)';
            if (is_array($_v) && isset($_v[$lang])) {
                $new_v[] = $_v[$lang];
            } else {
                $new_v[] = $_v;
            }
        }
        $items[$item_ix][$k] = implode(', ', $new_v);
    }
}


// store data and fields and render the page
load_library("set");
set_variable('_order', $meta['order'] ?? false);
set_variable("data.resource", $resource);
set_variable("data.records", $items);
set_variable('action_url', $meta['actions']['url'] ?? '');
$meta['fields'] = data_exclude($meta['fields'], 'admin_col', false);
unset($meta['fields']['_created_by']);
unset($meta['fields']['_created']);
unset($meta['fields']['_modified']);
set_variable("data.fields", $meta['fields']);
load_library("access", "user");
load_library("get-user-resources", "admin");
if (access_by_feature('manage-content') && get_user_resources_access($resource)) {
    $_SESSION['features']['manage-' . $resource] = true;
}

load_module("admin");
router_accept();
