<?php



function detect_language_sc() {
    static $ALLOWED_LANG = ['nl', 'en'];

    //1. from url
    $uri = $GLOBALS['SYSTEM']['request_uri'];
    foreach ($ALLOWED_LANG as $l) {
        
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
    if (!empty($lang) && strlen($lang) === 2 && in_array($lang, $ALLOWED_LANG)) {
        return $lang;
    }

    //3. from top level domain
    $tld = end(explode('.', $_SERVER['SERVER_NAME']));
    if (!empty($tld) && in_array($tld, $ALLOWED_LANG)) {
        return $tld;
    } 
    
    //4. from browser language
    $browser_language = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en', 0, 2);
    if (in_array($browser_language, $ALLOWED_LANG)) {
        return $browser_language;
    }
    
    //5. default to 'en' if any other method failed
    return "en";
}
