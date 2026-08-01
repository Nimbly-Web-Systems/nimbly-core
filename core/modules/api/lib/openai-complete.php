<?php

load_library('api');
load_libraries(['get', 'set', 'data', 'curl', 'env']);

function openai_complete_sc()
{
    api_method_switch('openai_complete');
}

function openai_complete_post()
{
    $data = json_input(false);

    if (empty($data['resource']) || empty($data['uuid']) || empty($data['lang']) || empty($data['field'])) {
        return json_result(['message' => 'INVALID_DATA'], 400);
    }

    $api_key = env('OPENAI_API_KEY');
    if (empty($api_key)) {
        return json_result(['message' => 'SERVICE_UNAVAILABLE'], 503);
    }

    $meta = data_meta($data['resource']);
    $fn = $data['field'];
    if ($fn === '(all)' && empty($meta['ai_record_action']) && empty($meta['ai_translate_record'])) {
        return json_result(['message' => 'NOT_IMPLEMENTED'], 501);
    }
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


    if ($fn === '(all)') {
        $input_values = $data['values'] ?? [];
        if (!is_array($input_values)) {
            return json_result(['message' => 'INVALID_DATA'], 400);
        }
        $fields = [];
        foreach ($meta['fields'] ?? [] as $field => $definition) {
            if (empty($definition['i18n']) || empty($definition['ai_prompts'])) {
                continue;
            }
            $field_values = $input_values[$field] ?? ($record[$field] ?? []);
            if (!is_array($field_values)) {
                return json_result(['message' => 'INVALID_DATA'], 400);
            }
            $target_value = is_array($field_values) ? ($field_values[$data['lang']] ?? '') : '';
            if (is_string($target_value) && trim($target_value) !== '') {
                continue;
            }
            $source = openai_source_content($field_values, $meta['languages'], $data['lang']);
            if ($source === null) {
                continue;
            }
            $fields[$field] = [
                'instructions' => array_column(
                    openai_get_system_instructions($definition['ai_prompts'], $data['lang']),
                    'content'
                ),
                'source_language' => $source['language'],
                'source' => $source['content'],
            ];
        }
        if (empty($fields)) {
            return json_result(['completions' => []]);
        }
        $completions = openai_get_record_completion($api_key, $fields, $data['lang']);
        if ($completions === false) {
            return json_result(['message' => 'OPENAI_FAIL'], 500);
        }
        return json_result(['completions' => $completions]);
    }

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
        $response = openai_get_completion($api_key, $prompts);
        if ($response === false) {
            log_system('Translation fail');
            return json_result(['message' => 'OPENAI_FAIL'], 500);
        }
    }
    return json_result(['completion' => $response]);
}

function openai_source_content($values, array $languages, string $target_lang): ?array
{
    if (!is_array($values)) {
        return null;
    }
    foreach ($languages as $language) {
        if ($language !== $target_lang && !empty($values[$language])) {
            return ['language' => $language, 'content' => $values[$language]];
        }
    }
    return null;
}

function openai_get_record_completion(string $api_key, array $fields, string $target_lang)
{
    $response = curl_post("https://api.openai.com/v1/chat/completions", [
        "Content-Type: application/json",
        "Authorization: Bearer " . $api_key
    ], json_encode([
        "model" => "gpt-4o-mini",
        "response_format" => ["type" => "json_object"],
        "messages" => [
            [
                "role" => "system",
                "content" => "Translate every supplied field to language code {$target_lang}. "
                    . "Follow each field's own instructions independently. Return one JSON object "
                    . "with exactly the supplied field keys and string values. Preserve HTML where requested."
            ],
            [
                "role" => "user",
                "content" => json_encode(['fields' => $fields], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ],
        ],
    ]));

    $content = $response['choices'][0]['message']['content'] ?? null;
    if (!is_string($content)) {
        return false;
    }
    $result = json_decode($content, true);
    if (!is_array($result)) {
        return false;
    }

    $completions = [];
    foreach ($fields as $field => $_definition) {
        if (isset($result[$field]) && is_string($result[$field])) {
            $completions[$field] = $result[$field];
        }
    }
    return $completions;
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
