<?php

function job_enqueue($type, $payload = [], $options = [])
{
    load_library('data');
    job_ensure_resource();

    if (empty($options['omit_request_context'])) {
        $payload = job_payload_with_request_context($payload);
    }

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

    if (!data_create('.jobs', $uuid, $job)) {
        return false;
    }
    job_mark_due((int)$job['available_at']);
    // the answer to the request names them, so the page can run them right away
    $GLOBALS['SYSTEM']['queued_jobs'][$uuid] = $job;
    return $uuid;
}

/**
 * The jobs this request queued, for the page that made it: it runs them at
 * once through api/v1/.jobs/(id)/run, the scheduler runs any it does not.
 */
function job_queued_summary(): array
{
    $result = [];
    foreach ($GLOBALS['SYSTEM']['queued_jobs'] ?? [] as $uuid => $job) {
        $payload = is_array($job['payload'] ?? null) ? $job['payload'] : [];
        $result[] = [
            'id' => (string)$uuid,
            'type' => (string)$job['type'],
            'resource' => (string)($payload['resource'] ?? ''),
            'uuid' => (string)($payload['uuid'] ?? ''),
            'fields' => job_fields($job),
        ];
    }
    return $result;
}

/** Earliest time a queued job is due; the host scheduler starts jobs:run only from then on. */
function job_due_path(): string
{
    $base_dir = defined('BASE_DIR') ? BASE_DIR : (string)($GLOBALS['SYSTEM']['file_base'] ?? '');
    return $base_dir . 'ext/data/.state/jobs-due';
}

function job_mark_due(int $at): void
{
    $path = job_due_path();
    if (!is_dir(dirname($path))) {
        @mkdir(dirname($path), 0775, true);
    }
    $handle = @fopen($path, 'c+');
    if (!$handle) {
        return;
    }
    flock($handle, LOCK_EX);
    $current = trim((string)stream_get_contents($handle));
    if ($current === '' || $at < (int)$current) {
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, (string)$at);
    }
    flock($handle, LOCK_UN);
    fclose($handle);
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
    // who may run it right away, see job_may_run()
    if (!isset($payload['_user']) && !empty($_SESSION['user_uuid'])) {
        $payload['_user'] = (string)$_SESSION['user_uuid'];
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
        return;
    }

    $meta = data_read('.jobs', '.meta');
    if (!is_array($meta)) {
        return;
    }
    $changed = false;

    if (empty($meta['keep_shortcodes'])) {
        $meta['keep_shortcodes'] = true;
        $changed = true;
    }

    if (($meta['fields']['payload']['admin_col'] ?? null) !== false) {
        $meta['fields']['payload']['admin_col'] = false;
        $changed = true;
    }

    if (($meta['fields']['last_error']['admin_col'] ?? null) !== false) {
        $meta['fields']['last_error']['admin_col'] = false;
        $changed = true;
    }

    if (!isset($meta['fields']['execution_ms'])) {
        $meta['fields']['execution_ms'] = [
            'name' => 'Execution (ms)',
            'type' => 'number',
            'admin_col' => false,
        ];
        $changed = true;
    }

    if (($meta['sort']['field'] ?? '') !== '_modified'
        || ($meta['sort']['flags'] ?? '') !== 'numeric'
        || ($meta['sort']['order'] ?? '') !== 'desc'
    ) {
        $meta['sort'] = [
            'field' => '_modified',
            'flags' => 'numeric',
            'order' => 'desc',
        ];
        $changed = true;
    }

    if (!$changed) {
        return;
    }

    file_put_contents(data_path('.jobs', '.meta'), json_encode($meta, JSON_UNESCAPED_UNICODE));
}

function job_resource_meta()
{
    return [
        // A payload is handed to its handler exactly as it was queued.
        'keep_shortcodes' => true,
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
                'admin_col' => false,
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
                'admin_col' => false,
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
        'sort' => [
            'field' => '_modified',
            'flags' => 'numeric',
            'order' => 'desc',
        ],
    ];
}

function job_run_queued($limit = 1)
{
    $base_dir = defined('BASE_DIR') ? BASE_DIR : (string)($GLOBALS['SYSTEM']['file_base'] ?? '');
    // In the site's own data, so containers that share it share the lock.
    $lock_dir = $base_dir . 'ext/data/.state';
    if (!is_dir($lock_dir)) {
        @mkdir($lock_dir, 0775, true);
    }
    $lock_path = $lock_dir . '/jobs.lock';
    $lock = @fopen($lock_path, 'c') ?: @fopen($lock_path, 'r');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
        if ($lock) {
            fclose($lock);
        }
        return ['processed' => 0, 'done' => 0, 'failed' => 0];
    }
    try {
        return job_run_queued_locked($limit);
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function job_run_queued_locked($limit = 1)
{
    load_libraries(['data', 'env']);

    // Cleared before reading, so a job queued during this run marks itself due again.
    @unlink(job_due_path());
    $jobs = data_read('.jobs');
    if (empty($jobs) || !is_array($jobs)) {
        return ['processed' => 0, 'done' => 0, 'failed' => 0];
    }

    $now = time();
    $processed = 0;
    $done = 0;
    $failed = 0;
    $delay_ms = max(0, (int)env('JOB_RUN_DELAY_MS', '250'));
    $next_due = null;

    foreach ($jobs as $uuid => $job) {
        if (($job['status'] ?? '') !== 'queued') {
            continue;
        }
        $available_at = (int)($job['available_at'] ?? 0);
        if ($processed >= $limit || $available_at > $now) {
            $next_due = min($next_due ?? $available_at, $available_at);
            continue;
        }

        $current = job_claim((string)$uuid, $now);
        if ($current === false) {
            continue;
        }
        $processed++;
        // a retry marks itself due, see job_execute()
        if (job_execute((string)$uuid, $current) === 0) {
            $done++;
        } else {
            $failed++;
        }

        if ($delay_ms > 0 && $processed < $limit) {
            usleep($delay_ms * 1000);
        }
    }
    if ($next_due !== null) {
        job_mark_due($next_due);
    }

    return ['processed' => $processed, 'done' => $done, 'failed' => $failed];
}

/**
 * Takes a queued job that is due for this process alone: the scheduler and a
 * page running it at once may try at the same moment. false when it is not
 * queued, not due yet or taken by the other.
 */
function job_claim(string $uuid, int $now)
{
    _data_lock('.jobs');
    try {
        $job = data_exists('.jobs', $uuid) ? data_read('.jobs', $uuid) : false;
        if (!is_array($job) || ($job['status'] ?? '') !== 'queued' || (int)($job['available_at'] ?? 0) > $now) {
            return false;
        }
        return data_update('.jobs', $uuid, [
            'status' => 'running',
            'attempts' => (int)($job['attempts'] ?? 0) + 1,
            'locked_at' => $now,
            'locked_by' => gethostname() ?: 'cli',
        ]);
    } finally {
        _data_unlock('.jobs');
    }
}

/** Runs a claimed job: 0 when it is done, the time of its retry, or -1 when it failed for good. */
function job_execute(string $uuid, array $job): int
{
    $attempts = (int)($job['attempts'] ?? 1);
    try {
        $start = microtime(true);
        if (function_exists('email_clear_last_result')) {
            email_clear_last_result();
        }
        $result = job_dispatch($job);
        if ($result === false) {
            $reason = 'Job handler returned false';
            if (function_exists('email_last_result')) {
                $email_result = email_last_result();
                if (!empty($email_result) && empty($email_result['success'])) {
                    $email_error = trim((string)($email_result['error'] ?? ''));
                    if ($email_error !== '') {
                        $reason .= ': email delivery: ' . substr($email_error, 0, 500);
                    }
                }
            }
            throw new Exception($reason);
        }
        data_update('.jobs', $uuid, [
            'status'         => 'done',
            'completed_at'   => time(),
            'execution_ms'   => (int) round((microtime(true) - $start) * 1000),
            'last_error'     => '',
        ]);
        return 0;
    } catch (Throwable $e) {
        $max_attempts = (int)($job['max_attempts'] ?? 5);
        $retry = $attempts < $max_attempts;
        $retry_at = time() + min(3600, 60 * $attempts);
        data_update('.jobs', $uuid, [
            'status' => $retry ? 'queued' : 'failed',
            'available_at' => $retry ? $retry_at : 0,
            'last_error' => $e->getMessage(),
        ]);
        if ($retry) {
            job_mark_due($retry_at);
            return $retry_at;
        }
        job_enqueue_failure_alert($uuid, $job, $attempts, $e->getMessage());
        return -1;
    }
}

/** Runs one job now, for the page whose request queued it; the job as it ended up. */
function job_run_one(string $uuid)
{
    load_libraries(['data', 'env']);
    $job = job_claim($uuid, time());
    if ($job !== false) {
        job_execute($uuid, $job);
    }
    return data_exists('.jobs', $uuid) ? data_read('.jobs', $uuid) : false;
}

/** The user whose request queued the job may run it at once, and whoever manages jobs. */
function job_may_run(array $job): bool
{
    load_library('access');
    load_library('session');
    $user = session_resume() ? (string)($_SESSION['user_uuid'] ?? '') : '';
    return ($user !== '' && ($job['payload']['_user'] ?? '') === $user) || access_by_feature('manage-.jobs');
}

/**
 * The record fields a job fills, so a page can show them as being made: a
 * handler names them with <type>_job_fields($payload).
 */
function job_fields(array $job): array
{
    $function_name = job_handler((string)($job['type'] ?? ''), '_job_fields');
    return $function_name !== null ? array_values((array)$function_name($job['payload'] ?? [])) : [];
}

function job_enqueue_failure_alert($uuid, $job, $attempts, $error)
{
    $type = trim((string)($job['type'] ?? ''));
    if ($type === '' || in_array($type, ['job-failure-alert', 'fatal-error-alert'], true)) {
        return false;
    }

    return job_enqueue('job-failure-alert', [
        'failed_uuid' => $uuid,
        'failed_type' => $type,
        'failed_attempts' => $attempts,
        'failed_error' => $error,
        'failed_payload' => $job['payload'] ?? [],
    ], [
        'uuid' => md5($uuid . '-job-failure-alert'),
        'max_attempts' => 1,
    ]);
}

function job_dispatch($job)
{
    $type = $job['type'] ?? '';
    if ($type === '') {
        throw new Exception('Job type is missing');
    }
    $function_name = job_handler($type, '_job');
    if ($function_name === null) {
        throw new Exception('No job handler found for ' . $type);
    }
    return $function_name($job);
}

/** The function of a job type with this suffix, from the module library named after the type. */
function job_handler(string $type, string $suffix): ?string
{
    if ($type === '') {
        return null;
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
            $function_name = str_replace('-', '_', $type) . $suffix;
            if (function_exists($function_name)) {
                return $function_name;
            }
        }
    }
    return null;
}
