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

function fatal_alert_enqueue($error)
{
    try {
        load_libraries(['data', 'job', 'log']);

        $type = (int)($error['type'] ?? 0);
        $message = trim((string)($error['message'] ?? ''));
        $file = trim((string)($error['file'] ?? ''));
        $line = (int)($error['line'] ?? 0);
        $signature = md5($type . '|' . $message . '|' . $file . '|' . $line);
        $now = time();
        $state_uuid = 'fatal-error-alerts';
        $state = data_exists('.state', $state_uuid) ? data_read('.state', $state_uuid) : ['alerts' => []];
        if (!is_array($state)) {
            $state = ['alerts' => []];
        }

        $last_sent = (int)($state['alerts'][$signature]['last_sent'] ?? 0);
        if ($last_sent > 0 && $last_sent > $now - 86400) {
            return;
        }

        $state['alerts'][$signature] = [
            'last_sent' => $now,
            'type' => $type,
            'message' => $message,
            'file' => $file,
            'line' => $line,
        ];
        data_create('.state', $state_uuid, $state);

        $request_url = '';
        if (!empty($_SERVER['HTTP_HOST']) || !empty($_SERVER['REQUEST_URI'])) {
            $scheme = empty($_SERVER['HTTPS']) ? 'http' : 'https';
            $request_url = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '');
        }

        job_enqueue('fatal-error-alert', [
            'type' => $type,
            'message' => $message,
            'file' => $file,
            'line' => $line,
            'url' => $request_url,
            'signature' => $signature,
        ], [
            'uuid' => md5('fatal-error-alert-' . $signature . '-' . $now),
            'max_attempts' => 1,
        ]);
    } catch (Throwable $e) {
        error_log('Nimbly: fatal alert enqueue failed: ' . $e->getMessage());
    }
}
