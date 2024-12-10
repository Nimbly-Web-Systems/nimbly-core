var nb_edit = {
    default_buttons: ['bold', 'italic'],
    enabled: false,
    editors: [],
    inputs: 0,
    active_editor: null
};

nb_edit.init = function () {
    if (typeof MediumEditor === "undefined") {
        return;
    }
    const imgs = document.querySelectorAll("[data-nb-edit-img]");
    const all_editors = document.querySelectorAll("[data-nb-edit]");
    if (all_editors.length > 0 || imgs.length > 0) {
        const edit_menu = document.getElementById('nb_edit_menu');
        if (edit_menu) {
            edit_menu.classList.remove('hidden');
        }
    }
    const form_editors = document.querySelectorAll("form [data-nb-edit]");
    form_editors.forEach(ed => {
        nb_edit.init_editor(ed, true);
    });

    window.addEventListener('beforeunload', nb_edit.on_beforeunload);
}


nb_edit.init_editor = function (ed, as_form_field = false) {
    if (nb_edit.editors.includes(ed)) {
        if (ed._nb_plain) {
            ed.setAttribute('contenteditable', true);
        } else {
            nb_edit.enable(ed);
        }
        return;
    }
    const options = JSON.parse(ed.dataset.nbEditOptions || '{}');
    const buttons = typeof options.buttons === 'string' ?
        options.buttons.split(',').map((v) => { return v.trim(); })
        : nb_edit.default_buttons;
    const placeholder = options.placeholder ?
        options.placeholder
        : nb.text.medium_editor_placeholder;
    ed._nb_plain = typeof options.plain === "boolean" && options.plain === true;

    if (ed._nb_plain) {
        ed.setAttribute('contenteditable', true);
    } else {
        const has_buttons = buttons.length > 0 && buttons[0] != '';
        var editor_options = has_buttons ? {
            toolbar: {
                buttons: buttons
            }
        } : {
            toolbar: false
        }
        editor_options['placeholder'] = {
            text: placeholder
        };
        editor_options['imageDragging'] = typeof options.media === "boolean" && options.media === true;
        var editor = new MediumEditor(ed, editor_options);
        ed._nb_medium_editor = editor;
    }

    ed._nb_editor_options = options;
    ed._nb_mode = as_form_field ? 'form' : 'page';

    ed.addEventListener("focus", (e) => {
        nb_edit.on_focus(e);
    });
    ed.addEventListener("blur", (e) => {
        nb_edit.on_blur(e);
    });
    if (!as_form_field) {
        ed._nb_inputs = 0;
        ed.addEventListener('input', nb_edit.on_input);
        nb_edit.editors.push(ed);
    } // else form handles everything
}

nb_edit.on_focus = function (e) {
    const ed = e.currentTarget;
    nb_edit.active_editor = ed;
    const insert_media_btn = document.getElementById('nb_edit_insert_media');
    if (!insert_media_btn) {
        return;
    }
    if (ed._nb_editor_options.media) {
        insert_media_btn.removeAttribute('disabled');
    } else {
        insert_media_btn.setAttribute('disabled', true);
    }
}

nb_edit.on_blur = function (e) {
    const insert_media_btn = document.getElementById('nb_edit_insert_media');
    if (!insert_media_btn) {
        return;
    }
    const nb_bar_toggle_btn = document.getElementById('nb-bar-toggler');
    if (e.relatedTarget != insert_media_btn && e.relatedTarget != nb_bar_toggle_btn) {
        insert_media_btn.setAttribute('disabled', true);
        nb_edit.active_editor = null;
    }
}

nb_edit.toggle = function () {
    if (typeof MediumEditor === "undefined") {
        return;
    }
    const all_editors = document.querySelectorAll("[data-nb-edit]");
    const form_editors = Array.from(document.querySelectorAll("form [data-nb-edit]"));
    nb_edit.enabled = !nb_edit.enabled;
    all_editors.forEach(ed => {
        if (form_editors.includes(ed)) {
            return;
        }
        if (nb_edit.enabled) {
            nb_edit.enable_editor(ed);
        } else {
            nb_edit.disable_editor(ed);
        }
    });
    const all_imgs = document.querySelectorAll("[data-nb-edit-img]");
    all_imgs.forEach(eimg => {
        if (nb_edit.enabled) {
            nb_edit.enable_img(eimg);
        } else {
            nb_edit.disable_img(eimg);
        }
    });
}

nb_edit.enable_editor = function (ed) {
    if (typeof ed._nb_medium_editor == 'undefined') {
        nb_edit.init_editor(ed);
    } else {
        ed.setAttribute('contenteditable', true);
    }
}

nb_edit.disable_editor = function (ed) {
    ed.setAttribute('contenteditable', false);
}

nb_edit.enable_img = function (eimg) {
    eimg.setAttribute('data-nb-edit-img-enabled', true);
    eimg.classList.add('relative');
    const img_el = eimg.querySelector('img');
    if (!img_el) {
        return;
    }
    let img_uuid = img_el.src.slice(img_el.src.indexOf('/img/') + 5);
    img_uuid = img_uuid.substr(0, img_uuid.indexOf('/'));
    eimg.setAttribute('data-nb-edit-img-value', img_uuid);
    if (eimg.querySelectorAll('button[data-te-toggle=modal]').length === 0) {
        eimg.insertAdjacentHTML('beforeend', document.getElementById('nb_edit_img_btn').innerHTML);
        eimg.querySelector('button[data-te-toggle=modal]').addEventListener('click', function () {
            nb.media_alpine.mode = 'select';
            nb.media_alpine.filter(['img', 'svg']);
            nb.media_alpine.reset_tab();
            nb.media_modal._set_media = nb_edit.set_img;
            nb.media_modal.field = eimg;
        });
    }
}

nb_edit.disable_img = function (eimg) {
    eimg.removeAttribute('data-nb-edit-img-enabled');
}

nb_edit.get_field_values = function (el) {
    var result = {};
    const fields = el.querySelectorAll('[data-nb-edit');
    fields.forEach(f => {
        const key = f.dataset.nbEdit;
        if (key) {
            result[key] = f.innerHTML;
        }
    });
    const imgs = el.querySelectorAll('[data-nb-edit-img');
    imgs.forEach(eimg => {
        const key = eimg.dataset.nbEditImg;
        if (key) {
            eimg.dataset.nbEditImgValue;
            result[key] = eimg.dataset.nbEditImgValue;
        }
    });
    return result;
}

nb_edit.on_input = function (e) {
    if (!nb_edit.enabled) {
        return;
    }
    nb_edit.inputs++;
    if (e.currentTarget._nb_mode == 'page') {
        e.currentTarget._nb_inputs++;
    }
    document.getElementById('nb_edit_save').removeAttribute('disabled');
};

nb_edit.store_caret_pos = function () {
    if (!nb_edit.active_editor) {
        return;
    }
    const selection = window.getSelection();
    const range = selection.getRangeAt(0);
    const cloned_range = range.cloneRange();
    cloned_range.selectNodeContents(nb_edit.active_editor);
    cloned_range.setEnd(range.endContainer, range.endOffset);

    const last = cloned_range.toString().length;
    const first = last - (range.endOffset - range.startOffset);
    nb_edit.active_editor._nb_caret_pos = {
        start: first,
        end: last
    };
}

nb_edit.restore_caret_pos = function () {
    if (!nb_edit.active_editor) {
        return;
    }
    nb_edit.active_editor.focus();
    const range = nb_edit.create_range(nb_edit.active_editor, nb_edit.active_editor._nb_caret_pos.start, nb_edit.active_editor._nb_caret_pos.end);
    const selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(range);
}

nb_edit.create_range = function (n, start, stop) {
    let range = document.createRange();
    range.selectNode(n);
    range.setStart(n, 0);
    let pos = 0;
    const len = stop - start;
    const stack = [n];
    while (stack.length > 0) {
        const current = stack.pop();
        if (current.nodeType === Node.TEXT_NODE) {
            const len = current.textContent.length;
            if (pos + len >= start) {
                range.setStart(current, start - pos);
            }
            if (pos + len >= stop) {
                range.setEnd(current, stop - pos);
                return range;
            }
            pos += len;
        } else if (current.childNodes && current.childNodes.length > 0) {
            for (let i = current.childNodes.length - 1; i >= 0; i--) {
                stack.push(current.childNodes[i]);
            }
        }
    }

    range.setStart(n, n.childNodes.length - len);
    range.setEnd(n, n.childNodes.length);
    return range;
}

nb_edit.insert_html = function (html) {
    if (!this.active_editor) {
        return;
    }
    this.on_input({ currentTarget: this.active_editor });
    var sel = window.getSelection();
    var range = sel.getRangeAt(0);
    range.deleteContents();
    var el = document.createElement('div');
    el.innerHTML = html;
    var frag = document.createDocumentFragment();
    var lastNode = false;
    while ((node = el.firstChild)) {
        lastNode = frag.appendChild(node);
    }
    range.insertNode(frag);

    // preserve selection
    if (lastNode) {
        range = range.cloneRange();
        range.setStartAfter(lastNode);
        range.collapse(true);
        sel.removeAllRanges();
        sel.addRange(range);
    }
};

nb_edit.set_img = function (eimg, data) {
    const old_uuid = eimg.dataset.nbEditImgValue;
    if (old_uuid === data.uuid) {
        return;
    }
    nb_edit.inputs++;
    eimg._nb = eimg._nb || { inputs: 0 };
    eimg._nb.inputs++;
    eimg.innerHTML = eimg.innerHTML.replaceAll('/img/' + old_uuid + '/', '/img/' + data.uuid + '/');
    eimg.setAttribute('data-nb-edit-img-value', data.uuid);
    const img = eimg.querySelector('img');
    img.setAttribute('width', data.width || '');
    img.setAttribute('height', data.height || '');
    if (data.orientation === 'landscape') {
        img.style.maxWidth = 'min(' + data.width + 'px, 100vw)';
        img.style.maxHeight = null;
        img.style.height = 'auto';
        img.style.width = null;
    } else if (data.orientation === 'portrait') {
        img.style.maxHeight = 'min(' + data.height + 'px, 100vh)';
        img.style.maxWidth = null;
        img.style.width = 'auto';
        img.style.height = null;
    }
    document.getElementById('nb_edit_save').removeAttribute('disabled');
}

nb_edit.make_links_target_blank = function (ed) {
    const links = ed.querySelectorAll('a');
    links.forEach(link => {
        const href = link.getAttribute('href');
        if (href && href.includes('://') && !href.startsWith(window.location.origin)) {
            link.setAttribute('target', '_blank');
            link.setAttribute('rel', 'noopener noreferrer'); // security best practice
        }
    });
};

nb_edit.save = function () {
    nb_edit.inputs = 0;

    /* loop through editors checking if it has changes */
    this.editors.forEach(ed => {
        if (ed._nb_inputs > 0) {
            ed._nb_inputs = 0;
            nb_edit.save_resource(ed);
        }
    });

    const imgs = document.querySelectorAll('[data-nb-edit-img]');
    imgs.forEach(eimg => {
        if (typeof eimg._nb === 'undefined'
            || typeof eimg._nb.inputs === 'undefined'
            || eimg._nb.inputs < 1) {
            return;
        }
        eimg._nb.inputs = 0;
        nb_edit.save_resource(eimg);

    })
};

nb_edit.save_resource = function (ed) {
    const is_img = typeof ed.dataset.nbEditImg !== 'undefined';
    let resource_dot = ed.dataset[is_img ? 'nbEditImg' : 'nbEdit'].trim().toLowerCase();
    var offset = 0;
    if (resource_dot.lastIndexOf('.', 0) === 0) {
        // hidden resource
        offset = 1;
    }

    const resource_set = resource_dot.split('.');
    if ((resource_set.length - offset) !== 3) {
        console.warn('nb_edit.save_resource: unknown resource', resource_set, resource_set.length - offset);
        return;
    }
    var resource = resource_set[offset + 0];
    if (offset === 1) {
        resource = '.' + resource;
    }
    const uuid = resource_set[offset + 1];
    const field = resource_set[offset + 2];
    const api_url = nb.base_url + '/api/v1/' + resource + '/' + uuid;

    // add target=_blank to external links
    if (!is_img) {
        nb_edit.make_links_target_blank(ed);
    }

    const data = {};
    data[field] = is_img ? ed.dataset.nbEditImgValue : ed.innerHTML.trim();
    nb.api.put(api_url, data).then(d1 => {
        if (d1.success) {
            nb.notify(nb.text.saved);
        } else if (d1.code = 404) {
            // create resource
            nb.api.post(api_url, data).then(d2 => {
                if (d2.success) {
                    nb.notify(nb.text.saved);
                } else {
                    nb.notify(d2.message);
                }
            })
        } else {
            nb_notify(d1.message);
        }
    })
}

nb_edit.on_beforeunload = function (e) {
    if (nb_edit.inputs < 1) {
        return undefined;
    }
    var msg = nb.text.unsaved_changes;
    e.returnValue = msg;
    return msg;
};

nb_edit.has_changes = function () {
    return nb_edit.inputs > nb_edit.last_inputs;
}





export default nb_edit;