<?php

load_library('data');

$role_id = get_variable('role-id');
if (empty($role_id) || !data_exists('roles', $role_id)) {
    $GLOBALS['SYSTEM']['validation_errors']['_global'][] = '[#text Role not found#]';
}
