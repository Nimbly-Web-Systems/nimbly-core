<?php

/**
 * The site's full configured language list, as JSON, for client JS that
 * needs to build UI over every language rather than just the current one
 * ([#language#]). Never hardcode a language list in JS — read this.
 */
function site_languages_json_sc()
{
    load_library('data');
    echo json_encode(data_lookup('.config', 'site', 'languages', ['en']));
}
