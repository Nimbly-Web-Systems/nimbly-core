<?php

function event_dispatch($event, $data)
{
    global $SYSTEM;
    foreach ($SYSTEM['env_paths'] as $env_path) {
        if ($env_path === 'core') {
            continue;
        }

        $base_path = $SYSTEM['file_base'] . $env_path . '/modules/';
        if (!file_exists($base_path)) {
            continue;
        }

        foreach (scandir($base_path) as $module) {
            if ($module[0] === '.') {
                continue;
            }

            $file = $base_path . $module . '/lib/' . $event . '.php';
            if (!file_exists($file)) {
                $file = $base_path . $module . '/lib/' . $event . '/' . $event . '.php';
            }
            if (!file_exists($file)) {
                continue;
            }

            require_once($file);
            $function_name = str_replace('-', '_', $event);
            if (!function_exists($function_name)) {
                continue;
            }

            $function_name($data);
        }
    }
}

function event_resource_lifecycle($action, $resource, $uuid, $data = null)
{
    $meta = data_meta($resource);
    if (empty($meta['events'][$action]) || !is_array($meta['events'][$action])) {
        return;
    }

    $payload = [
        'action' => $action,
        'resource' => $resource,
        'uuid' => $uuid,
    ];
    if ($data !== null) {
        $payload['data'] = $data;
    }

    foreach ($meta['events'][$action] as $event) {
        if (!is_string($event) || trim($event) === '') {
            continue;
        }

        $event = trim($event);
        if (strpos($event, 'job:') === 0) {
            load_library('job');
            job_enqueue(substr($event, 4), $payload);
            continue;
        }

        $payload['event'] = $event;
        event_dispatch($event, $payload);
    }
}
