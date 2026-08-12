<?php

require_once dirname(__DIR__) . '/cli/agent_action_gateway.php';

function action_test_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$state_dir = sys_get_temp_dir() . '/nimbly-action-gateway-' . bin2hex(random_bytes(6));
mkdir($state_dir, 0700, true);
$environment = [
    'NIMBLY_AGENT_GATEWAY_KEY' => 'test-secret-key',
    'NIMBLY_AGENT_SERVER_ID' => 'nimbly1.stage',
    'NIMBLY_AGENT_AUTHORIZATION_DIR' => $state_dir,
];
$envelope = [
    'target' => 'nimbly1.stage',
    'command' => 'systemctl start apache2',
    'action_digest' => hash('sha256', 'action-1'),
    'expires_at' => time() + 300,
    'rollback' => 'systemctl stop apache2',
];
$envelope['signature'] = hash_hmac('sha256', action_gateway_canonical_json($envelope), $environment['NIMBLY_AGENT_GATEWAY_KEY']);
$result = action_gateway_run($envelope, $environment, function (array $command) {
    action_test_assert($command === ['/bin/sh', '-lc', 'systemctl start apache2'], 'gateway executes the exact signed command');
    return ['exit_code' => 0, 'stdout' => 'started', 'stderr' => ''];
});
action_test_assert($result['exit_code'] === 0 && $result['stdout'] === 'started', 'valid one-time envelope executes');

$replay_denied = false;
try {
    action_gateway_run($envelope, $environment, fn() => []);
} catch (RuntimeException) {
    $replay_denied = true;
}
action_test_assert($replay_denied, 'consumed envelope cannot be replayed');

$floor = $envelope;
$floor['action_digest'] = hash('sha256', 'action-2');
$floor['command'] = 'usermod -aG sudo nimbly-agent';
unset($floor['signature']);
$floor['signature'] = hash_hmac('sha256', action_gateway_canonical_json($floor), $environment['NIMBLY_AGENT_GATEWAY_KEY']);
$floor_denied = false;
try {
    action_gateway_run($floor, $environment, fn() => []);
} catch (RuntimeException) {
    $floor_denied = true;
}
action_test_assert($floor_denied, 'gateway independently enforces the human safety floor');

$wrong_target = $envelope;
$wrong_target['action_digest'] = hash('sha256', 'action-3');
$wrong_target['target'] = 'nimbly2.prod';
unset($wrong_target['signature']);
$wrong_target['signature'] = hash_hmac('sha256', action_gateway_canonical_json($wrong_target), $environment['NIMBLY_AGENT_GATEWAY_KEY']);
$target_denied = false;
try {
    action_gateway_run($wrong_target, $environment, fn() => []);
} catch (RuntimeException) {
    $target_denied = true;
}
action_test_assert($target_denied, 'authorization is bound to the configured host identity');

foreach (glob($state_dir . '/*') ?: [] as $file) {
    unlink($file);
}
rmdir($state_dir);

echo "Agent action gateway tests passed.\n";
