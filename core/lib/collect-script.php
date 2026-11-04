<?php

$GLOBALS['_collected_scripts'] = [];

function collect_script_sc($params)
{
    $path = get_param_value($params, 'path', current($params));
    if (empty($path)) {
        render_collected_scripts();
    } else {
        collect_script($path);
    }
    
}

function collect_script($path)
{
    if (!in_array($path, $GLOBALS['_collected_scripts'])) {
        $GLOBALS['_collected_scripts'][] = $path;
    }
}

function render_collected_scripts() {
    $external = [];
    foreach ($GLOBALS['_collected_scripts'] as $path) {
        if (is_array($path) && isset($path['external'])) {
            $external[] = $path['external'];
        } else {
            echo run_buffered($path);
        }
    }
    if ($external) {
        $urls = json_encode($external, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        echo '<script>document.addEventListener("nb:ready",function(){'
            . 'const urls=' . $urls . ';let i=0;function next(){if(i===urls.length)return;'
            . 'const s=document.createElement("script");s.src=urls[i++];s.async=false;s.fetchPriority="low";'
            . 's.onload=next;s.onerror=next;document.body.append(s);}next();},{once:true});</script>';
    }
}

function collect_external_script(string $url): void
{
    load_library('embed');
    if (embed_url_allowed($url)) {
        $entry = ['external' => $url];
        if (!in_array($entry, $GLOBALS['_collected_scripts'], true)) {
            $GLOBALS['_collected_scripts'][] = $entry;
        }
    }
}
