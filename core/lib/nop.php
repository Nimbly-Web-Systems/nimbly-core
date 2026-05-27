<?php

/**
 * @doc [nop] does nothing (no operation). Can be used for comments or to temporarily disable a shortcode in your template: 
 * `[#nop this shortcode call is intentionally disabled#]` does nothing
 * `[#nop <!-- i'm a comment that's only visible to developers --> #]`
 */ 
function nop_sc($params) {
    return; 
}
