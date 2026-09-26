<?php

// The team chat button, for logged-in users who have at least one agent to talk to.
function agent_chat_widget_sc($_params = null): void
{
    load_library('session');
    if (!session_resume() || empty($_SESSION['features'])) {
        return;
    }
    load_libraries(['data', 'username', 'agent', 'agent-chat', 'run']);
    $team = agent_chat_team();
    if ($team === []) {
        return;
    }
    agent_chat_ensure_resource();
    agent_chat_welcome((string)username_get(), $team);
    run_single_sc('agent-chat-panel');
}
