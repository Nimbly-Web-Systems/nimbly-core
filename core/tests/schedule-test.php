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
schedule_test_assert(schedule_command_parts('agent:enqueue infra-expert') === ['agent:enqueue', 'infra-expert', '--scheduled'],
    'scheduled agent commands receive an explicit scheduled trigger');
schedule_test_assert(schedule_command_parts('agent:enqueue infra-expert --operator=incident') === ['agent:enqueue', 'infra-expert', '--operator=incident'],
    'explicit agent triggers are preserved');

echo "schedule tests passed\n";
