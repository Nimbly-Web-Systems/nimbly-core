<?php

function get_form_errors_sc($params) {
    
    if (empty($params)) {
        //return global errors
        $field = "_global";
    } else {
        $field = current($params);
    }
    
    if (empty($GLOBALS['SYSTEM']['validation_errors'][$field])) {
        return;
    }
    
    $errors = $GLOBALS['SYSTEM']['validation_errors'][$field];
    set_variable('validation_errors', $errors); 
}
 
?>
