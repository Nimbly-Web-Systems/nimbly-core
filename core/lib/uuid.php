<?php

function uuid_sc() {
    $bytes = random_bytes(10);
    return gmp_strval(gmp_init(bin2hex($bytes), 16), 36);
}
