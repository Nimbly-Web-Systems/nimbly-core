<?php

function init_img_sc($params) {
	load_library('set');
	set_variable('max_img_w', 1920, false);
	set_variable('max_img_h', 1080, false);
	set_variable('watermark_image', false, false);
}