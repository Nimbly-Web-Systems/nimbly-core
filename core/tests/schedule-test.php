<?php

define('BASE_DIR', dirname(__DIR__, 2) . '/');

function schedule_test_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$source = file_get_contents(BASE_DIR . 'core/cli/schedule.php');
$functions = substr($source, strpos($source, 'function schedule_next_due'));
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
$hour = 1800000000 - 1800000000 % 3600;
schedule_test_assert(!schedule_task_due(['every_minutes' => 30], ['last_run_at' => $hour - 60], $hour, 7), 'a site with phase 7 does not run at :00');
schedule_test_assert(schedule_task_due(['every_minutes' => 30], ['last_run_at' => $hour - 60], $hour + 420, 7), 'it runs seven minutes in');
schedule_test_assert(schedule_task_due(['every' => 'hour'], ['last_run_at' => $hour + 60], $hour + 3600 + 420, 7)
    && !schedule_task_due(['every' => 'hour'], ['last_run_at' => $hour + 420], $hour + 3600, 7), 'hourly tasks share the phase');
schedule_test_assert(schedule_next_due([], [], $hour, 7) === $hour + 420 && schedule_next_due([], [], $hour, 8) === $hour + 480,
    'idle sites recheck on their own phase');
schedule_test_assert(schedule_command_parts('agent:enqueue infra-expert') === ['agent:enqueue', 'infra-expert', '--scheduled'],
    'scheduled agent commands receive an explicit scheduled trigger');
schedule_test_assert(schedule_command_parts('agent:enqueue infra-expert --operator=incident') === ['agent:enqueue', 'infra-expert', '--operator=incident'],
    'explicit agent triggers are preserved');

echo "schedule tests passed\n";
