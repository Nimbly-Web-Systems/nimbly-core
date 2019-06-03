<?php

function init_img_sc($params) {
	load_library('set');
	$uuid = get_param_value($params, 'uuid', current($params));
	set_variable('max_img_w', 1920);
	set_variable('max_img_h', 1080);
	set_variable('watermark_image', false);
}