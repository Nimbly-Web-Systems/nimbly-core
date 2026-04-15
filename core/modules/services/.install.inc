<?php

load_library('data');

$services_meta = [
    'fields' => [
        'service' => [
            'type' => 'text',
            'required' => true,
            'name' => 'Service (phpmailer, mailgun or system)',
            'admin_col' => false,
        ],
        'server' => [
            'type' => 'text',
            'name' => 'Server',
            'admin_col' => false,
        ],
        'user' => [
            'type' => 'text',
            'name' => 'User account (server)',
            'admin_col' => false,
        ],
        'pw' => [
            'type' => 'password',
            'required' => true,
            'name' => 'Password (server)',
            'admin_col' => false,
        ],
        'tpl' => [
            'type' => 'text',
            'name' => 'Template name',
        ],
        'from' => [
            'type' => 'email',
            'name' => 'From Address',
            'admin_col' => false,
        ],
        'from_name' => [
            'type' => 'text',
            'name' => 'From Name',
            'admin_col' => false,
        ],
        'recipient' => [
            'type' => 'email',
            'name' => 'Recipient Email',
            'admin_col' => false,
        ],
        'recipient_name' => [
            'type' => 'text',
            'name' => 'Recipient Name',
            'admin_col' => false,
        ],
        'subject' => [
            'type' => 'text',
            'name' => 'Subject',
        ],
    ],
    'encrypt2way' => 'pw',
    'sort' => [
        'field' => 'tpl',
        'flags' => 'string',
        'order' => 'asc',
    ],
    'index' => ['tpl'],
];

if (!data_exists('.services', '.meta')) {
    return data_create_resource('.services', $services_meta);
}

$existing_meta = data_read('.services', '.meta');
if (empty($existing_meta) || !is_array($existing_meta)) {
    return false;
}

$existing_meta['fields'] = array_merge_recursive_distinct(
    $existing_meta['fields'] ?? [],
    $services_meta['fields']
);

$existing_meta['encrypt2way'] = $services_meta['encrypt2way'];
$existing_meta['sort'] = $services_meta['sort'];

$existing_meta['index'] = $existing_meta['index'] ?? [];
if (!is_array($existing_meta['index'])) {
    $existing_meta['index'] = [$existing_meta['index']];
}
if (!in_array('tpl', $existing_meta['index'], true)) {
    $existing_meta['index'][] = 'tpl';
}

unset($existing_meta['pk']);

return data_update('.services', '.meta', $existing_meta) !== false;
