<?php

function nimbly_wrapper_test_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$root = dirname(__DIR__, 2);
$empty_path = sys_get_temp_dir() . '/nimbly-wrapper-test-' . bin2hex(random_bytes(4));
mkdir($empty_path, 0755, true);
file_put_contents($empty_path . '/php', "#!/bin/sh\necho php-dispatch:\"\$*\"\nexit 23\n");
chmod($empty_path . '/php', 0755);

$php_command = sprintf(
    'cd %s && PATH=%s /bin/sh ./nimbly agent:enqueue 2>&1',
    escapeshellarg($root),
    escapeshellarg($empty_path)
);
exec($php_command, $php_output, $php_status);
nimbly_wrapper_test_assert($php_status === 23, 'PHP-native command reaches the PHP CLI without Node');
nimbly_wrapper_test_assert(
    implode("\n", $php_output) === 'php-dispatch:core/cli/nimbly.php agent:enqueue',
    'PHP-native command keeps its arguments'
);

$node_command = sprintf(
    'cd %s && PATH=%s /bin/sh ./nimbly build 2>&1',
    escapeshellarg($root),
    escapeshellarg($empty_path)
);
exec($node_command, $node_output, $node_status);
nimbly_wrapper_test_assert($node_status === 1, 'Node-native command fails without Node');
nimbly_wrapper_test_assert(
    str_contains(implode("\n", $node_output), 'requires Node.js 20+'),
    'Node-native command explains its Node requirement'
);

unlink($empty_path . '/php');
rmdir($empty_path);
echo "Nimbly wrapper tests passed.\n";
