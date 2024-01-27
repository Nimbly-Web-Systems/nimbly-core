<?php 

function max_upload_size_sc() {
    load_libraries(['util', 'fmt']);
    echo fmt_bytes(max_upload_size(), 0);
}