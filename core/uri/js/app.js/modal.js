var modal = {};

modal.active = false;
modal.options = {};

modal.open = function(opts) {
    if ($('#modal').length == 0) {
        modal.create();
    }
    $('#modal').data('uid', opts.uid);
    $('#modal-content').load(base_url + '/.modal/' + opts.url);
    $('#modal').removeClass('nb-close');
    modal.active = true;
    modal.options = opts;
    $(document).trigger('modal.open', opts);
};

modal.create = function() {
    $('body').append('<div id="modal" class="nb-close"><div id="modal-content"></div></div>');
};

modal.close = function() {
    $('#modal').addClass('nb-close');
    $('#modal-content').html('');
    modal.active = false;
};

$('body').on('click', '[data-modal]', function(e) {
    var opts = $(this).data('modal');
    modal.open(opts);
    e.stopPropagation();
    return false;
});

// handle modal select (single)
$('body').on('click', '#modal [data-select] [data-uuid]', function () {
    modal.handle_select($(this));
});

$('body').on('click', '#modal', function(e) {
    if (modal.active && $(e.target).attr('id') === 'modal') {
        modal.close();
    }
});

$('body').on('keyup', function(e) {
     if (modal.active && e.keyCode == 27) { // esc
        modal.close();
    }
});

// handle modal autoselect
$('body').on('autoselect', '#modal [data-select] [data-uuid]', function (e, opts) {
    modal.handle_select($(e.target));
});

modal.handle_select = function (elem) {
    var uuid = elem.data('uuid');
    var p = elem.closest('[data-select]');
    var old_uuid = p.data('select');
    p.find("[data-uuid='" + old_uuid + "']").removeClass('selected');
    p.data('select', uuid);
    p.attr('data-select', uuid);
    elem.addClass('selected');
    var opts = modal.options;
    opts.uuid = uuid;
    opts.modal_uid = $('#modal').data('uid');
    opts.prev = old_uuid;
    modal.close();
    $(document).trigger('data-select', opts);
}
