var nb_edit = {
    default_buttons: ['bold', 'italic']
};

nb_edit.init = function () {
    if (!MediumEditor) {
        return;
    }
    const editors = document.querySelectorAll("[data-nb-edit]");
    editors.forEach(ed => {
        nb_edit.init_editor(ed);
    });
}

nb_edit.init_editor = function (ed) {
    const buttons = ed.dataset.nbEditButtons ?
        ed.dataset.nbEditButtons.split(',').map((v) => { return v.trim(); })
        : nb_edit.default_buttons;
    const placeholder = ed.dataset.nbEditPlaceholder? 
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


export default nb_edit;