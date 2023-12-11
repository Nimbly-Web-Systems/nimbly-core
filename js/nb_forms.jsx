var nb_forms = {
    form_data: {},
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
    select_image(field_name) {
        nb.media_alpine.mode = 'select';
        nb.media_alpine.filter(['img']);
        nb.media_modal.me = this; //remember this
        nb.media_modal._set_field = this._set_field;
        nb.media_modal.field = field_name;
    },
    _set_field(field_name, field_value) {
        // note: in this function 'this' refs the media modal, not this alpine object
        nb.media_modal.me.form_data[field_name] = field_value;
    },
    delete_image(field_name) {
        this.form_data[field_name] = '';
    }
};

export default nb_forms;