<?php

// The team chat button, for logged-in users who have at least one agent to talk to.
function agent_chat_widget_sc($_params = null): void
{
    load_library('session');
    if (!session_resume() || empty($_SESSION['features'])) {
        return;
    }
    load_libraries(['agent', 'agent-chat', 'run']);
    if (agent_chat_team() === []) {
        return;
    }
    run_single_sc('agent-chat-panel');
}
