var gallery_field = {
	debug: true
};

gallery_field.init = function(opts) {
	gallery_field.debug && console.log('gallery_field.init', opts);
	opts.tpl_id = 'tpl_gallery_' + opts.uuid + '_row';
	opts.ix = [];
	var $table = $('#gallery_' + opts.uuid + ' tbody' + '.nb-sortable');
	$table.data('opts', opts);
	for (ix in opts.images) {
		gallery_field.add_row($table, parseInt(ix))
	}
	$table.sortable();
	$table.on('sortstop', { 'opts' : opts }, gallery_field.on_sortstop);
	$(document).on('clear-img', function(e, opts) {
		gallery_field.remove_img($table, opts.uuid, opts.img);
	});
	$table.on('click', 'a[data-move-up]', gallery_field.move_up);
	$table.on('click', 'a[data-move-down]', gallery_field.move_down);
	gallery_field.refresh($table);
	$(document).on(opts.name + '_upload', gallery_field.handle_upload);
	$(document).on('data-select', gallery_field.handle_image_select);
}

gallery_field.data_context = function(opts, x) {
	gallery_field.debug && console.log('gallery_field.data_context', opts, x);
	var img_nr = parseInt(x) + 1;
	return {
		'img_nr': img_nr, 
		'img_uuid': opts.images[x], 
		'img_uuid_cover': opts.cover_images[x], 
		'field_name': opts.name + img_nr,
		'field_name_cover': opts.name + img_nr + '_cover',  
		'img_name': opts.image_names[x], 
		'img_type': opts.image_types[x]
	};
}

gallery_field.row_type = function(opts) {
	gallery_field.debug && console.log('gallery_field.row_type', opts);
	if (opts && opts.img_type) {
		if (opts.img_type === 'video/mp4') {
			return 'vid';
		}
	}
	return 'img';
}

gallery_field.add_row = function($table, ix) {
	gallery_field.debug && console.log('gallery_field.add_row', $table, ix);
	gallery_field.update_row($table, ix, null);
}

gallery_field.update_row = function($table, ix, $row) {
	gallery_field.debug && console.log('gallery_field.update_row', $table, ix, $row);
	var opts = $table.data('opts');
	var row_ctx = gallery_field.data_context(opts, ix);
	var type = gallery_field.row_type(row_ctx);
	var tpl_id = opts.tpl_id + '_' + type;
	var row_html = nb_populate_template(tpl_id, row_ctx);
	if ($row === null) {
		$table.append(row_html);
	} else {
		$row.replaceWith(row_html);
	} 
	opts.ix[ix] = ix;
}

gallery_field.on_sortstop = function(e, ui) {
	$table = $(e.target);
	gallery_field.reorder($table, e.data.opts);
}

gallery_field.reorder = function($table) {
	$table.find('tr').each(function(ix) {
		var $row = $(this);
		var num = gallery_field.row_num($row);
		if (num !== (ix+1)) {
			var y = num-1;
			if (ix < y) {
				gallery_field.swap_data($table, ix, y);
			}
		}
	});
	gallery_field.refresh($table);
}

gallery_field.row_num = function($row) {
	return parseInt($row.find('td:first').text());
}

gallery_field.swap_data = function($table, x, y) {
	var opts = $table.data('opts');
	var t1 = opts.images[x];
	var t2 = opts.image_names[x];
	var t3 = opts.ix[x];
	opts.images[x] = opts.images[y];
	opts.image_names[x] = opts.image_names[y];
	opts.ix[x] = opts.ix[y];
	opts.images[y] = t1;
	opts.image_names[y] = t2;
	opts.ix[y] = t3;
	$table.data('opts', opts);
}

gallery_field.remove_img = function($table, uuid, $img) {
	gallery_field.debug && console.log('gallery_field.remove_img', $table, uuid, $img);
	$row = $table.find('tr:has(img[data-img-uuid=' + uuid + '])');
	if (!$row.length) {
		return;
	}
	if ($row.length > 1) {
		var grid_id = $img.data('edit-img');
		$row = $row.filter('tr:has(img[data-edit-img=' + grid_id + '])')
		if ($row.length !== 1) {
			gallery_field.debug && console.log('gallery_field.remove_img ERROR: too many rows matchig criteria', uuid, grid_id);
			return;
		}
	}
	var num = gallery_field.row_num($row);
	var opts = $table.data('opts');
	if (opts.cover_images[num - 1] === uuid & opts.images[num - 1] !== uuid) {
		return;
	}
	opts.images.splice(num - 1, 1);
	opts.cover_images.splice(num - 1, 1);
	opts.image_names.splice(num - 1, 1);
	opts.ix.splice(num - 1, 1);
	$table.data('opts', opts);
	$row.remove();
	gallery_field.refresh($table);
}

gallery_field.update_rows = function($table) {
	var opts = $table.data('opts');
	$table.find('tr').each(function(ix) {
		var $row = $(this);
		var num = gallery_field.row_num($row);
		if (num !== (ix+1) || opts.ix[ix] != ix) {
			gallery_field.update_row($table, ix, $row);
		}
	});
}

gallery_field.refresh = function($table) {
	gallery_field.update_rows($table);
	if (editor.enabled) {
		editor.disable();
		editor.enable();
	}
	nb_load_images();
	var opts = $table.data('opts');
	var is_max = opts.max && opts.images.length >= opts.max;
	$('#' + opts.name + '_upload button').attr('disabled', is_max);
}

gallery_field.move_up = function(e) {
	$row = $(e.target).closest('tr');
	$table = $row.closest('tbody');
	var num = gallery_field.row_num($row);
	if (num < 2) {
		return;
	}
	gallery_field.swap_data($table, num-1, num-2);
	e.preventDefault();
	gallery_field.refresh($table);
}

gallery_field.move_down = function(e) {
	$row = $(e.target).closest('tr');
	$table = $row.closest('tbody');
	var opts = $table.data('opts');
	var num = gallery_field.row_num($row);
	if (num >= opts.ix.length) {
		return;
	}
	gallery_field.swap_data($table, num-1, num);
	e.preventDefault();
	gallery_field.refresh($table);
}

gallery_field.add_data = function($table, img_uuid, img_name, img_type) {
	gallery_field.debug && console.log('gallery_field.add_data', img_uuid, img_name, img_type);
	var opts = $table.data('opts');
	var ix = $table.find('tr').length;
	opts.images[ix] = img_uuid;
	opts.cover_images[ix] = false;
	opts.image_names[ix] = img_name;
	opts.image_types[ix] = img_type;
	opts.ix[ix] = ix;
	$table.data('opts', opts);
	gallery_field.add_row($table, ix);
	gallery_field.refresh($table);
}

gallery_field.update_data = function(data) {
	gallery_field.debug && console.log('gallery_field.update_data', data);
	var uid = data.modal_uid;
	var $img = $('[data-edit-uuid=' + data.resource_uuid + '] [data-edit-img=' + uid + ']');
	var $row = $img.closest('tr');
	if ($row.length === 0) {
		return;
	}
	$table = $row.closest('table').find('tbody');
  	var opts = $table.data('opts');
  	var ix = parseInt($img.closest('tr').find('td:first').text()) - 1;
  	if (uid.includes('_cover')) {
		opts.cover_images[ix] = data.uuid;
	} else {
  		opts.images[ix] = data.uuid;
  		opts.image_names[ix] = data.name;
  	}
  	$table.data('opts', opts);
  	gallery_field.update_row($table, ix, $row);
  	gallery_field.refresh($table);
}

gallery_field.handle_upload = function(e, data) {
	gallery_field.debug && console.log('gallery_field.handle_upload', e, data);
	$uploader = $('#' + e.type);
	if (data.event === 'preview') {
		
	} else if (data.event === 'progress') {
		$uploader.find('div.progress-wrapper').removeClass('nb-close');
		$uploader.find('div.progress-bar').css('width', data.data.pct + '%');
		$uploader.find('div.progress-bar-text').text(data.data.msg);
	} else if (data.event === 'done') {
		$table = $uploader.closest('table').find('tbody');
		gallery_field.add_data($table, data.data.uuid, data.data.name, data.data.type);
		$uploader.find('button[data-upload]').prop('disabled', false);
	} else if (data.event === 'fail') {
		$uploader.find('button[data-upload]').prop('disabled', false);
	} else {
		console.log('event', data);
	}
}

gallery_field.handle_image_select = function(e, data) {
	gallery_field.debug && console.log('handle_image_select', e, data);
	if (!data.uuid || !data.name) {
		return;
	}
	$selectbtn = $('#' + data.uid);
	if ($selectbtn.length === 1) {
		$table = $selectbtn.closest('table').find('tbody');
		gallery_field.add_data($table, data.uuid, data.name);
	} else {
		gallery_field.update_data(data);
	}
}