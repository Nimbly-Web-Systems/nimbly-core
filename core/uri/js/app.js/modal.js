var modal = {};

modal.active = false;
modal.options = {};
modal.init_done = false;

modal.init = function() {
    if (modal.init_done === true) {
        return;
    }

    modal.init_done = true;

    $('body').on('click', '#modal [data-select] [data-uuid]', function () {
        var opts = modal.options;
        $me = $(this);
        opts.uuid = $me.data('uuid');
        opts.modal_uid = $('#modal').data('uid');
        opts.name = $me.data('name');
        opts.type = $me.data('type');
        modal.close();
        $(document).trigger('data-select', opts);
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

    $('body').on('click', 'a[data-close-modal],button[data-close-modal]', function(e) {
        e.preventDefault();
        if (modal.active) {
            modal.close();
        }
    });

    $('body').on('click', 'a[data-finalize-modal],button[data-finalize-modal]', function(e) {
        e.preventDefault();
        if (modal.active) {
            modal.finalize();
        }
    });

    $(document).on('modal_img_select', function (e, opts) {
        modal.handle_img_select(e, opts);
    });

}

modal.open = function(opts) {
    modal.init();
    if ($('#modal').length == 0) {
        modal.create();
    }
    $('#modal').data('uid', opts.uid);
    $('#modal').data('original-url', window.location.href);
    $('#modal-content').html('');
    $('#modal-content').load(base_url + '/.modal/' + opts.url, function() {
        opts.original_url = window.location.pathname.substr(1);
        $('#modal-content').html(nb_populate_template('modal-content', opts));    
    });
    $('#modal').removeClass('nb-close');
    modal.active = true;
    modal.options = opts;
    $(document).trigger('modal.open', opts);
};

modal.create = function() {
    $('body').append('<div id="modal" class="nb-close"><div id="modal-content"></div></div>');
};

modal.show = function(tpl_id, ctx) {
    modal.init();
    if ($('#modal').length == 0) {
        modal.create();
    }
    var html = nb_populate_template(tpl_id, ctx);
    $('#modal-content').html(html);
    $('#modal').removeClass('nb-close');
    modal.active = true;
    modal.options = {};
}


modal.close = function() {
    $('#modal').addClass('nb-close');
    $('#modal-content').html('');
    modal.active = false;
};

modal.finalize = function() {
    console.log('modal.finalize');
    var done = $(modal.options._elem).data('modal-done');
    if (done) {
        var $frm = $('#modal form');
        var json = $frm.serializeObject();
        json.modal_uid = $('#modal').data('uid');
        nb_do(done, json);
    }
    modal.close();
}

modal.handle_img_select = function (e, data) {
    if (data.event === 'preview') {
        modal.init_img_select_row(data.data);
    } else if (data.event === 'progress') {
        $urow = $('#modal table[data-select] tbody tr.file-upload-row:first');
        $urow.find('div.progress-wrapper').removeClass('nb-close');
        $urow.find('div.progress-bar').css('width', data.data.pct + '%');
        $urow.find('div.progress-bar-text').text(data.data.msg);
    } else if (data.event === 'done') {
        modal.handle_img_upload_done(data);
    } else if (data.event === 'fail') {
        modal.reset_img_select();
    } else {
        console.log('unknown event', data);
    }
}

modal.handle_img_upload_done = function(data) {
    var html = nb_populate_template('tpl-modal-img-select-row', data.data);
    $urow = $('#modal table[data-select] tbody tr.file-upload-row:first');
    $urow = $urow.replaceWith(html);
    $img = $('.file-upload-row .file-upload-row-img img');
    $img.attr('src', base_url + '/img/' + data.data.uuid + '/100x50f');
    $('.file-upload-row .file-upload-row-buttons .nb-close').removeClass('nb-close');
    modal.reset_img_select();
    var opts = modal.options;
    opts.uuid = data.data.uuid;
    opts.modal_uid = $('#modal').data('uid');
    opts.name = data.data.name;
    modal.close();
    $(document).trigger('data-select', opts);
}

modal.init_img_select_row = function(img_src) {
    $('#modal .nb-button[data-upload]').addClass('nb-button-disabled').prop('disabled', true);
    $table = $('#modal table[data-select] tbody');
    var ctx = {
        'uuid': '',
        'name': ''
    }
    var html = nb_populate_template('tpl-modal-img-select-row', ctx);
    $table.prepend(html);
    $urow = $table.find('tr.file-upload-row:first');
    $urow.find('img').attr('src', img_src);
}

modal.reset_img_select = function() {
    $('#modal .nb-button[data-upload]').removeClass('nb-button-disabled').prop('disabled', false);
    $table = $('#modal table[data-select] tbody');
    $urow = $table.find('tr.file-upload-row:first');
    $urow.removeClass('file-upload-row');
}

$('body').on('click', '[data-modal]', function(e) {
    var opts = $(this).data('modal');
    opts._elem = $(this);
    modal.open(opts);
    e.stopPropagation();
    return false;
});