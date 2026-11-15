<?php

load_library("data");
load_library("data-sort");

function router_run($uri) {
    $GLOBALS['SYSTEM']['route_found'] = false;
    $GLOBALS['SYSTEM']['uri_parts'] = explode("/", $GLOBALS['SYSTEM']['request_uri']);
    $routes = data_read(".routes");
    $routes = is_array($routes) ? $routes : [];
    // Core's own dynamic routes work on every site without a .routes record.
    foreach (router_core_routes() as $route) {
        $routes[md5($route['route'])] ??= $route;
    }
    $routes = data_sort($routes, 'order', SORT_NUMERIC);
    if (empty($routes)) {
        return false;
    }
    load_library('subdomain');
    $subdomain = subdomain_folder();
    foreach ($routes as $r) {
        if (!subdomain_route_allowed((string)$r['route'], $subdomain)) {
            continue;
        }
        if (router_handle($r['route'])) {
            return true;
        }
    }
    return false;
}

/**
 * The dynamic routes core ships. Sites register only their own in .routes.
 */
function router_core_routes(): array {
    return [
        ['route' => 'api',                                 'order' => 900],
        ['route' => 'api/v1/(resource)',                   'order' => 500],
        ['route' => 'api/v1/(resource)/import',            'order' => 100],
        ['route' => 'api/v1/(resource)/export',            'order' => 100],
        ['route' => 'api/v1/(resource)/import-document',   'order' => 100],
        ['route' => 'api/v1/(resource)/options',           'order' => 100],
        ['route' => 'api/v1/(resource)/(id)',              'order' => 200],
        ['route' => 'api/v1/(resource)/(id)/(id)',         'order' => 200],
        ['route' => 'api/v1/.pages/(id)',                  'order' => 200],
        ['route' => 'api/v1/.files/(id)',                  'order' => 200],
        ['route' => 'api/v1/.jobs/(id)/run',               'order' => 100],
        ['route' => 'nb-admin/(resource)',                 'order' => 400],
        ['route' => 'nb-admin/(resource)/(id)',            'order' => 500],
        ['route' => 'nb-admin/(resource)/add',             'order' => 300],
        ['route' => 'nb-admin/(resource)/import',          'order' => 300],
        ['route' => 'nb-admin/(resource)/history',         'order' => 300],
        ['route' => 'nb-admin/(resource)/(id)/history',    'order' => 300],
        ['route' => 'nb-admin/roles/(id)',                 'order' => 200],
        ['route' => 'nb-admin/pages/(id)',                 'order' => 200],
        ['route' => 'nb-admin/files/(id)',                 'order' => 200],
        ['route' => 'img/(id)',                            'order' => 500],
        ['route' => 'download/(id)',                       'order' => 500],
        ['route' => 'video/(id)',                          'order' => 500],
        ['route' => 'password-reset/(uuid)/(key)',         'order' => 200],
        ['route' => 'change-email/(uuid)/(newuuid)/(key)', 'order' => 200],
    ];
}

function router_match($path) {
    $parts = $GLOBALS['SYSTEM']['uri_parts'];
    $path_parts = explode(DIRECTORY_SEPARATOR . "uri" . DIRECTORY_SEPARATOR, dirname($path));
    load_library('subdomain');
    $file_parts = subdomain_route_parts(explode(DIRECTORY_SEPARATOR, $path_parts[1]));
    if (count($file_parts) !== count($parts)) {
        router_deny();
        return false;
    }
    $result = array();
    for ($i = 0; $i < count($parts); $i++) {
        if ($file_parts[$i][0] === '(' || $parts[$i][0] === '(') {
            $result[] = $parts[$i];
        } else if ($parts[$i] !== $file_parts[$i]) {
            router_deny();
            return false;
        }
    }
    return $result;
}

function router_accept($accept = true) {
    $GLOBALS['SYSTEM']['route_found'] = $accept;
    return $accept;
}

function router_deny($accept = false) {
    $GLOBALS['SYSTEM']['route_found'] = $accept;
    return $accept;
}

function router_handle($ep) {
    $path = find_uri($ep, "route.inc");
    if ($path === false) {
        return false;
    }
    include_once($path);
    if ($GLOBALS['SYSTEM']['route_found'] !== true) {
        return false;
    }
    $file = find_uri($ep);
    if ($file !== false) {
        $GLOBALS['SYSTEM']['uri'] = $ep;
        $GLOBALS['SYSTEM']['uri_key'] = preg_replace("/[^ \w]+/", "_", $ep);
        $GLOBALS['SYSTEM']['uri_path'] = dirname($path);
        run($file);
        return true;
    }

    return false;
}
