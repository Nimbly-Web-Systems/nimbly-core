<?php

function honeypot_field_sc($params) {
    set_variable('honeypot.field_name', honeypot_field_name());
    run_single_sc('honeypot-input');
}

function honeypot_field_name() {
    load_library('data');
    return data_lookup('.config', 'site', 'honeypot_field', 'company_adress__2');
}

