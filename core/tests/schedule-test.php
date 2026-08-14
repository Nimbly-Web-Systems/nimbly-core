<?php

define('BASE_DIR', dirname(__DIR__, 2) . '/');

function schedule_test_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$source = file_get_contents(BASE_DIR . 'core/cli/schedule.php');
$functions = substr($source, strpos($source, 'function schedule_task_due'));
$functions = substr($functions, 0, strpos($functions, 'function schedule_run_command'));
eval($functions);

$minute = 60;
schedule_test_assert(schedule_task_due(['every_minutes' => 2], [], $minute), 'new two-minute task is due');
schedule_test_assert(!schedule_task_due(['every_minutes' => 2], ['last_run_at' => $minute], 119), 'task stays done in its interval');
schedule_test_assert(schedule_task_due(['every_minutes' => 2], ['last_run_at' => 119], 120), 'task is due in the next interval');
schedule_test_assert(!schedule_task_due(['every_minutes' => 0], [], 120), 'invalid interval is never due');
schedule_test_assert(!schedule_task_due(['every' => 'minute'], ['last_run_at' => 60], 119), 'minute schedule remains compatible');
schedule_test_assert(schedule_task_due(['every' => 'hour'], ['last_run_at' => 3599], 3600), 'hour schedule remains compatible');
schedule_test_assert(schedule_task_due(['every' => 'day'], ['last_run_at' => 86399], 86400), 'day schedule remains compatible');
schedule_test_assert(schedule_task_due(['daily_at' => '08:00'], [], strtotime('2026-08-13 08:00 UTC')), 'daily-at remains compatible');

echo "schedule tests passed\n";
