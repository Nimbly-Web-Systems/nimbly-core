var editor = {};

editor.enabled = false;
editor.inputs = 0;
editor.last_inputs = 0;
editor.editors = [];
editor.timer = null;
editor.active = null;
editor.autoenabled = false;
editor.modal_uuid = null;
editor.autosave = false;
editor.init_done = false;

editor.enable = function(e) {
  if (editor.init_done === false) {
    editor.init();
  }
  if (editor.enabled === true) {
    return;
  }
  var $scope = false;
  if (e) {
    $tgt = $(e.target);
    $parent = $tgt.closest('[data-edit-uuid]');
    if ($parent && $parent.attr('data-edit-scope') !== undefined) {
      $scope = $parent;
    }
  }
  editor.enabled = true;
  $('#edit-button-save').removeClass('nb-close');
  if (editor.inputs === editor.last_inputs) {
    $('#edit-button-save').addClass('nb-disabled');
  }
  $('#edit-button[data-edit-toggle] a').addClass('active');
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
  if (editor.autosave === true) {
    editor.timer = setInterval(editor.save, 10000);
  }
  $(document).trigger('editor', { enabled: true, scope: $scope });
};

editor.disable = function() {
  if (editor.enabled === false) {
    return;
  }
  editor.enabled = false;
  for (var e in editor.editors) {
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
  $('#edit-button[data-edit-toggle] a').removeClass('active');
  if (editor.timer) {
    clearInterval(editor.timer);
  }
  $(document).trigger('editor', { enabled: false });
};

editor.toggle = function(e) {
  if (editor.enabled) {
    editor.disable();
  } else {
    editor.enable(e);
  }
};

editor.save = function() {
  $('#edit-button-save').addClass('nb-disabled');
  if (editor.inputs === editor.last_inputs) {
    system_message('Saved');
    return;
  }
  editor.last_inputs = editor.inputs;
  $('[data-edit-uuid]').each(function(ix) {
    var resource = $(this).data('edit-resource');
    if (!resource) {
      return true;
    }
    var uuid = $(this).data('edit-uuid');
    if (!uuid) {
      return true;
    }
    var changes = false;
    var payload = {};
    $(this)
      .find('[data-edit-field],[data-edit-img]')
      .each(function(ix) {
        var changed = $(this).data('edit-changed');
        if (!changed) {
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
        } else {
          // clean up
          $('[data-remove]', this).remove();
          $("[style='']", this).removeAttr('style');
          $('img[data-img-uuid]', this)
            .attr('src', 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==')
            .removeClass('nb-img-loaded');
          if (tpl === 'plain_text') {
            payload[field] = $(this).text();
            if ($(this).html() !== payload[field]) {
              $(this).html(payload[field]); //note: cleans up html but also reset cursor position to start
            }
          } else {
            payload[field] = $(this).html();
          }
        }
    });
    if (changes) {
      api({
        method: 'put',
        url: resource + '/' + uuid,
        payload: JSON.stringify(payload),
        done: { notification: 'Saved' },
      });
      $(document).trigger('editor', { saved: true });
    } else {
      system_message('Saved');
    }
  });
};

editor._in_scope = function($scope, elem) {
  if (!$scope) {
    return true;
  }
  var $elem_scope = $(elem).closest('[data-edit-uuid]');
  if (!$elem_scope) {
    return false;
  }
  return $scope[0] === $elem_scope[0];
};


editor.init = function() {
  if (editor.init_done === true) {
    return;
  }
  if ($('[data-edit-field]').length < 1 
    && $('[data-edit-img]').length < 1  
    && $('[data-edit-wysiwyg]').length < 1) {
    return;
  }

  editor.init_done = true;
  
  // load medium editor style sheet
  $('head').append('<link>');
  var css = $('head').children(':last');
  css.attr({
    rel: 'stylesheet',
    type: 'text/css',
    href:
      'https://cdnjs.cloudflare.com/ajax/libs/medium-editor/5.23.0/css/medium-editor.min.css',
  });

  // load medium editor script
  $script(
    'https://cdnjs.cloudflare.com/ajax/libs/medium-editor/5.23.0/js/medium-editor.min.js',
    'medium'
  );

  // initialize html editing
  $script.ready('medium', function() {
    $('body').on('click', '#edit-button[data-edit-toggle]', function(e) {
      e.preventDefault();
      editor.toggle(e);
    });

    $('body').on('click', '#edit-button-save', function(e) {
      e.preventDefault();
      if ($(this).hasClass('nb-disabled')) {
        return;
      }
      editor.save();
    });

    $('body').on('mousemove', '.editor.editor-active.img-insert .medium-editor-element', function(e) {
      editor.handle_mousemove(e);
    });

    $('body').on('DOMNodeInserted', '[data-edit-field]', editor.clean_node);

    $('body').on('click', 'a[data-clear-img]', function(e) {
      e.preventDefault();
      var me = $(this);
      if (me.data('confirm') !== undefined && confirm(me.data('confirm')) !== true) {
        return;
      }
      var $img = $(this).siblings('img:first');
      var uuid = $img.data('img-uuid');
      $img.data('edit-changed', true);
      $img.data('img-uuid', '');
      $img.attr('src', base_url + '/img/placeholder.png');
      $img.data('empty', true);
      $(this).addClass('nb-close');
      $(document).trigger('clear-img', { uuid: uuid, img: $img });
    });

    if ($('form[data-edit-autoenable]').length > 0) {
      editor.autoenabled = true;
      editor.enable();
    } else {
      $('#edit-button').removeClass('nb-close');
      $('#edit-menu').removeClass('nb-close');
      $('#edit-button-save').addClass('nb-close');
    }

    $('[data-edit-wysiwyg]').each(function() {
      var btns = $(this).data('edit-buttons');
      var placeholder = $(this).attr('placeholder') || false;
      if (!btns) {
        btns = 'bold,italic,anchor';
      }
      btns = btns.split(',');
      var e = new MediumEditor($(this), {
        toolbar: {
          buttons: btns,
        },
        placeholder: placeholder,
      });
    });
  });

  $('body').on('input', '[data-edit-field]', function(e) {
    if (editor.enabled) {
      editor.inputs++;
      $('#edit-button-save').removeClass('nb-disabled');
      $(this).data('edit-changed', true);
    }
  });

  $('body').on(
    'keydown',
    '[data-edit-field][data-edit-tpl=plain_text]',
    function(e) {
      if (!editor.enabled) {
        return;
      }
      if (e.keyCode === 13) {
        // prevents enter
        return false;
      } else if (
        (e.keyCode === 66 || e.keyCode === 73) &&
        (e.ctrlKey || e.metaKey)
      ) {
        // prevents ctrl+b and ctrl+i
        return false;
      }
    }
  );

  $(window).bind('beforeunload', function(e) {
    if (editor.inputs === editor.last_inputs) {
      return undefined;
    }
    var msg = 'You have unsaved changed. Are you sure you want to leave this page and discard your changes?';
    (e || window.event).returnValue = msg; 
    return msg;
  });
}

editor.handle_mousemove = function(e) {
    if (editor.enabled === false) {
        return;
    }
    $btn = $(e.target).closest('.editor.editor-active.img-insert').find('a.editor.add-img-icon:first');
    if ($btn.length === 0) {
        return;
    }
    editor.move_img_button($btn, e.pageY - $(e.currentTarget).offset().top);  
};

editor.move_img_button = function($btn, relative_y) {
    var y = relative_y + $btn.parent().offset().top - $btn.height(); 
    $btn.offset({ top: y });
}

// workaround chrome span / inline style bug
editor.clean_node = function(e) {
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
  if (elem.parent().is('div.editor.img-wrapper')) {
    elem.unwrap();
  }
  elem.wrap('<div class="editor img-wrapper"></div>');
  var resource_uuid = elem.closest('[data-edit-uuid]').data('edit-uuid');
  var img_uuid = elem.data('edit-img');
  var is_empty = elem.data('empty');
  if (is_empty) {
    elem
      .parent()
      .append(
        '<a href="#" class="clear-img-icon nb-button icon-button delete nb-close" data-clear-img data-confirm="Press OK to delete image."></a>'
      );
  } else {
    elem
      .parent()
      .append(
        '<a href="#" class="clear-img-icon nb-button icon-button delete" data-clear-img data-confirm="Press OK to delete image."></a>'
      );
  }

  elem
    .parent()
    .append(
      '<a href="#" class="edit-img-icon nb-button icon-button edit" data-modal=\'{"url": "img-select", "uid": "' +
        img_uuid +
        '", "resource_uuid": "' +
        resource_uuid +
        '"}\'></a>'
    );
};

editor.disable_img = function(elem, ix) {
  elem
    .parent()
    .find('a.edit-img-icon,a.clear-img-icon')
    .remove();
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

editor.insert_html = function(html) {
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
  if (!elem_id) {
    return;
  }
  $('#' + elem_id).replaceWith(html);
};

editor.handle_click = function(elem, event) {
  var wrapper = $(elem).closest('div.editor,span.editor');
  if (wrapper && wrapper.is(editor.active)) {
    return;
  }
  $('.editor-active').removeClass('editor-active');
  editor.active = wrapper;
  wrapper.addClass('editor-active');
};

// handle result from image select modal dialog
$(document).on('data-select', function(e, o) {
  if (editor.enabled === false) {
    return;
  }

  // todo: replace hardcoded image size with dynamic using device pixel ratio

  // create new image at caret position
  if (o.modal_uid === '(new)') {
    var img_html =
      '<img src="' +
      base_url +
      '/img/' +
      o.uuid +
      '/medium" data-img-uuid=' +
      o.uuid +
      '>';
    editor.replace_html(editor.modal_uuid, img_html);
    editor.modal_uuid = false;
    editor.active.find('[data-edit-field]:first').data('edit-changed', true);
    editor.inputs++;
    $('#edit-button-save').removeClass('nb-disabled');
    return;
  }

  // update existing image
  var img = $(
    '[data-edit-uuid=' +
      o.resource_uuid +
      '] img[data-edit-img=' +
      o.modal_uid +
      ']'
  );
  if (img) {
    img.attr('src', base_url + '/img/' + o.uuid + '/medium');
    img.data('edit-changed', true);
    img.data('img-uuid', o.uuid);
    img.attr('data-img-uuid', o.uuid);
    img.data('empty', false);
    img.siblings('.clear-img-icon').removeClass('nb-close');
    editor.inputs++;
    $('#edit-button-save').removeClass('nb-disabled');
  }
});

// handle opening modal dialog
$(document).on('modal.open', function(e, o) {
  if (editor.enabled === false || o.url !== 'img-select' || o.uid !== '(new)') {
    return;
  }
  editor.save();
  editor.modal_uuid = editor.uuid();
  editor.insert_html(
    '<a id="' + editor.modal_uuid + '" data-remove="true"></a>'
  );
});

editor.init();