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
    select_image(field_name, field_ix = undefined) {
        this.select_media(field_name, field_ix, ['img', 'svg']);
    },
    select_media(field_name, field_ix = undefined, filter = []) {
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
        nb.media_modal.me.file_info[field_data.uuid] = field_data;
    },
    move_item(field_name, old_ix, new_ix) {
        if (old_ix === new_ix || new_ix < 0 || new_ix >= this.form_data[field_name].length) {
            return;
        }
        var value = this.form_data[field_name].splice(old_ix, 1)[0];
        this.form_data[field_name].splice(new_ix, 0, value);
    },
    delete_image(field_name, field_ix = undefined) {
        if (field_ix) {
            this.form_data[field_name].splice(field_ix, 1);
        } else {
            this.form_data[field_name] = '';
        }
    },
    get_file_meta(field_name) {
        this.form_data[field_name].forEach((item) => {
            this.fetch_file_meta(item).then(r => this.file_info[item] = r);
        });
    },
    async fetch_file_meta(uuid) {
        if (this.file_info[uuid] !== undefined) {
            return this.file_info[uuid];
        }
        // todo: check if media library has this file info
        if (nb.media_library.files && nb.media_library.files.lengh) {
            console.log('does media lib have info on file', uuid, nb.media_library);
        }

        // otherwise, look it up:
        var result = {}
        await nb.api.get(nb.base_url + '/api/v1/.files_meta/' + uuid).then(data => {
            if (data.success) {
                result = data['.files_meta'][uuid];
            }
        });
        return result;
    },
    _resolve_path_ix(path) {
        if (this.ix !== undefined) {
            return path.replace(/(\[)ix(\])/g, `$1${this.ix}$2`);

        }
        return path;
    },
    _get_obj_val(path) {
        path = this._resolve_path_ix(path);
        return path
            .replace(/\[(\w+)\]/g, '.$1')
            .split('.')
            .reduce((o, k) => (o ? o[k] : undefined), this);
    },

    _set_obj_val(path, value) {
        var target = this._get_obj_val(path);
        if (target && typeof target === "object") {
            Object.assign(target, value);
        }
    },
    _ensure_obj_val(path, value) {
        path = this._resolve_path_ix(path);
        const tokens = path.replace(/\[(\w+)\]/g, '.$1').split('.');
        let o = this;

        for (let i = 0; i < tokens.length; i++) {
            const key = tokens[i];
            const next_key = tokens[i + 1];
            const is_last = i === tokens.length - 1;

            if (/^\d+$/.test(key)) {
                const index = parseInt(key);

                if (!Array.isArray(o)) {
                    console.warn("Expected array at", tokens.slice(0, i).join('.'), "but found", o);
                    return;
                }

                if (!o[index] || typeof o[index] !== "object" || Array.isArray(o[index])) {
                    o[index] = is_last ? value : {};
                }

                o = o[index];
            } else {
                if (o[key] === undefined) {
                    if (/^\d+$/.test(next_key)) {
                        o[key] = [];
                    } else if (is_last) {
                        o[key] = value;
                    } else {
                        o[key] = {};
                    }
                }

                o = o[key];
            }
        }
    }
};

export default nb_forms;