<?php

function text_sc($params)
{
    if (isset($GLOBALS['SYSTEM']['variables']['language'])) {
        $languages = [$GLOBALS['SYSTEM']['variables']['language'], ''];
    } else {
        $languages = [''];
    }
    $path = $GLOBALS['SYSTEM']['file_base'] . 'ext/data/.i18n/bundle.';
    $key = implode(' ', $params);
    static $results = [];
    if (isset($results[$key])) {
        return $results[$key];
    }

    $store = function($id, $txt) use ($key, &$results) {
        if (empty($id) || empty($txt)) {
            return false;
        }
        $results[$id] = $txt;
        return isset($results[$key]);
    };

    foreach ($languages as $language) {
        $text_include_file = $path;
        if (!empty($language)) {
            $text_include_file .= $language . ".";
        }
        $text_include_file .= "po";
        $fh = @fopen($text_include_file, "r");
        if ($fh === false) {
            continue;
        }
        $msgid = "";
        $msgstr = "";
        while (!feof($fh)) {
            $line = fgets($fh);
            if (stripos($line, "msgid") === 0) {
                if ($store($msgid, $msgstr)) {
                    fclose($fh);
                    return $results[$key];
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
        fclose($fh);
        if ($store($msgid, $msgstr)) {
            return $results[$key];
        }
    }
    $results[$key] = $key;
    return $key;
}

function t($str)
{
    return text_sc(['str' => $str]);
}
