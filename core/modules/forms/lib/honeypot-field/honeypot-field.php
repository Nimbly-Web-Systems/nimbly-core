<?php

function honeypot_field_sc($params) {
    load_module('admin');
    load_library('lookup');
    set_variable('honeypot.field', lookup_data('.config', 'site', 'honeypot_field', 'company_adress__2'));
    run_single_sc('honeypot-input');
}

