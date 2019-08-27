var order={};

order.init = function(opts) {
	console.log('order');
	opts = opts || {};
	$('tbody').sortable({
		cursor: "move"
	});
	$('tbody').on('sortstop', { 'opts' : opts }, order.on_sortstop);
	order.resource = $('table[data-resource]').data('resource');
	order.reorder();
}

// add order value to columns where it is empty
order.reorder = function() {
	$th = $('th[data-order]');
	var col = $th.index();
	if (col < 0) {
		console.log('order column not found');
	}
	$('tbody tr').each(function(ix, val) {
		var $col = $(this).find('td:nth-child(' + (col + 1) + ')');
		var txt = $col.text().trim().toLowerCase();
		if (txt != (ix+1)) {
			order.update_col($col, ix + 1);
		}
	});
}

order.update_col = function($col, ix) {
	$row = $col.parent();
	var uuid = $row.data('uuid');
	if (!uuid || !order.resource) {
		console.log('update_col: resource and/or uuid not set');
		return;
	}
	api({
        "url": order.resource + '/' + uuid,
        "method": "put",
        "payload": '{ "order": ' + '"' + ix + '"}'
    });
	$col.text(ix);
}

order.on_sortstop = function(e, ui) {
	order.reorder();
}

