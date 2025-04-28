<?php

load_library('api', 'api');
load_libraries(['get', 'set', 'data', 'encrypt', 'curl']);

function openai_complete_sc()
{
    api_method_switch('openai_complete');
}

function openai_complete_post()
{
    $data = json_input(false);

    if (empty($data['resource'] || empty($data['uuid'] || empty($data['lang']) || empty($data['field'])))) {
        return json_result(['message' => 'INVALID_DATA'], 400);
    }

    $service = data_read('.services', md5('openai-api'));
    if (empty($service)) {
        return json_result(['message' => 'SERVICE_UNAVAILABLE'], 503);
    }

    $meta = data_meta($data['resource']);
    $fn = $data['field'];
    if ($fn !== '(all)' && !isset($meta['fields'][$fn]['ai_prompts'])) {
        return json_result(['message' => 'NOT_IMPLEMENTED'], 501);
    }

    $record = data_read($data['resource'], $data['uuid']);
    if (empty($record)) {
        return json_result(['message' => 'RESOURCE_NOT_FOUND'], 404);
    }

    if (!in_array($data['lang'], $meta['languages'] ?? [])) {
        return json_result(['message' => 'LANGUAGE_NOT_SUPPORTED'], 403);
    }

    $api_key = decrypt_2way($service['pw'], $service['salt']);

    $prompts = openai_get_system_instructions($meta['fields'][$fn]['ai_prompts'], $data['lang']);
    $src_content = '';
    foreach ($meta['languages'] as $lang) {
        if ($lang === $data['lang'] || empty($record[$fn][$lang])) {
            continue;
        }
        if (empty($src_content)) {
            $src_content = $record[$fn][$lang];
            $prompts[] = ["role" => "system", "content" => "the source content you get is in language (code) " . $lang];
        } else {
            $prompts[] = ["role" => "system", "content" => "the content in language (code) " . $lang . " is " . $record[$fn][$lang]];
        }
    }
    if (empty($src_content)) {
        $response = '';
    } else {
        $prompts[] = ["role" => "user", "content" => $src_content];
        log_system($prompts);
        $response = openai_get_completion($api_key, $prompts);
        if ($response === false) {
            log_system('Translation fail');
            return json_result(['message' => 'OPENAI_FAIL'], 500);
        }
    }
    return json_result(['completion' => $response]);
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

    log_system($messages);

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
