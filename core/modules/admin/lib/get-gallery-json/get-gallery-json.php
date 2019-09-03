<?php

function get_gallery_json_sc($params) {
	$uuid = get_param_value($params, 'uuid');
	$name = get_param_value($params, 'name');
	$max = get_param_value($params, 'max');
	load_library('get');
	load_library('data');
	$result = array(
		'name' => $name, 
		'uuid' => $uuid,
		'max' => $max,
		'images' => array(),
		'cover_images' => array(),
		'image_names' => array(),
		'image_types' => array());
	$j = 0;
	for ($i = 0; $i < $max; $i++) {
		$img = get_variable('record' . '.' . $name . $i);
		if (empty($img)) {
			continue;
		}
		$cover_img = get_variable('record' . '.' . $name . $i . '_cover', false);
		$result['images'][$j] = $img;
		$result['cover_images'][$j] = $cover_img;
		$meta = data_read('.files_meta', $img, ['name', 'type']);
		$result['image_names'][$j] = $meta['name'];
		$result['image_types'][$j] = $meta['type'];
		$j++;
	}
	echo json_encode($result, JSON_UNESCAPED_UNICODE);
}