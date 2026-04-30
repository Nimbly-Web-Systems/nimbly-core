<?php

function job_enqueue($type, $payload = [], $options = [])
{
    load_library('data');
    job_ensure_resource();

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
        ],
        'index' => [
            'status',
            'type',
        ],
    ];
}

function job_run_queued($limit = 1)
{
    load_library('data');

    $jobs = data_read('.jobs');
    if (empty($jobs) || !is_array($jobs)) {
        return ['processed' => 0, 'done' => 0, 'failed' => 0];
    }

    $now = time();
    $processed = 0;
    $done = 0;
    $failed = 0;

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
            $result = job_dispatch($current);
            if ($result === false) {
                throw new Exception('Job handler returned false');
            }
            data_update('.jobs', $uuid, [
                'status' => 'done',
                'completed_at' => time(),
                'last_error' => '',
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
