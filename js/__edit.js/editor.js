var editor = {
    enabled: false,
    inputs: 0,
    last_inputs: 0,
    editors: [],
    timer: null,
    active: null,
    modal_uuid: null,
    has_custom_save: false,
    empty_img: 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==',
    debug: false
};

editor.init = function() {
    editor.debug && console.log('editor.init');
    if ($('[data-edit-field],[data-edit-img],[data-edit-wysiwyg]').length < 1) {
        editor.debug && console.log('editor.init - no inline editing fields');
        return;
    }
    $('body').on('click', '#edit-button', editor.on_edit_button);
    $('body').on('click', '#edit-button-save', editor.on_edit_button_save);
    $('body').on('click', '#edit-button-media', editor.on_edit_button_media);
    $('body').on('click', '#edit-button-vimeo', editor.on_edit_button_vimeo);
    $('body').on('DOMNodeInserted', '[data-edit-field]', editor.clean_node);
    $('body').on('click', 'a[data-clear-img]', editor.on_clear_img);
    $('body').on('input', '[data-edit-field]', editor.on_input);
    $('body').on('keydown', '[data-edit-field][data-edit-tpl=plain_text]', editor.on_keydown_plain_text);
    $('body').on('click', '[data-edit-field]', editor.on_click);
    $(window).bind('beforeunload', editor.on_beforeunload);

    // load medium editor style sheet
    $('head').append('<link>');
    var css = $('head').children(':last');
    css.attr({
        rel: 'stylesheet',
        type: 'text/css',
        href: 'https://cdnjs.cloudflare.com/ajax/libs/medium-editor/5.23.0/css/medium-editor.min.css'
    });

    // load medium editor script
    $script('https://cdnjs.cloudflare.com/ajax/libs/medium-editor/5.23.0/js/medium-editor.min.js', 'medium');

    // continue init after medium script is ready
    $script.ready('medium', function() {
        editor._init_more();
    });
}

editor._init_more = function() {
    editor.debug && console.log('editor._init_more');
    if ($('form[data-edit-autoenable]').length > 0) {
        editor.has_custom_save = true;
        editor.enable();
        return;
    }
    editor.has_custom_save = false;
    $('#edit-button').removeClass('nb-close');
}

editor.allow_click = function(e) {
    editor.debug && console.log('editor.allow_click');
    e.preventDefault();
    if ($(this).hasClass('nb-disabled') || $(this).prop('disabled')) {
        return false;
    }
    if ($(this).data('confirm') !== undefined && confirm($(this).data('confirm')) !== true) {
        return false;
    }
    return true;
}

editor.on_edit_button = function(e) {
    editor.debug && console.log('editor.on_edit_button', e);
    if (!editor.allow_click(e)) {
        return false;
    }
    editor.toggle();
}

editor.on_edit_button_save = function(e) {
    editor.debug && console.log('editor.on_edit_button_save');
    if (!editor.allow_click(e)) {
        return false;
    }
    editor.save();
}

editor.on_edit_button_media = function(e) {
    editor.debug && console.log('editor.on_edit_button_media');
    if (!editor.allow_click(e)) {
        return false;
    }
    editor.insert_media();
}

editor.on_edit_button_vimeo = function(e) {
    editor.debug && console.log('editor.on_edit_button_vimeo');
    if (!editor.allow_click(e)) {
        return false;
    }
    editor.insert_vimeo();
}

editor.on_clear_img = function(e) {
    editor.debug && console.log('editor.on_clear_img');
    if (!editor.allow_click(e)) {
        return false;
    }
    var $img = $(this).siblings('img:first');
    var uuid = $img.data('img-uuid');
    $img.data('edit-changed', true);
    $img.data('img-uuid', '');
    $img.attr('src', base_url + '/img/placeholder.png');
    $img.data('empty', true);
    $(this).addClass('nb-close');
    $(document).trigger('clear-img', { uuid: uuid, img: $img });
}

editor.on_input = function(e) {
    if (!editor.enabled) {
        return;
    }
    editor.inputs++;
    $('#edit-button-save').removeClass('nb-disabled');
    $(this).data('edit-changed', true);
};

editor.on_keydown_plain_text = function(e) {
    if (!editor.enabled) {
        return;
    }
    if (e.keyCode === 13) {
        // prevents enter
        return false;
    } else if ((e.keyCode === 66 || e.keyCode === 73) && (e.ctrlKey || e.metaKey)) {
        // prevents ctrl+b and ctrl+i
        return false;
    }
}

editor.on_click = function(e) {
  editor.debug && console.log('editor.handle_click');
  editor.set_active(e);
};

editor.on_beforeunload = function(e) {
    if (!editor.has_changes() || editor.has_custom_save) {
        return undefined;
    }
    var msg = 'You have unsaved changed. Are you sure you want to leave this page and discard your changes?';
    (e || window.event).returnValue = msg; 
    return msg;
};

editor.has_changes = function() {
    return editor.inputs > editor.last_inputs;
}

editor.scope = function(e) {
    if (e && e.target) {
        $tgt = $(e.target);
        $parent = $tgt.closest('[data-edit-uuid]');
        if ($parent && $parent.attr('data-edit-scope') !== undefined) {
            return $parent;
        }
    }
    if (e && typeof e === 'string') {
        return e;
    } 
    return false;
}

editor.enable = function(e) {
    editor.debug && console.log('editor.enable');
    if (editor.enabled) {
        return;
    }
    editor.enabled = true;
    var $scope = editor.scope(e);
    $('#edit-button-media, #edit-button-vimeo').removeClass('nb-close').addClass('nb-disabled');
    if (editor.has_custom_save !== true) {
        $('#edit-button-save').removeClass('nb-close');
    }
    if (!editor.has_changes()) {
        $('#edit-button-save').addClass('nb-disabled');
    }
    if (editor.editors.length === 0) {
        $('[data-edit-field]').each(function(ix) {
          btns = $(this).data('edit-buttons');

          if (!editor._in_scope($scope, this)) {
            return;
          }
          $(this).attr('contenteditable', true);
          if (!btns) {
            return;
          }
          btns = btns.split(',');
          var e = new MediumEditor($(this), {
            toolbar: {
              buttons: btns,
            },
            placeholder: false,
          });
          editor.editors.push(e);
        });
    }
    $('[data-edit-img]').each(function(ix) {
        if (!editor._in_scope($scope, this)) {
            return;
        }
        editor.enable_img($(this), ix);
    });
    $(document).trigger('editor', { enabled: true, scope: $scope });
};

editor.disable = function() {
    editor.debug && console.log('editor.disable');
    if (editor.enabled === false) {
        return;
    }
    editor.enabled = false;
    for (var ix in editor.editors) {
        var e = editor.editors[ix];
        delete e;
        e = null;
    }
    $('[data-edit-img]').each(function(ix) {
        editor.disable_img($(this), ix);
    });
    editor.editors = [];
    editor.active = null;
    $('.editor-active').removeClass('editor-active');
    $('[data-edit-field]').attr('contenteditable', false);
    if (editor.timer) {
        clearInterval(editor.timer);
    }
    $('#edit-button-media, #edit-button-vimeo').addClass('nb-disabled').prop('disabled', true);
    $(document).trigger('editor', { enabled: false });
};

editor.toggle = function(e) {
    editor.debug && console.log('editor.toggle', e);
    if (editor.enabled) {
        editor.disable();
    } else {
        editor.enable(e);
    }
};

editor.enable_media_buttons = function(enabled) {
    editor.debug && console.log('editor.enable_media_button', enabled);
    var $btns = $("#edit-button-media, #edit-button-vimeo");
    if (enabled) {
        $btns.prop('disabled', false); 
        $btns.removeClass('nb-disabled');
        $btns.removeClass('nb-close');
    } else {
        $btns.prop('disabled', true); 
        $btns.addClass('nb-disabled');
    }
}

editor.save = function() {
    editor.debug && console.log('editor.save');
    $('#edit-button-save').addClass('nb-disabled');
    $('#edit-button-media, #edit-button-vimeo').addClass('nb-disabled').prop('disabled', true);
    if (!editor.has_changes()) {
        system_message('Saved');
        return;
    }
    editor.last_inputs = editor.inputs;
    $('[data-edit-uuid]').each(function(ix) {
        editor.save_resource($(this));
    });
    editor.active = null;
};

editor.save_resource = function($r) {
    var resource = $r.data('edit-resource');
    if (!resource) {
        return;
    }
    var uuid = $r.data('edit-uuid');
    if (!uuid) {
        return;
    }
    var changes = false;
    var payload = {};
    $r.find('[data-edit-field],[data-edit-img]').each(function(ix) {
        if (!$(this).data('edit-changed')) {
            return;
        }
        var $parent = $(this).closest('[data-edit-uuid]');
        if ($parent.data('edit-uuid') != uuid) {
            return;
        }
        changes = true;
        $(this).data('edit-changed', false);
        var is_img = false;
        var field = $(this).data('edit-field');
        var tpl = $(this).data('edit-tpl');
        if (!field && $(this).data('edit-img')) {
            is_img = true;
            field = $(this).data('edit-img');
        } else if (!field) {
            field = 'block-' + ix;
        }
        if (is_img) {
            payload[field] = $(this).data('img-uuid');
        } else if (tpl === 'plain_text') {
            payload[field] = $(this).text();
            if ($(this).html() !== payload[field]) {
                $(this).html(payload[field]); //note: cleans up html but also reset cursor position to start
            }
        } else {
            $('[data-remove]', this).remove();
            $("[style='']", this).removeAttr('style');
            $('img[data-img-uuid]', this).attr('src', editor.empty_img).removeClass('nb-img-loaded');
            payload[field] = $(this).html();
            nb_load_images();
        }
    });

    if (!changes) {
        return system_message('Saved');
    }

    api({
        method: 'put',
        url: resource + '/' + uuid,
        payload: JSON.stringify(payload),
        done: { notification: 'Saved' },
    });
    
    $(document).trigger('editor', { saved: true });
}

editor._in_scope = function($scope, elem) {
  editor.debug && console.log('editor._in_scope', $scope, elem);
  if (!$scope) {
    return true;
  }
  var $elem_scope = $(elem).closest('[data-edit-uuid]');
  if (!$elem_scope) {
    return false;
  }
  if (typeof $scope === 'string') {
    return $elem_scope.closest($scope).length === 1;
  }
  return $scope[0] === $elem_scope[0];
};




// workaround chrome span / inline style bug
editor.clean_node = function(e) {
  editor.debug && console.log('editor.clean_node');
  if (editor.enabled === false) {
    return;
  }
  if (e.target.tagName === 'SPAN') {
    e.target.outerHTML = e.target.innerHTML;
    return;
  }
  e.target.style = null;
};

editor.enable_img = function(elem, ix) {
    editor.debug && console.log('editor.enable_img');
    if (elem.parent().is('div.editor.img-wrapper')) {
        elem.unwrap();
    }
    elem.wrap('<div class="editor img-wrapper"></div>');
    var resource_uuid = elem.closest('[data-edit-uuid]').data('edit-uuid');
    var img_uuid = elem.data('edit-img');
    var is_empty = elem.data('empty');
    if (is_empty) {
        elem.parent().append('<a href="#" class="clear-img-icon nb-button icon-button delete nb-close" data-clear-img data-confirm="Press OK to delete image."></a>');
  } else {
    elem.parent().append('<a href="#" class="clear-img-icon nb-button icon-button delete" data-clear-img data-confirm="Press OK to delete image."></a>');
  }

  elem.parent().append('<a href="#" class="edit-img-icon nb-button icon-button edit" data-modal=\'{"url": "img-select", "uid": "' +
        img_uuid + '", "resource_uuid": "' + resource_uuid + '"}\'></a>'
    );
};

editor.disable_img = function(elem, ix) {
    editor.debug && console.log('editor.disable_img');
    elem.parent().find('a.edit-img-icon,a.clear-img-icon').remove();
    if (elem.parent().is('div.editor.img-wrapper')) {
        elem.unwrap();
    }
};

editor.uuid = function() {
  function id4() {
    return Math.floor((1 + Math.random()) * 0x10000)
      .toString(16)
      .substring(1);
  }
  return id4() + id4() + id4() + id4();
};

editor.insert_media = function() {
    editor.debug && console.log('editor.insert_media');
    modal.open({url: 'img-select', uid: '(new)'});
}

editor.insert_vimeo = function() {
    editor.debug && console.log('editor.insert_vimeo');
    modal.open({
        url: 'get-value', 
        uid: 'insert_vimeo', 
        title:  'Enter Vimeo ID', 
        done: { trigger: 'vimeo-insert'} 
    });
}

editor.insert_html = function(html) {
  editor.debug && console.log('editor.insert_html', html);
  if (window.getSelection) {
    var sel = window.getSelection();
    if (sel.getRangeAt && sel.rangeCount) {
      var range = sel.getRangeAt(0);
      range.deleteContents();
      var el = document.createElement('div');
      el.innerHTML = html;
      var frag = document.createDocumentFragment();
      var node;
      var lastNode = false;
      while ((node = el.firstChild)) {
        lastNode = frag.appendChild(node);
      }
      range.insertNode(frag);

      // Preserve the selection
      if (lastNode) {
        range = range.cloneRange();
        range.setStartAfter(lastNode);
        range.collapse(true);
        sel.removeAllRanges();
        sel.addRange(range);
      }
    }
  } else if (document.selection && document.selection.type != 'Control') {
    document.selection.createRange().pasteHTML(html);
  }
};

editor.replace_html = function(elem_id, html) {
    editor.debug && console.log('editor.replace_html', elem_id, html);
    if (!elem_id) {
        return;
    }
    $('#' + elem_id).replaceWith(html);
};

editor.insert_modal_html = function(html) {
    if (!editor.modal_uuid || !html) {
        return;
    }
    editor.replace_html(editor.modal_uuid, html);
    editor.modal_uuid = false;
    editor.active.find('[data-edit-field]:first').data('edit-changed', true);
    editor.inputs++;
    nb_load_images();
    $('#edit-button-save').removeClass('nb-disabled');
}

editor.set_active = function(e) {
    editor.debug && console.log('editor.set_active');
    $me = $(e.target);
    var wrapper = $me.closest('div.editor,span.editor,[data-edit-uuid]');
    if (wrapper && wrapper.is(editor.active)) {
        return;
    }
    $('.editor-active').removeClass('editor-active');
    editor.active = wrapper;
    wrapper.addClass('editor-active');
    img_insert = wrapper.hasClass('img-insert');
    editor.enable_media_buttons(img_insert);
}

// handle result from image select modal dialog
$(document).on('data-select', function(e, o) {
    editor.debug && console.log('editor.data-select', e, o);
  
    if (editor.enabled === false) {
        return;
    }

    // create new image at caret position
    if (o.modal_uid === '(new)' && editor.active) {
        var img_html = '<img src="' + editor.empty_img + '" data-img-uuid=' + o.uuid + '>';
        editor.insert_modal_html(img_html);
        return;
    }

    // update existing image
    var $img = $('[data-edit-uuid=' + o.resource_uuid + '] img[data-edit-img=' + o.modal_uid + ']');
    if ($img) {
        $img.attr('src', base_url + '/img/' + o.uuid + '/medium');
        $img.data('edit-changed', true);
        $img.data('img-uuid', o.uuid);
        $img.attr('data-img-uuid', o.uuid);
        $img.data('empty', false);
        $img.siblings('.clear-img-icon').removeClass('nb-close');
        editor.inputs++;
        $('#edit-button-save').removeClass('nb-disabled');
    }
});

// handle result from vimeo modal dialog
// todo: make this external media insert
$(document).on('vimeo-insert', function(e, o) {
    editor.debug && console.log('editor.vimeo-insert', e, o);
    if (editor.enabled === false) {
        return;
    }
    if (o.value.indexOf('//youtu.be/') > 0 || o.value.indexOf('youtube.com/') > 0) {
        editor.insert_youtube_html(o.value);  
        return;
    }
    
    editor.insert_vimeo_html(o.value);
});

editor.insert_vimeo_html = function(url) {
    var ids = url? url.match(/(\d+)/) : false;
    if (!ids) {
        return system_message("Invalid Vimeo ID. Please enter a valid ID or URL.");
    }
    var vimeo_html = '<iframe src="https://player.vimeo.com/video/' + ids.pop() + '" width="100%" height="360px" frameborder="0" allow="autoplay; fullscreen" allowfullscreen></iframe>';
    editor.insert_modal_html(vimeo_html);
}

editor.insert_youtube_html = function(url) {
    var ids = url.match(/(\?|&)v=([^&#]+)/) || url.match(/(\.be\/)+([^\/]+)/) || url.match(/(\embed\/)+([^\/]+)/);
    if (!ids) {
        return system_message("Invalid Youtube ID. Please enter a valid ID or URL.");
    }
    var youtube_html = '<iframe width="100%" height="360px" src="https://www.youtube.com/embed/' + ids.pop() + '" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
    editor.insert_modal_html(youtube_html);
}

// handle opening modal image select dialog
$(document).on('modal.open', function(e, o) {
    editor.debug && console.log('editor.modal.open', e, o);
    if (editor.enabled === false) {
        return;
    }
    if (o.url !== 'img-select' && o.url !== 'get-value') {
        return;
    }
    if (o.url === 'img-select' && o.uid !== '(new)') {
        return;
    }
    if (o.url ===  'get-value' && o.uid !== 'insert_vimeo') {
        return;
    }
    editor.modal_uuid = editor.uuid();
    editor.insert_html(
        '<a id="' + editor.modal_uuid + '" data-remove="true"></a>'
    );
});



editor.init();