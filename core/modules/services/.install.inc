<?php

load_library("data");

$result = data_create_resource(".services", [
    "fields" => [
        "service" => [
            "type" => "text",
            "required" => true,
            "name" => "Service (phpmailer, mailgun or system)",
            "admin_col" => false
        ],
        "server" => [
            "type" => "text",
            "name" => "Server",
            "admin_col" => false
        ],
        "user" => [
            "type" => "text",
            "name" => "User account (server)",
            "admin_col" => false
        ],
        "pw" => [
            "type" => "password",
            "required" => true,
            "name" => "Password (server)",
            "admin_col" => false
        ],
        "tpl" => [
            "type" => "text",
            "name" => "Template name"
        ],
        "from" => [
            "type" => "email",
            "name" => "From Address",
            "admin_col" => false
        ],
        "from_name" => [
            "type" => "text",
            "name" => "From Name",
            "admin_col" => false
        ],
        "recipient" => [
            "type" => "email",
            "name" => "Recipient Email",
            "admin_col" => false
        ],
        "recipient_name" => [
            "type" => "text",
            "name" => "Recipient Name",
            "admin_col" => false
        ],
        "subject" => [
            "type" => "text",
            "name" => "Subject"
        ]

    ],
    "pk" => "tpl",
    "encrypt2way" => "pw",
    "uuid" => ".meta",
    "sort" => [
        "field" => "tpl",
        "flags" => "string",
        "order" => "asc"
    ]
]);

return $result;
