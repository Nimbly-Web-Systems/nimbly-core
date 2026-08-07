<?php

$ok = cli_composer_require_ext('dompdf/dompdf', '^3.0');

if ($ok) {
    load_library('data');
    data_create('.state', 'module:pdf', ['installed_at' => time()]);
}

return $ok;
