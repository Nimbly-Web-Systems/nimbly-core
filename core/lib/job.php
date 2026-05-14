<?php

function job_enqueue($type, $payload = [], $options = [])
{
    load_library('data');
    job_ensure_resource();

    $payload = job_payload_with_request_context($payload);

    $uuid = $options['uuid'] ?? md5(uniqid((string)$type, true));
    $job = [
        'type' => $type,
        'status' => 'queued',
        'payload' => $payload,
        'attempts' => 0,
        'max_attempts' => $options['max_attempts'] ?? 5,
        'available_at' => $options['available_at'] ?? time(),
        'last_error' => '',
    ];

    return data_create('.jobs', $uuid, $job) ? $uuid : false;
}

function job_payload_with_request_context($payload)
{
    if (!is_array($payload) || empty($_SERVER['SERVER_NAME'])) {
        return $payload;
    }

    $scheme = empty($_SERVER['HTTPS']) ? 'http' : 'https';
    $port = $_SERVER['SERVER_PORT'] ?? '';
    $port_part = ($port !== '' && $port !== '80' && $port !== '443') ? ':' . $port : '';
    $uri_base = $GLOBALS['SYSTEM']['uri_base'] ?? '/';
    $base_url = rtrim($scheme . '://' . $_SERVER['SERVER_NAME'] . $port_part . '/' . trim($uri_base, '/'), '/');
    $request_uri = $GLOBALS['SYSTEM']['request_uri'] ?? ($_SERVER['REQUEST_URI'] ?? '');

    if (!isset($payload['_base_url'])) {
        $payload['_base_url'] = $base_url;
    }
    if ($request_uri !== '' && !isset($payload['_request_url'])) {
        $payload['_request_url'] = $base_url . '/' . ltrim($request_uri, '/');
    }

    return $payload;
}

function job_ensure_resource()
{
    load_library('data');
    if (!data_exists('.jobs', '.meta')) {
        data_create_resource('.jobs', job_resource_meta());
    }
}

function job_resource_meta()
{
    return [
        'fields' => [
            'type' => [
                'name' => 'Type',
                'type' => 'text',
                'required' => true,
            ],
            'status' => [
                'name' => 'Status',
                'type' => 'text',
                'required' => true,
            ],
            'payload' => [
                'name' => 'Payload',
                'type' => 'text',
            ],
            'attempts' => [
                'name' => 'Attempts',
                'type' => 'number',
            ],
            'max_attempts' => [
                'name' => 'Max attempts',
                'type' => 'number',
                'admin_col' => false,
            ],
            'available_at' => [
                'name' => 'Available at',
                'type' => 'number',
                'admin_col' => false,
            ],
            'locked_at' => [
                'name' => 'Locked at',
                'type' => 'number',
                'admin_col' => false,
            ],
            'locked_by' => [
                'name' => 'Locked by',
                'type' => 'text',
                'admin_col' => false,
            ],
            'last_error' => [
                'name' => 'Last error',
                'type' => 'text',
            ],
            'completed_at' => [
                'name' => 'Completed at',
                'type' => 'number',
                'admin_col' => false,
            ],
            'execution_ms' => [
                'name' => 'Execution (ms)',
                'type' => 'number',
                'admin_col' => false,
            ],
        ],
        'index' => [
            'status',
            'type',
        ],
    ];
}

function job_run_queued($limit = 1)
{
    load_libraries(['data', 'env']);

    $jobs = data_read('.jobs');
    if (empty($jobs) || !is_array($jobs)) {
        return ['processed' => 0, 'done' => 0, 'failed' => 0];
    }

    $now = time();
    $processed = 0;
    $done = 0;
    $failed = 0;
    $delay_ms = max(0, (int)env('JOB_RUN_DELAY_MS', '250'));

    foreach ($jobs as $uuid => $job) {
        if ($processed >= $limit) {
            break;
        }
        if (($job['status'] ?? '') !== 'queued') {
            continue;
        }
        if ((int)($job['available_at'] ?? 0) > $now) {
            continue;
        }

        $processed++;
        $attempts = (int)($job['attempts'] ?? 0) + 1;
        data_update('.jobs', $uuid, [
            'status' => 'running',
            'attempts' => $attempts,
            'locked_at' => $now,
            'locked_by' => gethostname() ?: 'cli',
        ]);

        try {
            $current = data_read('.jobs', $uuid);
            $start = microtime(true);
            $result = job_dispatch($current);
            if ($result === false) {
                throw new Exception('Job handler returned false');
            }
            data_update('.jobs', $uuid, [
                'status'         => 'done',
                'completed_at'   => time(),
                'execution_ms'   => (int) round((microtime(true) - $start) * 1000),
                'last_error'     => '',
            ]);
            $done++;
        } catch (Throwable $e) {
            $max_attempts = (int)($job['max_attempts'] ?? 5);
            $retry = $attempts < $max_attempts;
            data_update('.jobs', $uuid, [
                'status' => $retry ? 'queued' : 'failed',
                'available_at' => $retry ? time() + min(3600, 60 * $attempts) : 0,
                'last_error' => $e->getMessage(),
            ]);
            $failed++;
        }

        if ($delay_ms > 0 && $processed < $limit) {
            usleep($delay_ms * 1000);
        }
    }

    return ['processed' => $processed, 'done' => $done, 'failed' => $failed];
}

function job_dispatch($job)
{
    $type = $job['type'] ?? '';
    if ($type === '') {
        throw new Exception('Job type is missing');
    }

    global $SYSTEM;
    foreach ($SYSTEM['env_paths'] as $env_path) {
        $base_path = $SYSTEM['file_base'] . $env_path . '/modules/';
        if (!file_exists($base_path)) {
            continue;
        }
        foreach (scandir($base_path) as $module) {
            if ($module[0] === '.') {
                continue;
            }
            $file = $base_path . $module . '/lib/' . $type . '.php';
            if (!file_exists($file)) {
                $file = $base_path . $module . '/lib/' . $type . '/' . $type . '.php';
            }
            if (!file_exists($file)) {
                continue;
            }
            require_once($file);
            $function_name = str_replace('-', '_', $type) . '_job';
            if (function_exists($function_name)) {
                return $function_name($job);
            }
        }
    }

    throw new Exception('No job handler found for ' . $type);
}
