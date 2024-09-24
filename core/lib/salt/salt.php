<?php

function salt_sc() {
    return rtrim(strtr(base64_encode(random_bytes(32)), '+', '.'),'=');
}