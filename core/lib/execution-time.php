<?php

/**
 * @doc `[execution-time]` returns the current request execution time in seconds.
 */
function execution_time_sc($params)
{
    $request_time = $GLOBALS['SYSTEM']['request_time'] ?? microtime(true);
    return microtime(true) - $request_time;
}
