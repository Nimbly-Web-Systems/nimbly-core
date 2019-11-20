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
		'cover_images' => array(),
		'media_uuids' => array(),
		'media_names' => array(),
		'media_types' => array());
	$j = 0;
	for ($i = 0; $i < $max; $i++) {
		$muuid = get_variable('record' . '.' . $name . $i);
		if (empty($muuid)) {
			continue;
		}
		$cover_img = get_variable('record' . '.' . $name . $i . '_cover', false);
		$result['media_uuids'][$j] = $muuid;
		$result['cover_images'][$j] = $cover_img;
		$meta = data_read('.files_meta', $muuid, ['name', 'type']);
		$result['media_names'][$j] = $meta['name'];
		$result['media_types'][$j] = $meta['type'];
		$j++;
	}
	echo json_encode($result, JSON_UNESCAPED_UNICODE);
}