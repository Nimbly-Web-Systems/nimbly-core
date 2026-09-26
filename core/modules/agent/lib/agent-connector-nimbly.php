<?php

// Tools of the Nimbly agent: the Nimbly docs and the site it lives in, with the asker's rights.
function agent_connector_nimbly(array $source, array $config, array $context): array
{
    load_library('agent-site');
    $arguments = (array)(agent_artifact_data($source)['arguments'] ?? []);
    try {
        $result = agent_nimbly_tool((string)($config['operation'] ?? ''), $arguments, $context);
    } catch (Throwable $error) {
        // A failing lookup is something to tell the colleague about, not a reason to drop the whole answer.
        $result = ['error' => 'This lookup failed: ' . agent_safe_error($error->getMessage())];
    }
    return agent_artifact('agent.tool-result', 1, $result);
}

function agent_nimbly_tool(string $operation, array $arguments, array $context): array
{
    return match ($operation) {
        'docs' => agent_nimbly_docs((string)($arguments['action'] ?? ''), trim((string)($arguments['query'] ?? ''))),
        'site-map' => agent_site_map(agent_site_asker($context)),
        'records' => agent_site_records(agent_site_asker($context), (string)($arguments['resource'] ?? ''),
            trim((string)($arguments['uuid'] ?? '')), trim((string)($arguments['search'] ?? ''))),
        'write' => agent_site_write(agent_site_asker($context), (string)($arguments['action'] ?? ''),
            (string)($arguments['resource'] ?? ''), trim((string)($arguments['uuid'] ?? '')), agent_nimbly_fields($arguments)),
        default => throw new RuntimeException('Nimbly agent operation is invalid'),
    };
}

function agent_nimbly_fields(array $arguments): array
{
    $fields = json_decode((string)($arguments['fields_json'] ?? ''), true);
    return is_array($fields) && !array_is_list($fields) ? $fields : [];
}

/** The Nimbly reference, a piece at a time: its outline, a search, or one section. */
function agent_nimbly_docs(string $action, string $query): array
{
    load_library('docs');
    $contents = docs_reference();
    $headings = docs_parse_headings($contents);
    if ($action === 'list') {
        return ['outline' => array_map(fn($heading) => str_repeat('  ', $heading['level'] - 1) . $heading['title'],
            array_filter($headings, fn($heading) => $heading['level'] <= 3))];
    }
    if ($query === '') {
        return ['error' => 'Give a search term or a section name.'];
    }
    if ($action === 'search') {
        $lines = docs_search_lines($contents, $headings, $query);
        return $lines === [] ? ['matches' => [], 'hint' => 'No match. Try a shorter term, a function or command name, or list the outline.']
            : ['matches' => array_slice($lines, 0, 40), 'total' => count($lines)];
    }
    try {
        $text = docs_section_text($contents, $headings, docs_find_section($headings, $query));
    } catch (RuntimeException $error) {
        return ['error' => $error->getMessage()];
    }
    return mb_strlen($text) <= 14000 ? ['section' => $text]
        : ['section' => mb_substr($text, 0, 14000), 'truncated' => 'Section is long; ask for a child section ("Parent > Child").'];
}
