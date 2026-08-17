<?php

load_library('api');
load_libraries(['data', 'curl', 'env', 'util', 'docx']);
load_library('openai-complete');

/**
 * POST /api/v1/{resource}/import-document — upload a .docx, extract field
 * values from it via AI, and report which of the resource's still-empty
 * fields could be filled, plus (only when the resource has configured
 * languages) the document's detected language. A generic add-form building
 * block, not article- or i18n-specific: every free-text field (text,
 * textarea, html) defined on the resource is eligible, whether or not it's
 * i18n, and whether or not it has ai_prompts configured — the field's own
 * name/type from .meta is enough on its own to build an instruction;
 * ai_prompts, when present, is folded in as extra guidance, not a
 * requirement. location-picker fields (plus their declared longitude_field
 * pair) get the same treatment via geocoding instead of extraction — no
 * per-project config needed there either, since the pairing is structural.
 * Companion to api_import_resource()'s bulk CSV/JSON import, but for a
 * single unsaved record being drafted on the add form: the upload is read
 * straight from PHP's own tmp path and never persisted to `.files`, same as
 * api_import_resource()'s pattern.
 */
function openai_import_document($resource)
{
    if (empty($_FILES)) {
        if (isset($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > max_upload_size()) {
            return json_result(['message' => 'UPLOAD_TOO_LARGE'], 413);
        }
        return json_result(['message' => 'INVALID_DATA'], 400);
    }

    $api_key = env('OPENAI_API_KEY');
    if (empty($api_key)) {
        return json_result(['message' => 'SERVICE_UNAVAILABLE'], 503);
    }

    $meta = data_meta($resource);
    if (empty($meta['fields']) || !is_array($meta['fields'])) {
        return json_result(['message' => 'RESOURCE_NOT_FOUND'], 404);
    }

    $file = reset($_FILES);
    if (!is_uploaded_file($file['tmp_name'] ?? '')) {
        return json_result(['message' => 'INVALID_DATA'], 400);
    }

    $tmp_path = $file['tmp_name'];
    $name = strtolower((string)($file['name'] ?? ''));
    $signature = file_get_contents($tmp_path, false, null, 0, 2);
    if (!str_ends_with($name, '.docx') || $signature !== 'PK') {
        return json_result(['message' => 'INVALID_DATA'], 400);
    }

    $text = docx_extract_text($tmp_path);
    if ($text === false || trim($text) === '') {
        return json_result(['message' => 'EMPTY_DOCUMENT'], 422);
    }

    $current_values = json_decode($_POST['current_values'] ?? '', true);
    if (!is_array($current_values)) {
        $current_values = [];
    }

    // Free-text field types only — extracting a plausible value for an
    // image, file, group, select, or coordinate-picker field from document
    // prose isn't reliable enough to attempt.
    $extractable_types = ['text', 'textarea', 'html'];

    $fields = [];
    foreach ($meta['fields'] as $field => $definition) {
        $type = $definition['type'] ?? 'text';
        if (!in_array($type, $extractable_types, true)) {
            continue;
        }
        if (!openai_translation_value_is_empty($current_values[$field] ?? '', $definition)) {
            continue;
        }
        $label = $definition['name'] ?? ucfirst(str_replace(['-', '_'], ' ', $field));
        $instructions = [$type === 'html'
            ? "Extract the value for the field \"{$label}\". Return semantic HTML using only "
                . '<p>, <h2>, <h3>, <strong>, <em>, <a>, <ul>, <ol>, <li> tags — no script, style, '
                . 'or inline event handler attributes.'
            : "Extract the value for the field \"{$label}\". Return plain text only, no HTML tags."];
        foreach ($definition['ai_prompts']['_all'] ?? [] as $extra) {
            $instructions[] = openai_import_document_extraction_instruction($extra);
        }
        $fields[$field] = ['instructions' => $instructions];
    }

    // location-picker fields declare their own paired longitude field
    // (see field-location-picker/index.tpl's `longitude_field` option), so
    // geocoding support needs no per-project configuration — it's a
    // structural property of the field type, not project-specific copy the
    // way ai_prompts is.
    $geo_bounds = [];
    foreach ($meta['fields'] as $field => $definition) {
        if (($definition['type'] ?? 'text') !== 'location-picker') {
            continue;
        }
        $lng_field = $definition['longitude_field'] ?? '';
        $lng_definition = $meta['fields'][$lng_field] ?? null;
        if ($lng_field === '' || $lng_definition === null) {
            continue;
        }
        if (!openai_translation_value_is_empty($current_values[$field] ?? '', $definition)
            || !openai_translation_value_is_empty($current_values[$lng_field] ?? '', $lng_definition)) {
            continue;
        }
        $lat_min = $definition['min'] ?? -90;
        $lat_max = $definition['max'] ?? 90;
        $lng_min = $lng_definition['min'] ?? -180;
        $lng_max = $lng_definition['max'] ?? 180;
        $fields[$field] = [
            'instructions' => [
                'Name the single primary real-world place the document is actually about (a town, '
                    . 'region, landmark, or address), then give your best-effort estimate of its '
                    . "decimal WGS84 latitude (between {$lat_min} and {$lat_max}), from your own "
                    . 'geographic knowledge of that place. Only omit this field if the document names '
                    . 'no real-world place at all — a named real place always gets an estimate, even '
                    . 'an approximate one. Return only a plain decimal number, nothing else.',
            ],
        ];
        $fields[$lng_field] = [
            'instructions' => [
                'Give your best-effort estimate of the decimal WGS84 longitude of that same primary '
                    . "place (between {$lng_min} and {$lng_max}); it must correspond to the same "
                    . 'place as the latitude field. Only omit this field if the document names no '
                    . 'real-world place at all. Return only a plain decimal number, nothing else.',
            ],
        ];
        $geo_bounds[$field] = [$lat_min, $lat_max];
        $geo_bounds[$lng_field] = [$lng_min, $lng_max];
    }

    if (empty($fields)) {
        return json_result(['values' => (object)[], 'detected_language' => null]);
    }

    $languages = $meta['languages'] ?? [];
    $detect_language = count($languages) > 1;

    $system_prompt = 'Extract structured field values from the document text supplied under '
        . '"document". For each requested field, follow that field\'s own instructions to decide '
        . 'what content (if any) to extract from the document. Do not translate — return the '
        . "content in the document's own language. If a field's content cannot be found in "
        . 'the document, omit that key entirely rather than guessing.'
        . ($detect_language
            ? ' Additionally return a "_detected_language" key set to the language code of the '
                . 'document itself, choosing only from: ' . implode(', ', $languages) . '.'
            : '')
        . ' Return one JSON object with exactly the requested field keys that were actually found'
        . ($detect_language ? ', plus "_detected_language".' : '.');

    $extra_keys = $detect_language ? ['_detected_language'] : [];
    $result = openai_get_record_completion($api_key, $fields, '', $system_prompt, $extra_keys, $text);
    if ($result === false) {
        return json_result(['message' => 'OPENAI_FAIL'], 500);
    }

    $detected_language = $result['_detected_language'] ?? null;
    unset($result['_detected_language']);
    if (!in_array($detected_language, $languages, true)) {
        $detected_language = null;
    }

    foreach ($result as $field => $value) {
        if (isset($geo_bounds[$field])) {
            [$min, $max] = $geo_bounds[$field];
            if (!is_numeric($value) || (float)$value < $min || (float)$value > $max) {
                unset($result[$field]);
                continue;
            }
            $result[$field] = (string)round((float)$value, 6);
            continue;
        }
        $type = $meta['fields'][$field]['type'] ?? 'text';
        // The model inconsistently HTML-entity-escapes markup instead of
        // returning raw tags for the html type (a JSON string needs neither —
        // "<p>" is valid JSON-string content as-is); decode first so both
        // response styles land in the same place.
        $value = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
        $result[$field] = $type === 'html'
            ? strip_tags($value, '<p><h2><h3><strong><em><a><ul><ol><li>')
            : strip_tags($value);
    }
    // If only one of the pair came back valid, geocoding is meaningless —
    // drop the lone half rather than plot a point on the equator/prime
    // meridian by accident.
    foreach ($meta['fields'] as $field => $definition) {
        if (($definition['type'] ?? 'text') !== 'location-picker') {
            continue;
        }
        $lng_field = $definition['longitude_field'] ?? '';
        $has_lat = array_key_exists($field, $result);
        $has_lng = array_key_exists($lng_field, $result);
        if ($has_lat !== $has_lng) {
            unset($result[$field], $result[$lng_field]);
        }
    }

    return json_result(['values' => $result, 'detected_language' => $detected_language]);
}

/**
 * ai_prompts entries are written for the translate-existing-record feature
 * ("Translate the title of a travel article...") and, sent verbatim, reliably
 * out-argue the system prompt's "do not translate" instruction — the model
 * follows the more specific per-field wording. Swapping the translate/translation
 * verb for an extraction-flavored one keeps the useful part (what the field
 * is, what to preserve) while dropping the part that causes mistranslation.
 */
function openai_import_document_extraction_instruction(string $instruction): string
{
    return preg_replace_callback('/\btranslat(e|es|ed|ing)\b/i', function ($m) {
        $replacement = ['e' => 'extract', 'es' => 'extracts', 'ed' => 'extracted', 'ing' => 'extracting'][strtolower($m[1])];
        return ctype_upper($m[0][0]) ? ucfirst($replacement) : $replacement;
    }, $instruction);
}
