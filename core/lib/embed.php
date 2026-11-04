<?php

/** Embed metadata is input, never executable markup or an authorization token. */
function embed_url_allowed(string $url): bool
{
    $parts = parse_url($url);
    return !preg_match('/[\x00-\x20\\\\]/', $url) && is_array($parts)
        && strtolower($parts['scheme'] ?? '') === 'https' && !empty($parts['host'])
        && !isset($parts['user']) && !isset($parts['pass']);
}

function embed_document(string $html): DOMDocument
{
    $doc = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8"?><html><body>' . $html . '</body></html>', LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    return $doc;
}

function embed_dimension($value, int $fallback): int
{
    return preg_match('/^[1-9][0-9]{0,4}$/', (string)$value) ? min(10000, (int)$value) : $fallback;
}

function embed_info(DOMElement $frame): ?array
{
    $src = $frame->getAttribute('src');
    if (!embed_url_allowed($src) || $frame->hasAttribute('srcdoc')) {
        return null;
    }
    $metadata = json_decode($frame->getAttribute('data-nb-embed'), true);
    $metadata = is_array($metadata) ? $metadata : [];
    $sandbox = ($metadata['sandbox'] ?? $frame->hasAttribute('sandbox')) !== false;
    // Frames preserving their origin must be external to this application.
    $site_host = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]);
    if ($sandbox && strtolower(parse_url($src, PHP_URL_HOST) ?? '') === $site_host) {
        return null;
    }
    $scripts = [];
    foreach ((array)($metadata['scripts'] ?? []) as $url) {
        if (!is_string($url) || !embed_url_allowed($url)) {
            return null;
        }
        if (!in_array($url, $scripts, true)) {
            $scripts[] = $url;
        }
    }
    return [
        'src' => $src,
        'title' => trim($frame->getAttribute('title')) ?: 'Embedded media',
        'width' => embed_dimension($frame->getAttribute('width'), 640),
        'height' => embed_dimension($frame->getAttribute('height'), 360),
        'proportional' => ($metadata['proportional'] ?? false) === true,
        'sandbox' => $sandbox,
        'scripts' => $scripts,
    ];
}

function embed_markup(array $info, bool $render = false, bool $preview = false): string
{
    $info['scripts'] = array_values(array_unique($info['scripts']));
    $escape = fn($value) => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $style = 'display:block;border:0;max-width:100%;width:100%;height:' . $info['height'] . 'px;';
    if ($info['proportional']) {
        $style = 'display:block;border:0;max-width:100%;width:100%;height:auto;aspect-ratio:' . $info['width'] . '/' . $info['height'] . ';';
    }
    $attrs = ' data-nb-embed="' . $escape(json_encode($info, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) . '"'
        . ' src="' . $escape($info['src']) . '" title="' . $escape($info['title']) . '"'
        . ' width="' . $info['width'] . '" height="' . $info['height'] . '" style="' . $style . '"'
        . ' loading="lazy" allow="autoplay; fullscreen; picture-in-picture"';
    if ($info['sandbox'] || $preview) {
        $tokens = 'allow-scripts allow-presentation';
        if ($render && !$preview && $info['scripts']) {
            $inner = '<!doctype html><meta charset="utf-8"><style>html,body{margin:0;width:100%;height:100%}iframe{width:100%;height:100%;border:0}</style>'
                . '<iframe src="' . $escape($info['src']) . '" title="' . $escape($info['title']) . '" allow="autoplay; fullscreen; picture-in-picture"></iframe>';
            $urls = json_encode($info['scripts'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            $inner .= '<script>addEventListener("message",function ready(e){'
                . 'if(e.source!==parent||e.data!=="nb:embed-ready")return;removeEventListener("message",ready);'
                . 'const urls=' . $urls . ';let i=0;function next(){if(i===urls.length)return;'
                . 'const s=document.createElement("script");s.src=urls[i++];s.async=false;s.fetchPriority="low";'
                . 's.onload=next;s.onerror=next;document.body.append(s);}next();});</script>';
            $attrs .= ' srcdoc="' . $escape($inner) . '"';
        } elseif (strtolower(parse_url($info['src'], PHP_URL_HOST) ?? '') !== strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0])) {
            $tokens .= ' allow-same-origin';
        }
        $attrs .= ' sandbox="' . $tokens . '"';
    } elseif ($render && $info['scripts']) {
        load_library('collect-script');
        foreach ($info['scripts'] as $url) {
            collect_external_script($url);
        }
    }
    return '<iframe' . $attrs . '></iframe>';
}

/** Normalize frames while keeping surrounding text and images as ordinary HTML. */
function embed_normalize_html(string $html, bool $render = false, bool $preview = false): string
{
    if (stripos($html, '<iframe') === false) {
        return $html;
    }
    $doc = embed_document($html);
    foreach (iterator_to_array($doc->getElementsByTagName('iframe')) as $frame) {
        $info = embed_info($frame);
        if (!$info) {
            $frame->parentNode->removeChild($frame);
            continue;
        }
        $replacement = embed_document(embed_markup($info, $render, $preview))->getElementsByTagName('iframe')->item(0);
        $frame->parentNode->replaceChild($doc->importNode($replacement, true), $frame);
    }
    $result = '';
    foreach ($doc->getElementsByTagName('body')->item(0)->childNodes as $child) {
        $result .= $doc->saveHTML($child);
    }
    return $result;
}

function embed_privileged_frames(string $html): array
{
    $result = [];
    foreach (embed_document($html)->getElementsByTagName('iframe') as $frame) {
        $info = embed_info($frame);
        if ($info && !$info['sandbox']) {
            $result[] = json_encode($info);
        }
    }
    return $result;
}

/** Compare multisets per field/language: retaining or deleting is allowed; copying is not. */
function embed_validate_fields(array $meta, array &$record, array $previous): bool
{
    $permitted = null;
    $fields = is_array($meta['fields'] ?? null) ? $meta['fields'] : [];
    foreach ($fields as $field => $definition) {
        if (($definition['type'] ?? '') !== 'html' || !isset($record[$field])) {
            continue;
        }
        $values = is_array($record[$field]) ? $record[$field] : ['' => $record[$field]];
        $old = is_array($previous[$field] ?? null) ? $previous[$field] : ['' => $previous[$field] ?? ''];
        foreach ($values as $lang => $value) {
            if (!is_string($value) || stripos($value, '<iframe') === false) {
                continue;
            }
            if ($permitted === null) {
                require_once dirname(__DIR__) . '/modules/user/lib/permissions.php';
                $permitted = permission_session_has('manage-unsandboxed-embeds');
            }
            if (!$permitted) {
                $allowed = embed_privileged_frames((string)($old[$lang] ?? ''));
                foreach (embed_privileged_frames($value) as $fingerprint) {
                    $key = array_search($fingerprint, $allowed, true);
                    if ($key === false) {
                        data_error_set('VALIDATION_FAILED', 'manage-unsandboxed-embeds');
                        return false;
                    }
                    unset($allowed[$key]);
                }
            }
            $values[$lang] = embed_normalize_html($value);
        }
        $record[$field] = is_array($record[$field]) ? $values : $values[''];
    }
    return true;
}
