<?php

/**
 * Strips event-handler attributes and unsafe URL schemes from html-type
 * field values before they are written to storage. Tag-level filtering
 * already happens elsewhere (strip_tags() in json_input()/get_html_sc()) —
 * this is an additive pass scoped to attributes those filters never touch,
 * applied once at write time rather than on every render.
 */
function sanitize_html_attrs(string $html): string
{
    if (trim($html) === '') {
        return $html;
    }

    $doc = new DOMDocument();
    $prev_errors = libxml_use_internal_errors(true);
    $loaded = $doc->loadHTML(
        '<?xml encoding="utf-8"?><div>' . $html . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();
    libxml_use_internal_errors($prev_errors);

    if (!$loaded) {
        // Couldn't parse it even with libxml's very lenient HTML recovery —
        // treat it as suspect and fail closed rather than storing it as-is.
        return strip_tags($html);
    }

    $changed = false;
    $xpath = new DOMXPath($doc);
    foreach ($xpath->query('//*') as $el) {
        $remove = [];
        foreach ($el->attributes as $attr) {
            if (
                stripos($attr->name, 'on') === 0
                || $attr->name === 'srcdoc'
            ) {
                $remove[] = $attr->name;
            } elseif (
                ($attr->name === 'href' || $attr->name === 'src')
                && !_html_sanitize_url_allowed($attr->value, $el->tagName, $attr->name)
            ) {
                $remove[] = $attr->name;
            }
        }
        foreach ($remove as $name) {
            $el->removeAttribute($name);
            $changed = true;
        }
    }

    // Nothing dangerous found — return the original bytes untouched rather
    // than reserializing, since DOMDocument's serializer normalizes things
    // (URL percent-encoding, entity representation) that have nothing to do
    // with safety and would otherwise silently rewrite unrelated, already-
    // published content on every future save.
    if (!$changed) {
        return $html;
    }

    $wrapper = $doc->getElementsByTagName('div')->item(0);
    $result = '';
    foreach ($wrapper->childNodes as $child) {
        $result .= $doc->saveHTML($child);
    }
    return $result;
}

/**
 * Runs sanitize_html_attrs() over every `type: html` field in a single
 * flat field=>value record (as produced by api_json_input() for
 * resource_post/resource_id_post/resource_id_put, and per-record inside
 * resource_put's bulk update map). Handles both plain string values and
 * i18n {lang: value} objects. Non-html fields and absent keys are left
 * untouched.
 */
function sanitize_html_fields(array $meta, array $data): array
{
    $fields = is_array($meta['fields'] ?? null) ? $meta['fields'] : [];
    foreach ($fields as $field => $def) {
        if (($def['type'] ?? null) !== 'html' || !isset($data[$field])) {
            continue;
        }
        if (is_string($data[$field])) {
            $data[$field] = sanitize_html_attrs($data[$field]);
        } elseif (is_array($data[$field])) {
            foreach ($data[$field] as $lang => $value) {
                if (is_string($value)) {
                    $data[$field][$lang] = sanitize_html_attrs($value);
                }
            }
        }
    }
    return $data;
}

/**
 * Denylist, not allowlist: only blocks schemes that can execute script
 * (javascript:, vbscript:) or smuggle an executable document (data:,
 * except raster-image data: URIs on src, the lazy-load placeholder
 * pattern already used elsewhere in this codebase). Anything else —
 * including odd-but-inert schemes like file:// or applewebdata:// that
 * show up in real pasted content — is left alone rather than silently
 * stripped from existing articles.
 */
function _html_sanitize_url_allowed(string $url, string $tag, string $attr): bool
{
    // Browsers strip ASCII control characters (tabs, newlines, ...)
    // anywhere in a URL before resolving its scheme, so a naive
    // ^javascript: match can be defeated by hiding one inside the
    // scheme name (java\tscript:...). Strip them everywhere, not just
    // at the edges, before matching.
    $url = trim(preg_replace('~[\x00-\x1F]~', '', $url) ?? '');
    if ($url === '' || strpos($url, ':') === false) {
        return true;
    }
    if (preg_match('~^(javascript|vbscript):~i', $url)) {
        return false;
    }
    if (preg_match('~^data:~i', $url)) {
        // Only on src, only raster formats — svg can carry <script>/
        // onload and is never needed for the lazy-load placeholder use
        // case this exists for. Never on href: a data:image/... link
        // still executes as a full document on click/navigation, where
        // the "img context can't run scripts" assumption doesn't hold.
        return $attr === 'src' && $tag !== 'iframe'
            && preg_match('~^data:image/(png|gif|jpe?g|webp);base64,~i', $url) === 1;
    }
    return true;
}
