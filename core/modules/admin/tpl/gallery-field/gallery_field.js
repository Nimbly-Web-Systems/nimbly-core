var gallery_field = {
	debug: false
};

gallery_field.init = function(opts) {
	gallery_field.debug && console.log('gallery_field.init', opts);
	opts.tpl_id = 'tpl_gallery_' + opts.uuid + '_row';
	opts.ix = [];
	var $table = $('#gallery_' + opts.uuid + ' tbody' + '.nb-sortable');
	$table.data('opts', opts);
	for (ix in opts.media_uuids) {
		gallery_field.add_row($table, parseInt(ix))
	}
	$table.sortable();
	$table.on('sortstop', { 'opts' : opts }, gallery_field.on_sortstop);
	$table.on('click', 'a[data-move-up]', gallery_field.move_up);
	$table.on('click', 'a[data-move-down]', gallery_field.move_down);
	$table.on('click', 'a[data-delete-row]', gallery_field.delete_row);
	gallery_field.refresh($table);
	$(document).on(opts.name + '_upload', gallery_field.handle_upload);
	$(document).on('data-select', gallery_field.handle_image_select);
	$(document).on('modal_vimeo_id', gallery_field.handle_vimeo_id);
}

gallery_field.data_context = function(opts, x) {
	gallery_field.debug && console.log('gallery_field.data_context', opts, x);
	var media_nr = parseInt(x) + 1;
	return {
		'media_nr': media_nr, 
		'media_uuid': opts.media_uuids[x], 
		'media_uuid_cover': opts.cover_images[x], 
		'field_name': opts.name + media_nr,
		'field_name_cover': opts.name + media_nr + '_cover',  
		'media_name': opts.media_names[x], 
		'media_type': opts.media_types[x]
	};
}

gallery_field.row_type = function(opts) {
	gallery_field.debug && console.log('gallery_field.row_type', opts);
	if (!opts.media_type) {
		return console.log('no media type set', opts);
	}
	switch (opts.media_type) {
		case 'image/jpeg':
		case 'image/png':
			return 'img';
		case 'video/mp4':
			return 'vid';
		case 'vimeo':
			return 'vimeo';
		default:
			console.log('unhandled media type', opts.media_type);
			return 'unknown';
	}
}

gallery_field.add_row = function($table, ix) {
	gallery_field.debug && console.log('gallery_field.add_row', $table, ix);
	gallery_field.update_row($table, ix, null);
}

gallery_field.set_closure = function($table) {
	gallery_field.debug && console.log('gallery_field.set_closure', $table);
	var opts = $table.data('opts');
	$('#' + opts.name + '_closure').attr('name', opts.name + (opts.media_uuids.length + 1));
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
	gallery_field.debug && console.log('gallery_field.reorder', $table);
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
	gallery_field.debug && console.log('gallery_field.swap_data', x, y);
	var opts = $table.data('opts');
	var t_ix = opts.ix[x];
	var t_img = opts.media_uuids[x];
	var t_cover_img = opts.cover_images[x];
	var t_media_name = opts.media_names[x];
	var t_media_type = opts.media_types[x];
	opts.ix[x] = opts.ix[y];
	opts.media_uuids[x] = opts.media_uuids[y];
	opts.cover_images[x] = opts.cover_images[y];
	opts.media_names[x] = opts.media_names[y];
	opts.media_types[x] = opts.media_types[y];
	opts.ix[y] = t_ix;
	opts.media_uuids[y] = t_img;
	opts.cover_images[y] = t_cover_img;
	opts.media_names[y] = t_media_name;
	opts.media_types[y] = t_media_type;
	$table.data('opts', opts);
}

gallery_field.delete_row = function(e) {
	gallery_field.debug && console.log('gallery_field.delete_row', e);
	$row = $(e.target).closest('tr');
	if ($row.length !== 1) {
		return;
	}

	$table = $row.closest('tbody');
	var num = gallery_field.row_num($row);
	var opts = $table.data('opts');
	if (confirm('Are you sure you want to remove ' + opts.media_names[num-1] + '?') !== true) {
		return;
	}
	opts.media_uuids.splice(num - 1, 1);
	opts.cover_images.splice(num - 1, 1);
	opts.media_names.splice(num - 1, 1);
	opts.media_types.splice(num - 1, 1);
	opts.ix.splice(num - 1, 1);
	$table.data('opts', opts);
	$row.remove();
	e.preventDefault();
	gallery_field.refresh($table);
}

gallery_field.update_rows = function($table) {
	gallery_field.debug && console.log('gallery_field.update_rows', $table);
	var opts = $table.data('opts');
	$table.find('tr').each(function(ix) {
		var $row = $(this);
		var num = gallery_field.row_num($row);
		if (num !== (ix+1) || opts.ix[ix] != ix) {
			gallery_field.update_row($table, ix, $row);
		}
	});
	gallery_field.set_closure($table);
}

gallery_field.refresh = function($table) {
	gallery_field.debug && console.log('gallery_field.refresh', $table);
	gallery_field.update_rows($table);
	if (editor.enabled) {
		editor.disable();
		editor.enable();
	}
	nb_load_images();
	var opts = $table.data('opts');
	var is_max = opts.max && opts.media_uuids.length >= opts.max;
	$('#' + opts.name + '_upload button').attr('disabled', is_max);
}

gallery_field.move_up = function(e) {
	gallery_field.debug && console.log('gallery_field.move_up', e);
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
	gallery_field.debug && console.log('gallery_field.move_down', e);
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

gallery_field.add_data = function($table, media_uuid, media_name, media_type) {
	gallery_field.debug && console.log('gallery_field.add_data', media_uuid, media_name, media_type);
	var opts = $table.data('opts');
	var ix = $table.find('tr').length;
	opts.media_uuids[ix] = media_uuid;
	opts.cover_images[ix] = false;
	opts.media_names[ix] = media_name;
	opts.media_types[ix] = media_type;
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
		nb_load_images();
		return;
	}
	$table = $row.closest('table').find('tbody');
  	var opts = $table.data('opts');
  	var ix = parseInt($img.closest('tr').find('td:first').text()) - 1;
  	if (uid.includes('_cover')) {
		opts.cover_images[ix] = data.uuid;
	} else {
  		opts.media_uuids[ix] = data.uuid;
  		opts.media_names[ix] = data.name;
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
	if (!data.uuid || !data.name || data.uid === '(new)') {
		return;
	}
	$selectbtn = $('#' + data.uid);
	if ($selectbtn.length === 1) {
		$table = $selectbtn.closest('table').find('tbody');
		gallery_field.add_data($table, data.uuid, data.name, data.type);
	} else {
		gallery_field.update_data(data);
	}
}

gallery_field.handle_vimeo_id = function(e, data) {
	gallery_field.debug && console.log('handle_vimeo_id', e, data);
	var ids = data.value? data.value.match(/(\d+)/) : false;
	if (!ids || !ids[0]) {
		return system_message("Invalid Vimeo ID. Please enter a valid ID or URL.");
	} 
	$selectbtn = $('#' + data.modal_uid);
	if ($selectbtn.length !== 1) {
		return system_message("Gallery id not set or not recognized.");
	}
	var vimeo_id = ids[0];
	$table = $selectbtn.closest('table').find('tbody');
	gallery_field.add_data($table, vimeo_id, 'vimeo ' + vimeo_id, 'vimeo');


}