<?php

/**
 * Whether an OpenAI key is configured — client JS uses this to hide AI
 * translate buttons entirely rather than show one that always 503s.
 */
function ai_translate_available_sc()
{
    load_library('env');
    echo empty(env('OPENAI_API_KEY')) ? 'false' : 'true';
}
