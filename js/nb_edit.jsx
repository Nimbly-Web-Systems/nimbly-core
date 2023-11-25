var nb_edit = {
    default_buttons: ['bold', 'italic'],
    enabled: false,
    editors: [],
    inputs: 0,
};

nb_edit.init = function () {
    if (typeof MediumEditor === "undefined") {
        return;
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
    const buttons = ed.dataset.nbEditButtons ?
        ed.dataset.nbEditButtons.split(',').map((v) => { return v.trim(); })
        : nb_edit.default_buttons;
    const placeholder = ed.dataset.nbEditPlaceholder ?
        ed.dataset.nbEditPlaceholder
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
    ed._nb_medium_editor._nb_mode = as_form_field ? 'form' : 'page';
    if (!as_form_field) {
        ed._nb_medium_editor._nb_inputs = 0;
        ed.addEventListener('input', nb_edit.on_input);
        nb_edit.editors.push(ed);
    } // else form handles everything
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

nb_edit.save = function () {
    console.log('nb_edit.save');
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