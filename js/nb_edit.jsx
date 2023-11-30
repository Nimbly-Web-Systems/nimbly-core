import { __isOptionsFunction } from "@tailwindcss/typography";

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
    const all_editors = document.querySelectorAll("[data-nb-edit]");
    if (all_editors.length > 0) {
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
        nb_edit.enable(ed);
        return;
    }
    const options = JSON.parse(ed.dataset.nbEditOptions || '{}');
    const buttons = options.buttons ?
        options.buttons.split(',').map((v) => { return v.trim(); })
        : nb_edit.default_buttons;
    const placeholder = options.placeholder ?
        options.placeholder
        : nb.text.medium_editor_placeholder;
    var editor = new MediumEditor(ed, {
        toolbar: {
            buttons: buttons
        },
        placeholder: {
            text: placeholder
        }
    });
    ed._nb_medium_editor = editor;
    ed._nb_editor_options = options;
    ed._nb_medium_editor._nb_mode = as_form_field ? 'form' : 'page';
    ed.addEventListener("focus", (e) => {
        nb_edit.on_focus(e);
    });
    ed.addEventListener("blur", (e) => {
        nb_edit.on_blur(e);
    });
    if (!as_form_field) {
        ed._nb_medium_editor._nb_inputs = 0;
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
    const ed = e.currentTarget;
    const insert_media_btn = document.getElementById('nb_edit_insert_media');
    if (!insert_media_btn) {
        return;
    }
    if (e.relatedTarget != insert_media_btn) {
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
}

nb_edit.enable_editor = function (ed) {
    if (typeof ed._nb_medium_editor == 'undefined') {
        nb_edit.init_editor(ed);
    } else {
        ed.setAttribute('contenteditable', true);
    }
}

nb_edit.disable_editor = function (ed) {
    if (typeof ed._nb_medium_editor == 'undefined') {
        return;
    }
    ed.setAttribute('contenteditable', false);
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
    return result;
}

nb_edit.on_input = function (e) {
    if (!nb_edit.enabled) {
        return;
    }
    nb_edit.inputs++;
    if (e.currentTarget._nb_medium_editor._nb_mode == 'page') {
        e.currentTarget._nb_medium_editor._nb_inputs++;
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


nb_edit.save = function () {
    nb_edit.inputs = 0;

    /* loop through editors checking if it has changes */
    this.editors.forEach(ed => {
        if (ed._nb_medium_editor._nb_inputs > 0) {
            ed._nb_medium_editor._nb_inputs = 0;
            nb_edit.save_resource(ed);
        }
    })
};

nb_edit.save_resource = function (ed) {
    const resource_dot = ed.dataset.nbEdit.trim().toLowerCase(); // e.g. content.contact.main (resource).(uuid).(field)
    var offset = 0;
    if (resource_dot.lastIndexOf('.', 0) === 0) {
        // hidden resource
        offset = 1;
    }

    const resource_set = ed.dataset.nbEdit.split('.');
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

    const data = {};
    data[field] = ed.innerHTML;
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