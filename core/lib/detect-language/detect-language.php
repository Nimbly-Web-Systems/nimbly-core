<?php



function detect_language_sc() {
    load_library('lookup');
    $allowed_lang = lookup_data('.config', 'site', 'languages', ['en']);
    //1. from url
    $uri = $GLOBALS['SYSTEM']['request_uri'];
    foreach ($allowed_lang as $l) {
        
        if (stripos($uri, '/' . $l . '/') !== false) {
            return $l;
        }     

        if (stripos($uri, $l . '/') === 0) {
            return $l;
        }  

        if ($uri === $l || $uri === '/' . $l || $uri === '/' . $l . '/') {
            return $l;
        }
    }
    
    //2. from user preference
    load_library('get');
    $lang = get_variable('lang');
    if (!empty($lang) && strlen($lang) === 2 && in_array($lang, $allowed_lang)) {
        return $lang;
    }

    //3. from top level domain
    $server_name_parts = explode('.', $_SERVER['SERVER_NAME']);
    $tld = end($server_name_parts);
    if (!empty($tld) && in_array($tld, $allowed_lang)) {
        return $tld;
    } 
    
    //4. from browser language
    $browser_language = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en', 0, 2);
    if (in_array($browser_language, $allowed_lang)) {
        return $browser_language;
    }
    
    //5. default to 'en' if any other method failed
    return "en";
}
