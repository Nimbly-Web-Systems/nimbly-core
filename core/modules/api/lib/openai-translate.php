<?php

load_library('api');
load_libraries(['get', 'set', 'data', 'curl', 'env']);
load_library('openai-complete');

function openai_translate_sc()
{
    api_method_switch('openai_translate');
}

function openai_translate_post()
{
    $data = json_input(false);

    if (empty($data['resource'] || empty($data['uuid'] || empty($data['lang'])))) {
        return json_result(['message' => 'INVALID_DATA'], 400);
    }

    $api_key = env('OPENAI_API_KEY');
    if (empty($api_key)) {
        return json_result(['message' => 'SERVICE_UNAVAILABLE'], 503);
    }

    $meta = data_meta($data['resource']);
    if (empty($meta) || empty($meta['translations'])) {
        return json_result(['message' => 'NOT_IMPLEMENTED'], 501);
    }

    $record = data_read($data['resource'], $data['uuid']);
    if (empty($record)) {
        return json_result(['message' => 'RESOURCE_NOT_FOUND'], 404);
    }

    $translations = $meta['translations'];
    if (!in_array($data['lang'], $translations['languages'] ?? [])) {
        return json_result(['message' => 'LANGUAGE_NOT_SUPPORTED'], 403);
    }

    $translated_record = $record;
    unset($translated_record['uuid']);
    $translated_record['lang'] = $data['lang'];
    if (!isset($translated_record['translations'])) {
        $translated_record['translations'] = [];
    }
    $translated_record['translations'][$record['lang']] = $record['uuid'];
    

    foreach ($translations['rules'] as $field => $recipe) {
        if (is_scalar($recipe)) {
            $translated_record[$field] = $recipe;
        } else if (!empty(trim($record[$field])) && is_array($recipe)) {
            $messages = openai_get_system_instructions($recipe, $data['lang']);
            $messages[] = ["role" => "user", "content" => $record[$field]];
            $response = openai_get_completion($api_key, $messages);
            if ($response === false) {
                log_system('Translation fail');
                return json_result(['message' => 'OPENAI_FAIL'], 500);
            } 
            $translated_record[$field] = $response;
        }
        if (!empty($meta['fields'][$field]['slug'])) {
            load_library('util');
            $translated_record[$field . '_slug'] = make_slug($translated_record[$field]);
        }
    }
    $uuid = $record['translations'][$data['lang']] ?? null;
    if (empty($uuid)) {
        load_library('util');
        $uuid = generate_uuid();
    }

    // update orignal record
    if (!isset($record['translations'])) {
        $record['translations'] = [];
    }
    $record['translations'][$data['lang']] = $uuid;
    data_update($data['resource'], $record['uuid'], [
        'translations' => $record['translations']
    ]);

    $translated_record['translations'][$record['lang']] = $record['uuid'];

    if (data_create($data['resource'], $uuid, $translated_record)) {
        return json_result(
            [
                $data['resource'] => [$uuid => $translated_record],
                'count' => 1,
                'message' => 'RESOURCE_CREATED'
            ],
            201
        );
    }
    return json_result(['message' => 'RESOURCE_CREATE_FAILED'], 500);
}
