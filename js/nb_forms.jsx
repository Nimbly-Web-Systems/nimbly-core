var nb_forms = {
    form_data: {},
    file_info: {},
    update_slug(e) {
        const slug_child_name = e.currentTarget.getAttribute("name") + "_slug";
        const slug_child = document.querySelector(
            'input[name="' + slug_child_name + '"]'
        );
        if (slug_child) {
            const clean_val = e.currentTarget.value
                .toLowerCase()
                .trim()
                .replace(/[^0-9a-zA-Z-]/g, "-");
            this.form_data[slug_child_name] = clean_val;
        }
    },
    select_image(field_name, field_ix=undefined) {
        this.select_media(field_name, field_ix, ['img', 'svg']);
    },
    select_media(field_name, field_ix=undefined, filter=[]) {
        nb.media_alpine.mode = 'select';
        nb.media_alpine.filter(filter);
        nb.media_alpine.reset_tab();
        nb.media_modal.me = this; //remember this
        nb.media_modal._set_media = this._set_media;
        nb.media_modal.field = field_name;
        nb.media_modal.field_ix = field_ix;
    },
    _set_media(field_name, field_data) {
        // note: in this function 'this' refs the media modal, not this alpine object
        const ix = Number(nb.media_modal.field_ix);
        if (Number.isInteger(ix)) {
            nb.media_modal.me.form_data[field_name][ix] = field_data.uuid;
        } else {
            nb.media_modal.me.form_data[field_name] = field_data.uuid;
        }
        nb.media_modal.me.file_info[field_name] = field_data;
    },
    move_item(field_name, old_ix, new_ix) {
        if (old_ix === new_ix || new_ix < 0 || new_ix >= this.form_data[field_name].length) {
            return;
        }
        var value = this.form_data[field_name].splice(old_ix, 1)[0];
        this.form_data[field_name].splice(new_ix, 0, value);
    },
    delete_image(field_name, field_ix=undefined) {
        if (field_ix) {
            this.form_data[field_name].splice(field_ix, 1);
        } else {
            this.form_data[field_name] = '';
        }
    }
};

export default nb_forms;