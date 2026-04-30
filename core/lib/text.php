<?php

function text_sc($params)
{
    $lang = $GLOBALS['SYSTEM']['variables']['language'] ?? 'base';
    $key = implode(' ', $params);
    return text_translate($lang, $key);
}

function t($str)
{
    return text_sc(['str' => $str]);
}

function text_translate($lang, $key)
{
    static $results = [];

    /* get translations for this language if they are not yet stored */
    if (!isset($results[$lang])) {
        $results[$lang] = text_parse_po($GLOBALS['SYSTEM']['file_base'] . 'ext/data/.i18n/text.' . $lang . '.po');
    }

    /* return stored translation */
    if (isset($results[$lang][$key])) {
        return $results[$lang][$key];
    } 
    
    /* not found.. return value from base translation bundle */
    if ($lang !== 'base') {
        return text_translate('base', $key);
    }

    /* if nothing else gave a result, a translation does not exists: return the $key */
    $results[$lang][$key] = $key;
    return $key;
}

function text_parse_po($po_file_path)
{
    $result = [];
 
    if (($fh = @fopen($po_file_path, "r")) === false) {
        return $result;
    }

    $msgid = "";
    $msgstr = "";

    while (!feof($fh)) {
        $line = fgets($fh);
        if (stripos($line, "msgid") === 0) {
            if (!empty($msgid)) {
                $result[$msgid] = $msgstr;
            }
            $str_start = strpos($line, '"') + 1;
            $str_stop = strpos($line, '"', $str_start);
            $msgid = substr($line, $str_start, $str_stop - $str_start);
            $msgstr = "";
            continue;
        }
        $str_start = strpos($line, '"');
        if ($str_start === false) {
            continue;
        }
        $str_stop = strpos($line, '"', $str_start + 1);
        if ($str_stop === false) {
            continue;
        }
        $len = $str_stop - $str_start - 1;
        if ($len > 0) {
            $msgstr .= substr($line, $str_start + 1, $len);
        }
    }
    if (!empty($msgid)) {
        $result[$msgid] = $msgstr;
    }
    fclose($fh);    
    return $result;
}
