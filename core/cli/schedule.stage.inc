<?php

return [
    [
        'id' => 'jobs-run',
        'command' => 'jobs:run 10',
        'every' => 'minute',
    ],
    [
        'id' => 'jobs-prune',
        'command' => 'jobs:prune --days=30',
        'daily_at' => '03:00',
    ],
    [
        'id' => 'ext-sync',
        'command' => 'ext:sync',
        'every' => 'hour',
    ],
];
