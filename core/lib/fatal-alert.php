<?php

function fatal_alert_register()
{
    register_shutdown_function('fatal_alert_shutdown');
}

function fatal_alert_shutdown()
{
    $error = error_get_last();
    if (empty($error) || !fatal_alert_is_fatal((int)($error['type'] ?? 0))) {
        return;
    }

    fatal_alert_enqueue($error);
}

function fatal_alert_is_fatal($type)
{
    return in_array($type, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true);
}

function fatal_alert_enqueue($error, ?int $now = null)
{
    try {
        load_libraries(['data', 'job']);
        $type = (int)($error['type'] ?? 0);
        $message = (string)($error['message'] ?? '');
        $file = (string)($error['file'] ?? '');
        $line = (int)($error['line'] ?? 0);
        $signature = hash('sha256', $type . '|' . $message . '|' . $file . '|' . $line);
        $now ??= time();
        $uuid = 'fatal-incident-' . $signature;
        $lock = fatal_alert_lock();
        try {
            $state = data_read('.state', $uuid);
            if (!is_array($state) || (int)($state['last_at'] ?? 0) < $now - 2592000) {
                $state = [
                    'signature' => $signature, 'first_at' => $now, 'count' => 0,
                    'events' => [], 'overflow' => 0, 'notifications' => [],
                    'type' => $type, 'file' => substr(basename($file), 0, 120), 'line' => $line,
                ];
            }
            $events = array_values(array_filter((array)$state['events'],
                fn($event) => (int)$event >= $now - 2592000));
            $events[] = $now;
            if (count($events) > 10000) {
                $state['overflow'] = (int)$state['overflow'] + count($events) - 10000;
                $events = array_slice($events, -10000);
            }
            $state['events'] = $events;
            $state['last_at'] = $now;
            $state['count']++;
            if (!data_create('.state', $uuid, $state)) {
                throw new RuntimeException('Fatal incident occurrence could not be saved');
            }
            $recent = count(array_filter($events, fn($event) => $event >= $now - 900));
            $stage = $state['count'] === 1 ? 'first'
                : ($recent >= 5 && empty($state['notifications']['escalation']['sent_at'])
                    ? 'escalation' : '');
            $first_job_uuid = (string)($state['notifications']['first']['job_uuid'] ?? '');
            $first_job = $first_job_uuid === '' ? null : data_read('.jobs', $first_job_uuid);
            if (empty($state['notifications']['first']['sent_at'])
                && is_array($first_job) && ($first_job['status'] ?? '') === 'failed') {
                $stage = 'first';
            }
            if ($stage === '' && (int)($state['last_sent_at'] ?? 0) > 0
                && $now - $state['last_sent_at'] >= 86400) {
                $stage = 'daily-' . gmdate('Y-m-d', $now);
            }
            if ($stage !== '') {
                $previous = (array)($state['notifications'][$stage] ?? []);
                $job = empty($previous['job_uuid']) ? null : data_read('.jobs', $previous['job_uuid']);
                if (empty($previous['sent_at']) && (!is_array($job)
                    || in_array(($job['status'] ?? ''), ['failed', 'done'], true))) {
                    $job_uuid = job_enqueue('fatal-error-alert', [
                        'signature' => $signature, 'stage' => $stage,
                        'count' => $state['count'], 'first_at' => $state['first_at'],
                        'last_at' => $now, 'type' => $type, 'message' => 'PHP fatal error',
                        'file' => $state['file'], 'line' => $line, 'url' => '',
                    ], ['max_attempts' => 10, 'omit_request_context' => true]);
                    if ($job_uuid === false) {
                        throw new RuntimeException('Fatal alert job could not be queued');
                    }
                    $state['notifications'][$stage] = ['job_uuid' => $job_uuid, 'queued_at' => $now];
                }
            }
            if ($stage !== '' && !data_create('.state', $uuid, $state)) {
                throw new RuntimeException('Fatal incident state could not be saved');
            }
        } finally {
            fatal_alert_unlock($lock);
        }
    } catch (Throwable $e) {
        error_log('Nimbly: fatal alert enqueue failed');
    }
}

function fatal_alert_lock()
{
    $handle = fopen(fatal_alert_base_dir() . 'ext/data/.state/fatal-alert.lock', 'c');
    if (!$handle || !flock($handle, LOCK_EX)) {
        throw new RuntimeException('Fatal incident lock is unavailable');
    }
    return $handle;
}

function fatal_alert_base_dir(): string
{
    return defined('BASE_DIR') ? BASE_DIR : (string)($GLOBALS['SYSTEM']['file_base'] ?? '');
}

function fatal_alert_unlock($handle): void
{
    flock($handle, LOCK_UN);
    fclose($handle);
}

function fatal_alert_mark_sent(string $signature, string $stage, string $job_uuid): void
{
    load_library('data');
    $lock = fatal_alert_lock();
    try {
        $uuid = 'fatal-incident-' . $signature;
        $state = data_read('.state', $uuid);
        if (!is_array($state) || ($state['notifications'][$stage]['job_uuid'] ?? '') !== $job_uuid) {
            throw new RuntimeException('Fatal incident state is unavailable');
        }
        $now = time();
        $state['notifications'][$stage]['sent_at'] = $now;
        $state['last_sent_at'] = $now;
        if (!data_create('.state', $uuid, $state)) {
            throw new RuntimeException('Fatal incident delivery state could not be saved');
        }
    } finally {
        fatal_alert_unlock($lock);
    }
}

function fatal_alert_prune(?int $now = null): int
{
    $now ??= time();
    $pruned = 0;
    foreach (glob(fatal_alert_base_dir() . 'ext/data/.state/fatal-incident-*') ?: [] as $path) {
        if (!is_file($path)) {
            continue;
        }
        $state = json_decode((string)file_get_contents($path), true);
        if (is_array($state) && (int)($state['last_at'] ?? 0) < $now - 2592000 && unlink($path)) {
            $pruned++;
        }
    }
    return $pruned;
}
