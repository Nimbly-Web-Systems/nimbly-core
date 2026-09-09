<?php

// Core maintenance is always scheduled separately. Add application tasks here.
return [
    ['id' => 'ext-sync', 'command' => 'ext:sync', 'every' => 'hour'],
];
