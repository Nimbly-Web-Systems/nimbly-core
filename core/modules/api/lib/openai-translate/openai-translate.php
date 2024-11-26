<?php

load_library('api', 'api');
load_libraries(['get', 'set', 'data', 'encrypt', 'curl']);

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

    $service = data_read('.services', md5('openai-api'));
    if (empty($service)) {
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
    
    $api_key = decrypt_2way($service['pw'], $service['salt']);

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
            load_library('slug');
            $translated_record[$field . '_slug'] = slug_sc($translated_record[$field]);
        }
    }
    $pk_field = $meta['pk'];
    $pk_value = $translated_record[$pk_field];
    load_library('md5');
    $uuid = md5_uuid($pk_value);

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

function openai_get_system_instructions($recipe, $lang)
{
    $result = [];
    foreach ($recipe['_all'] ?? [] as $system_msg) {
        $result[] = ["role" => "system", "content" => $system_msg];
    }
    foreach ($recipe[$lang] ?? [] as $system_msg) {
        $result[] = ["role" => "system", "content" => $system_msg];
    }
    return $result;
}

function openai_get_completion($api_key, $messages)
{
    $result = false;
    $response = curl_post("https://api.openai.com/v1/chat/completions", [
        "Content-Type: application/json",
        "Authorization: Bearer " . $api_key
    ], json_encode([
        "model" => "gpt-4o-mini",
        "messages" => $messages
    ]));

    if (empty($response) || !isset($response['choices'][0]['message']['content'])) {
        return $result;
    }

    $choice0 = $response['choices'][0];
    $content = $choice0['message']['content'];
    $finish_reason = $choice0['finish_reason'] ?? 'unknown';

    if ($finish_reason === 'stop') {
        $result = $content;
    }

    return $result;
}
