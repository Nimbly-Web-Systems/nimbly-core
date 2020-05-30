<?php

if (($parts = router_match(__FILE__)) !== false) {
    load_library('return-img', 'media');
    return_img('avatars/' . $parts[0], $parts[1], $parts[2]);
}