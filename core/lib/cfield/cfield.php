<?php

/**
 * Shortcode: [#cfield fieldname#]
 * @doc Resolves the full dot-path of a content field in the current context.
 * @doc This is typically used to identify the fully qualified field name within content blocks.
 * @doc Example: In block `about`, `[cfield main_text]` → `content.about.main_text`
 * @doc Useful for overlays, inline editing, or content-specific logic.
 *
 * @param array $params A single field name, e.g. `main_text`.
 * @return string Canonical dot-path (e.g. `content.about.main_text`), or `(empty)` if unresolved.
 */
function cfield_sc($params) {
    $rs = dot2rs(current($params));
    return $rs ? implode('.', $rs) : '(empty)';
}