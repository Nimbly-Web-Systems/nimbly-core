<?php

load_library('get');

function field_actions_sc($params) {
    $actions = get_variable('_f.actions', []);
    if (empty($actions) || !is_array($actions)) {
        return;
    }

    $field = get_variable('_f.key', '');
    $html = '';
    foreach ($actions as $action) {
        if (!is_array($action) || !_field_action_allowed($action)) {
            continue;
        }
        $html .= _field_action_render($action, $field);
    }

    if ($html === '') {
        return;
    }

    $align = get_param_value($params, 'align', 'center');
    $top_class = $align === 'top' ? 'top-3' : 'top-1/2 -translate-y-1/2';
    return '<div class="absolute right-2 ' . $top_class . ' z-10 flex items-center gap-1">' . $html . '</div>';
}

function _field_action_allowed(array $action): bool
{
    $feature = trim((string)($action['feature'] ?? ''));
    if ($feature === '') {
        return true;
    }

    load_libraries(['session', 'permissions']);
    return session_resume() && isset($_SESSION['features']) && permission_session_has($feature);
}

function _field_action_render(array $action, string $field): string
{
    $type = $action['type'] ?? 'link';
    if ($type === 'link') {
        return _field_action_link($action);
    }
    if ($type === 'ai') {
        return _field_action_ai($action, $field);
    }
    return '';
}

function _field_action_link(array $action): string
{
    $target = _field_action_template((string)($action['target'] ?? ''));
    if ($target === '' || strpos($target, '[#') !== false) {
        return '';
    }

    $label = (string)($action['label'] ?? 'Open');
    $icon = (string)($action['icon'] ?? 'external-link');
    return '<a class="' . _field_action_class() . '" href="' . htmlspecialchars($target, ENT_QUOTES, 'UTF-8') . '" title="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '" aria-label="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '">' . _field_action_icon($icon) . '</a>';
}

function _field_action_ai(array $action, string $field): string
{
    $label = (string)($action['label'] ?? 'Generate with AI');
    $field_arg = htmlspecialchars(json_encode($field, JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
    return '<button type="button" class="' . _field_action_class() . '" title="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '" aria-label="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '" x-bind:disabled="busy" @click.prevent="ai(' . $field_arg . ', lang)">' . _field_action_icon((string)($action['icon'] ?? 'sparkles')) . '</button>';
}

function _field_action_template(string $template): string
{
    if ($template === '') {
        return '';
    }

    ob_start();
    run_template($template);
    return trim((string)ob_get_clean());
}

function _field_action_class(): string
{
    return 'inline-flex h-8 w-8 items-center justify-center rounded-md text-neutral-500 hover:bg-neutral-100 hover:text-neutral-900 focus:outline-none focus:ring-2 focus:ring-neutral-300 disabled:pointer-events-none disabled:opacity-50';
}

function _field_action_icon(string $icon): string
{
    $class = 'h-4 w-4';
    if ($icon === 'shield-check') {
        return '<svg xmlns="http://www.w3.org/2000/svg" class="' . $class . '" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 3.75 5.25 6v5.25c0 4.2 2.7 7.95 6.75 9 4.05-1.05 6.75-4.8 6.75-9V6L12 3.75Z"/></svg>';
    }
    if ($icon === 'sparkles') {
        return '<svg xmlns="http://www.w3.org/2000/svg" class="' . $class . '" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.091-3.091L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.091-3.091L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.091 3.091L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.091 3.091ZM18.25 8.25 17.8 9.8l-1.55.45 1.55.45.45 1.55.45-1.55 1.55-.45-1.55-.45-.45-1.55ZM16 2.25l-.675 2.325L13 5.25l2.325.675L16 8.25l.675-2.325L19 5.25l-2.325-.675L16 2.25Z"/></svg>';
    }
    return '<svg xmlns="http://www.w3.org/2000/svg" class="' . $class . '" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H18v4.5M18 6l-7.5 7.5"/><path stroke-linecap="round" stroke-linejoin="round" d="M18 13.5V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18V8.25A2.25 2.25 0 0 1 6 6h4.5"/></svg>';
}
