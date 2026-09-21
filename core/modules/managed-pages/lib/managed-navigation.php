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

function managed_navigation_validate_items(array $items, int $max_depth, int $depth = 1): ?array
{
    if ($items === []) {
        return [];
    }
    if ($depth > $max_depth) {
        return null;
    }
    $result = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            return null;
        }
        $kind = (string)($item['target']['kind'] ?? '');
        if (!in_array($kind, ['page', 'internal_url', 'external_url', 'group'], true)) {
            return null;
        }
        $label = trim((string)($item['label'] ?? ''));
        if ($label === '' || mb_strlen($label) > 120) {
            return null;
        }
        $value = trim((string)($item['target']['value'] ?? $item['target']['id'] ?? $item['target']['path'] ?? ''));
        if ($kind === 'internal_url') {
            $value = managed_pages_normalize_path($value);
            if ($value === null) {
                return null;
            }
        } elseif ($kind === 'external_url') {
            $scheme = strtolower((string)parse_url($value, PHP_URL_SCHEME));
            if (!filter_var($value, FILTER_VALIDATE_URL) || !in_array($scheme, ['http', 'https'], true)) {
                return null;
            }
        } elseif ($kind === 'page' && ($value === '' || !data_exists('pages', $value))) {
            return null;
        } elseif ($kind === 'group') {
            $value = '';
        }
        $children = $item['children'] ?? [];
        if (!is_array($children)) {
            return null;
        }
        $children = managed_navigation_validate_items($children, $max_depth, $depth + 1);
        if ($children === null) {
            return null;
        }
        $result[] = [
            'id' => preg_match('/^[a-zA-Z0-9_-]+$/', (string)($item['id'] ?? ''))
                ? (string)$item['id'] : bin2hex(random_bytes(8)),
            'label' => $label,
            'target' => ['kind' => $kind, 'value' => $value],
            'children' => $children,
        ];
    }
    return $result;
}

/** Replace a complete tree and reject saves based on an older revision. */
function managed_navigation_save(string $slot, string $language, array $items, string $expected_revision): array
{
    $slots = managed_navigation_slots();
    if (empty($slots[$slot])) {
        return ['ok' => false, 'error' => 'unknown-slot'];
    }
    $languages = data_lookup('.config', 'site', 'languages', ['en']);
    if (!in_array($language, is_array($languages) ? $languages : ['en'], true)) {
        return ['ok' => false, 'error' => 'unknown-language'];
    }
    $items = managed_navigation_validate_items($items, max(1, (int)($slots[$slot]['depth'] ?? 1)));
    if ($items === null) {
        return ['ok' => false, 'error' => 'invalid-tree'];
    }
    $resource_dir = data_path('.navigation');
    if (!is_dir($resource_dir) && !mkdir($resource_dir, 0750, true) && !is_dir($resource_dir)) {
        return ['ok' => false, 'error' => 'write-failed'];
    }
    $lock = @fopen($resource_dir . '/.tree.lock', 'c');
    if ($lock === false || !flock($lock, LOCK_EX)) {
        if (is_resource($lock)) fclose($lock);
        return ['ok' => false, 'error' => 'write-failed'];
    }
    try {
        $uuid = managed_navigation_document_id($slot, $language);
        $existing = data_read('.navigation', $uuid);
        if (managed_navigation_revision(is_array($existing) ? $existing : null) !== $expected_revision) {
            return ['ok' => false, 'error' => 'stale'];
        }
        $document = [
            'slot' => $slot,
            'language' => $language,
            'items' => $items,
            '_revision' => bin2hex(random_bytes(16)),
        ];
        if (!data_create('.navigation', $uuid, $document)) {
            return ['ok' => false, 'error' => 'write-failed'];
        }
        return ['ok' => true, 'document' => data_read('.navigation', $uuid)];
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
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
