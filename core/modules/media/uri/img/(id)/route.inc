<?php

if (($parts = router_match(__FILE__)) !== false) {
    load_library('return-img', 'media');
    return_img('.files', $parts[0], 240);
}