<?php

function managed_navigation_slots(): array
{
    load_library('managed-pages');
    return managed_pages_declaration('navigation-slots.json');
}

function managed_navigation_document_id(string $slot, string $language): string
{
    return preg_replace('/[^a-zA-Z0-9_-]/', '-', $slot . '-' . $language);
}

function managed_navigation_revision(?array $document): string
{
    return is_array($document) ? (string)($document['_revision'] ?? '') : '';
}

/**
 * Normalize a navigation tree. Returns null on the first problem and sets
 * $error to "<item id>:<reason>" so the editor can point at the offending row.
 */
function managed_navigation_check_items(array $items, int $max_depth, ?string &$error = null, int $depth = 1): ?array
{
    if ($items === []) {
        return [];
    }
    $result = [];
    foreach ($items as $index => $item) {
        $id = is_array($item) && preg_match('/^[a-zA-Z0-9_-]+$/', (string)($item['id'] ?? ''))
            ? (string)$item['id'] : 'index-' . $index;
        if (!is_array($item)) {
            $error = $id . ':item';
            return null;
        }
        if ($depth > $max_depth) {
            $error = $id . ':depth';
            return null;
        }
        $kind = (string)($item['target']['kind'] ?? '');
        if (!in_array($kind, ['page', 'internal_url', 'external_url', 'group'], true)) {
            $error = $id . ':kind';
            return null;
        }
        $label = trim((string)($item['label'] ?? ''));
        if ($label === '' || mb_strlen($label) > 120) {
            $error = $id . ':label';
            return null;
        }
        $value = trim((string)($item['target']['value'] ?? $item['target']['id'] ?? $item['target']['path'] ?? ''));
        if ($kind === 'internal_url') {
            $value = managed_pages_normalize_path($value);
        } elseif ($kind === 'external_url') {
            $scheme = strtolower((string)parse_url($value, PHP_URL_SCHEME));
            $value = filter_var($value, FILTER_VALIDATE_URL) && in_array($scheme, ['http', 'https'], true) ? $value : null;
        } elseif ($kind === 'page') {
            $value = $value !== '' && data_exists('pages', $value) ? $value : null;
        } else {
            $value = '';
        }
        if ($value === null) {
            $error = $id . ':target';
            return null;
        }
        $children = $item['children'] ?? [];
        if (!is_array($children)) {
            $error = $id . ':children';
            return null;
        }
        $children = managed_navigation_check_items($children, $max_depth, $error, $depth + 1);
        if ($children === null) {
            return null;
        }
        $result[] = [
            'id' => $id === 'index-' . $index ? bin2hex(random_bytes(8)) : $id,
            'label' => $label,
            'target' => ['kind' => $kind, 'value' => $value],
            'children' => $children,
        ];
    }
    return $result;
}

function managed_navigation_validate_items(array $items, int $max_depth, int $depth = 1): ?array
{
    $error = null;
    return managed_navigation_check_items($items, $max_depth, $error, $depth);
}

/**
 * Resource validator for `.navigation`, run on every write path (API PUT
 * included, under the resource write lock). A record replaces the complete tree
 * and must carry the `revision` it was loaded with; a mismatch is a stale edit.
 */
function managed_navigation_validate_record($resource, $uuid, &$record): bool
{
    if ($resource !== '.navigation' || !is_array($record) || $record === []) {
        return true;
    }
    $slot = (string)($record['slot'] ?? '');
    $language = (string)($record['language'] ?? '');
    $slots = managed_navigation_slots();
    $languages = data_lookup('.config', 'site', 'languages', ['en']);
    if (empty($slots[$slot])) {
        data_error_set('VALIDATION_FAILED', 'slot:unknown');
        return false;
    }
    if (!in_array($language, is_array($languages) ? $languages : ['en'], true)) {
        data_error_set('VALIDATION_FAILED', 'language:unknown');
        return false;
    }
    if ((string)$uuid !== managed_navigation_document_id($slot, $language)) {
        data_error_set('VALIDATION_FAILED', 'uuid:mismatch');
        return false;
    }
    if (!is_array($record['items'] ?? [])) {
        data_error_set('VALIDATION_FAILED', 'items:list');
        return false;
    }
    $error = null;
    $items = managed_navigation_check_items($record['items'] ?? [], max(1, (int)($slots[$slot]['depth'] ?? 1)), $error);
    if ($items === null) {
        data_error_set('VALIDATION_FAILED', 'items.' . $error);
        return false;
    }
    $existing = data_read('.navigation', $uuid);
    $base = (string)($record['revision'] ?? '');
    if ($base !== managed_navigation_revision(is_array($existing) ? $existing : null)) {
        data_error_set('VALIDATION_FAILED', 'revision:stale');
        return false;
    }
    $record['items'] = $items;
    // Derived from the base revision and content, so the second validation
    // inside the write lock computes the same value.
    $record['_revision'] = substr(hash('sha256', $base . json_encode($items)), 0, 32);
    return true;
}

function managed_navigation_resolve_items(array $items, string $language, string $current_path, bool $ancestor = false): array
{
    $result = [];
    foreach ($items as $item) {
        $kind = $item['target']['kind'] ?? '';
        $value = $item['target']['value'] ?? '';
        $url = null;
        if ($kind === 'page') {
            $url = managed_pages_url((string)$value, $language, true);
        } elseif ($kind === 'internal_url') {
            $url = managed_pages_normalize_path($value);
        } elseif ($kind === 'external_url' && filter_var($value, FILTER_VALIDATE_URL)) {
            $url = $value;
        }
        if ($kind !== 'group' && $url === null) {
            continue;
        }
        $children = managed_navigation_resolve_items($item['children'] ?? [], $language, $current_path);
        $current = is_string($url) && trim($url, '/') === trim($current_path, '/');
        $child_current = (bool)array_filter(
            $children,
            fn($child) => ($child['current'] ?? 'false') === 'true' || ($child['ancestor'] ?? 'false') === 'true'
        );
        $result[] = [
            'id' => $item['id'] ?? '',
            'label' => $item['label'] ?? '',
            'label_html' => htmlspecialchars((string)($item['label'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'kind' => $kind,
            'url' => $url ?? '',
            'current' => $current ? 'true' : 'false',
            'ancestor' => !$current && $child_current ? 'true' : 'false',
            'children' => $children,
        ];
    }
    return $result;
}

function managed_navigation_load(string $slot, string $language): array
{
    $slots = managed_navigation_slots();
    if (empty($slots[$slot])) {
        return [];
    }
    $document = data_read('.navigation', managed_navigation_document_id($slot, $language));
    if (!is_array($document)) {
        return [];
    }
    return managed_navigation_resolve_items(
        is_array($document['items'] ?? null) ? $document['items'] : [],
        $language,
        (string)($GLOBALS['SYSTEM']['request_uri'] ?? '')
    );
}

/** [#managed-navigation slot=main var=main_navigation#] */
function managed_navigation_sc($params): string
{
    load_library('get');
    load_library('set');
    load_library('data');
    load_library('managed-pages');
    $slot = (string)get_param_value($params, 'slot', current($params));
    $language = (string)get_param_value($params, 'language', get_variable('language', 'en'));
    $variable = (string)get_param_value($params, 'var', 'navigation');
    set_variable($variable, managed_navigation_load($slot, $language));
    return '';
}
