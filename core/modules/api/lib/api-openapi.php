<?php

load_library('api');
load_library('json');
load_library('data');
load_library('url');

/**
 * This site's API as an OpenAPI 3 document: the resources the caller may
 * read or write, with their fields, and the operations the caller may use.
 * A caller without a session or token gets 403, as for any other read.
 */
function api_openapi_sc($params)
{
    $paths = [];
    $schemas = [];
    foreach (array_keys(data_resources_list()) as $resource) {
        $operations = api_openapi_operations($resource);
        if ($operations === []) {
            continue;
        }
        $schemas[$resource] = api_openapi_record_schema($resource);
        $paths += api_openapi_resource_paths($resource, $operations);
    }
    if ($paths === []) {
        return json_result(['message' => 'ACCESS_DENIED'], 403);
    }
    $paths += api_openapi_file_paths();
    load_library('http-header');
    http_header_sent('json');
    header('Cache-Control: private, no-store');
    exit(json_encode(api_openapi_document($paths, $schemas), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
}

/** The HTTP methods the caller may use on a resource, as the API checks them. */
function api_openapi_operations(string $resource): array
{
    $operations = [];
    foreach (['get', 'post', 'put', 'patch', 'delete'] as $method) {
        if (api_access(_api_access_str($method, $resource, ''), $resource)) {
            $operations[] = $method;
        }
    }
    return $operations;
}

function api_openapi_document(array $paths, array $schemas): array
{
    load_library('get');
    load_library('data');
    $site = data_read('.config', 'site') ?: [];
    $name = $site['name'] ?? 'Nimbly site';
    $name = is_array($name) ? (string)reset($name) : (string)$name;
    $error = ['$ref' => '#/components/responses/Error'];
    $schemas['Answer'] = [
        'type' => 'object',
        'properties' => [
            'code' => ['type' => 'integer'],
            'success' => ['type' => 'boolean'],
            'status' => ['type' => 'string', 'enum' => ['ok', 'error']],
            'message' => ['type' => 'string'],
            'needs' => ['type' => 'string', 'description' => 'Permissions that would allow a refused request'],
            'count' => ['type' => 'integer'],
            'total' => ['type' => 'integer'],
        ],
    ];
    return [
        'openapi' => '3.0.3',
        'info' => [
            'title' => $name . ' API',
            'version' => (string)(api_openapi_core_commit() ?: '1'),
            'description' => 'The Nimbly REST API of this site, limited to what the signed-in user or token may use.',
        ],
        'servers' => [['url' => rtrim(url_absolute(''), '/')]],
        'security' => [['bearer' => []], ['session' => []]],
        'paths' => [
            '/api/v1/auth/token' => [
                'post' => [
                    'operationId' => 'issueToken',
                    'summary' => 'Sign in as a user with API access; the token is valid for 10 minutes',
                    'security' => [],
                    'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => [
                        'type' => 'object',
                        'required' => ['email', 'password'],
                        'properties' => ['email' => ['type' => 'string', 'format' => 'email'], 'password' => ['type' => 'string', 'format' => 'password']],
                    ]]]],
                    'responses' => ['200' => api_openapi_json('Token', ['type' => 'object', 'properties' => [
                        'token' => ['type' => 'string'], 'token_created' => ['type' => 'integer'], 'token_expires' => ['type' => 'integer'],
                    ]]), '401' => $error, '403' => $error],
                ],
                'get' => [
                    'operationId' => 'renewToken',
                    'summary' => 'Renew the token for another 10 minutes',
                    'security' => [['bearer' => []]],
                    'responses' => ['200' => ['description' => 'The renewed token'], '403' => $error],
                ],
            ],
        ] + $paths,
        'components' => [
            'securitySchemes' => [
                'bearer' => ['type' => 'http', 'scheme' => 'bearer'],
                'session' => ['type' => 'apiKey', 'in' => 'cookie', 'name' => session_name(), 'description' => 'The signed-in user\'s session, for scripts on the site itself'],
            ],
            'schemas' => $schemas,
            'responses' => ['Error' => api_openapi_json('Refused or failed; see message and needs', ['$ref' => '#/components/schemas/Answer'])],
        ],
    ];
}

function api_openapi_core_commit(): string
{
    $head = @file_get_contents($GLOBALS['SYSTEM']['file_base'] . '.git/HEAD');
    if ($head === false) {
        return '';
    }
    $head = trim($head);
    if (str_starts_with($head, 'ref: ')) {
        $head = trim((string)@file_get_contents($GLOBALS['SYSTEM']['file_base'] . '.git/' . substr($head, 5)));
    }
    return preg_match('/^[0-9a-f]{40}$/', $head) ? substr($head, 0, 8) : '';
}

function api_openapi_json(string $description, array $schema): array
{
    return ['description' => $description, 'content' => ['application/json' => ['schema' => $schema]]];
}

/** A resource's answer: its records keyed by UUID, next to the common fields. */
function api_openapi_list_schema(string $resource): array
{
    return ['allOf' => [
        ['$ref' => '#/components/schemas/Answer'],
        ['type' => 'object', 'properties' => [$resource => [
            'type' => 'object',
            'additionalProperties' => ['$ref' => '#/components/schemas/' . $resource],
        ]]],
    ]];
}

function api_openapi_resource_paths(string $resource, array $operations): array
{
    $error = ['$ref' => '#/components/responses/Error'];
    $record = ['$ref' => '#/components/schemas/' . $resource];
    $answer = api_openapi_json('The records, keyed by UUID', api_openapi_list_schema($resource));
    $body = ['required' => true, 'content' => ['application/json' => ['schema' => $record]]];
    $id = preg_replace('/[^a-zA-Z0-9]+/', '_', $resource);
    $uuid = ['name' => 'uuid', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']];
    $list = [];
    $item = ['parameters' => [$uuid]];
    if (in_array('get', $operations, true)) {
        $list['get'] = [
            'operationId' => 'list_' . $id,
            'summary' => 'List ' . $resource,
            'parameters' => [
                ['name' => 'filter', 'in' => 'query', 'schema' => ['type' => 'string'], 'example' => 'status:open'],
                ['name' => 'sort', 'in' => 'query', 'schema' => ['type' => 'string'], 'example' => '_modified|numeric|desc'],
                ['name' => 'search', 'in' => 'query', 'schema' => ['type' => 'string']],
                ['name' => 'limit', 'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 0]],
                ['name' => 'offset', 'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 0]],
            ],
            'responses' => ['200' => $answer, '304' => ['description' => 'Not modified'], '400' => $error, '403' => $error],
        ];
        $item['get'] = ['operationId' => 'read_' . $id, 'summary' => 'Read one record', 'responses' => ['200' => $answer, '304' => ['description' => 'Not modified'], '403' => $error, '404' => $error]];
    }
    if (in_array('post', $operations, true)) {
        $list['post'] = ['operationId' => 'create_' . $id, 'summary' => 'Create a record', 'requestBody' => $body, 'responses' => ['201' => $answer, '403' => $error, '409' => $error, '422' => $error]];
        $item['post'] = ['operationId' => 'create_' . $id . '_with_id', 'summary' => 'Create a record with this UUID', 'requestBody' => $body, 'responses' => ['201' => $answer, '403' => $error, '409' => $error, '422' => $error]];
    }
    if (in_array('put', $operations, true)) {
        $list['put'] = ['operationId' => 'update_' . $id . '_many', 'summary' => 'Update several records, keyed by UUID', 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'additionalProperties' => $record]]]], 'responses' => ['200' => $answer, '400' => $error, '403' => $error]];
        $item['put'] = ['operationId' => 'update_' . $id, 'summary' => 'Update the fields sent', 'requestBody' => $body, 'responses' => ['200' => $answer, '403' => $error, '422' => $error]];
    }
    if (in_array('patch', $operations, true)) {
        $list['patch'] = ['operationId' => 'patch_' . $id . '_many', 'summary' => 'Update several records, keyed by UUID (same as PUT)', 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'additionalProperties' => $record]]]], 'responses' => ['200' => $answer, '400' => $error, '403' => $error]];
        $item['patch'] = ['operationId' => 'patch_' . $id, 'summary' => 'Update the fields sent (same as PUT)', 'requestBody' => $body, 'responses' => ['200' => $answer, '403' => $error, '404' => $error, '422' => $error]];
    }
    if (in_array('delete', $operations, true)) {
        $item['delete'] = ['operationId' => 'delete_' . $id, 'summary' => 'Delete one record', 'responses' => ['200' => api_openapi_json('Deleted', ['$ref' => '#/components/schemas/Answer']), '403' => $error, '404' => $error]];
    }
    $paths = [];
    if ($list !== []) {
        $paths['/api/v1/' . $resource] = $list;
    }
    if (count($item) > 1) {
        $paths['/api/v1/' . $resource . '/{uuid}'] = $item;
    }
    return $paths;
}

function api_openapi_file_paths(): array
{
    if (!api_access(_api_access_str('post', '.files', ''), '.files')) {
        return [];
    }
    return ['/api/v1/.files' => ['post' => [
        'operationId' => 'uploadFile',
        'summary' => 'Upload a file to the media library; the UUID is its checksum',
        'requestBody' => ['required' => true, 'content' => ['multipart/form-data' => ['schema' => [
            'type' => 'object', 'required' => ['file'], 'properties' => ['file' => ['type' => 'string', 'format' => 'binary']],
        ]]]],
        'responses' => ['201' => ['description' => 'The file\'s metadata'], '403' => ['$ref' => '#/components/responses/Error'], '413' => ['$ref' => '#/components/responses/Error']],
    ]]];
}

/** The fields of a resource as JSON Schema, without the ones the API never returns. */
function api_openapi_record_schema(string $resource): array
{
    $meta = data_meta($resource);
    $hidden = api_hidden_fields($resource);
    $languages = is_array($meta['languages'] ?? null) ? $meta['languages'] : [];
    $properties = ['uuid' => ['type' => 'string', 'readOnly' => true]];
    $required = [];
    foreach (is_array($meta['fields'] ?? null) ? $meta['fields'] : [] as $key => $field) {
        if (!is_array($field) || in_array($key, $hidden, true) || in_array($field['type'] ?? '', ['group_start', 'group_end'], true)) {
            continue;
        }
        $schema = api_openapi_field_schema($field);
        if (!empty($field['name'])) {
            $schema['title'] = is_array($field['name']) ? (string)reset($field['name']) : (string)$field['name'];
        }
        if (!empty($field['i18n']) && $languages !== []) {
            $schema = ['type' => 'object', 'title' => $schema['title'] ?? $key, 'properties' => array_fill_keys($languages, array_diff_key($schema, ['title' => 1]))];
        }
        $properties[$key] = $schema;
        if (!empty($field['required'])) {
            $required[] = $key;
        }
    }
    foreach (['_created', '_modified'] as $key) {
        $properties[$key] = ['type' => 'integer', 'readOnly' => true, 'description' => 'Unix time'];
    }
    foreach (['_created_by', '_modified_by'] as $key) {
        $properties[$key] = ['type' => 'string', 'readOnly' => true];
    }
    $schema = ['type' => 'object', 'properties' => $properties];
    if ($required !== []) {
        $schema['required'] = $required;
    }
    return $schema;
}

function api_openapi_field_schema(array $field): array
{
    $type = (string)($field['type'] ?? 'text');
    switch ($type) {
        case 'boolean':
            return ['type' => 'boolean'];
        case 'number':
        case 'location-picker':
            $schema = ['type' => 'number'];
            foreach (['min' => 'minimum', 'max' => 'maximum'] as $from => $to) {
                if (isset($field[$from]) && is_numeric($field[$from])) {
                    $schema[$to] = $field[$from] + 0;
                }
            }
            return $schema;
        case 'date':
            return ['type' => 'string', 'format' => 'date'];
        case 'email':
            return ['type' => 'string', 'format' => 'email'];
        case 'url':
            return ['type' => 'string', 'format' => 'uri'];
        case 'html':
            return ['type' => 'string', 'description' => 'HTML'];
        case 'image':
        case 'file':
        case 'upload':
            return ['type' => 'string', 'description' => 'UUID of a file in the media library'];
        case 'gallery':
            return ['type' => 'string', 'description' => 'Comma-separated UUIDs of files in the media library'];
        case 'select':
            $schema = ['type' => 'string'];
            if (is_array($field['options'] ?? null) && $field['options'] !== []) {
                $schema['enum'] = array_map('strval', array_keys($field['options']));
            } elseif (!empty($field['resource'])) {
                $schema['description'] = 'UUID of a record in ' . $field['resource'];
            }
            if (!empty($field['multi'])) {
                $schema = ['type' => 'string', 'description' => 'Comma-separated values' . (isset($schema['enum']) ? ': ' . implode(', ', $schema['enum']) : '')];
            }
            return $schema;
        default:
            return ['type' => 'string'];
    }
}
