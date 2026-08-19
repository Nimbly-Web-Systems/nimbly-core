
var nb_media_library = {
    page_size: 20,
    current_page: 0,
    first: 0,
    last: 0,
    _in_use_tolerance: new Date() - 4 * 60 * 60 * 1000, //now minus four hours
    file_info: null,
    _original_title: null,
    _original_description: null,
    caption_lang: null,
    ai_busy_caption: null,
    embed_info: {
        active: 'vimeo',
        vimeo: {
            id: null,
            height: 360,
            width: 640,
            mode: 'responsive',
            hash: null
        },
        youtube: {
            id: null,
            width: 640,
            height: 360
        },
        extimg: {
            url: null
        },
        doc: {
            insert_mode: 'link'
        }
    },
    files: [],
    unfiltered: [],
    allowed_types: [],
    page: [],
    init() {
        this.fetch_media();
        if (typeof nb_modal_insert_media !== 'undefined' && nb_modal_insert_media) {
            window.nb.media_modal.el = nb_modal_insert_media;
            window.nb.media_alpine = this;
            nb_modal_insert_media.addEventListener('nb:modal:show', this.handle_modal_show);
        }
    },
    fetch_media() {
        nb.api.get(nb.base_url + "/api/v1/.files_meta").then((files_meta_data) => {
            if (!files_meta_data.success) {
                if (files_meta_data.message === 'RESOURCE_NOT_FOUND') {
                    console.warn('Could not get media data');
                } else {
                    nb.notify(files_meta_data.message);
                }
                return;
            }
            this.unfiltered = Object.values(files_meta_data[".files_meta"]);
            this.files = [...this.unfiltered];
            this.sort_files();
            this.set_page(this.current_page);
        });

    },
    filter(allowed_types) {
        if (!allowed_types || allowed_types.length === 0) {
            this.files = [...this.unfiltered];
        } else {
            this.files = this.unfiltered.filter((x) => {
                const t = this._type(x);
                return allowed_types.includes(t);
            })
        }
        this.sort_files();
        this.set_page(this.current_page);
    },
    reset_tab() {
        if (this.mode === 'embed') {
            this.mode = 'insert';
        }
    },
    sort_files() {
        this.files.sort((a, b) => {
            let d = b._created - a._created;
            if (d == 0) {
                d = b._modified - a._modified;
            }
            return d;
        });
    },
    file_date(f) {
        let d = new Date(f * 1000);
        let result = d.getFullYear() + "-";
        if (d.getMonth() < 9) {
            result += "0";
        }
        result += (d.getMonth() + 1) + "-";
        if (d.getDate() < 10) {
            result += "0";
        }
        result += d.getDate();
        return result;
    },
    page_count() {
        return Math.ceil(this.files.length / this.page_size);
    },
    set_page(p) {
        this.page = [];
        if (this.files.length <= 0) {
            return;
        }
        const first = this.page_size * p;
        if (first > this.files.length) {
            set_page(p - 1);
            return;
        }
        this.clear_page();
        this.current_page = p;
        this.first = first + 1;
        this.last = Math.min(this.files.length, first + this.page_size);
        var fs = this.files.slice(first, first + this.page_size);
        nb.api.get(nb.base_url + "/api/v1/.files-unused?_ids=" + fs.map(f => f.uuid).join()).then((unused_files) => {
            if (!unused_files.success || unused_files.count === 0) {
                this.page = fs;
                return;
            }
            var ufs = unused_files['.files_unused'];
            fs.forEach((f) => f.in_use = !ufs.includes(f.uuid) || ((1000 * f._created) > this._in_use_tolerance));
            this.page = fs;
        });
    },
    clear_page() {
        // empty the image src immediately so the new images lazy load on white bg (not on previous img)
        var imgs = document.querySelectorAll('#nb-media-grid img');
        imgs.forEach((img_el) => {
            img_el.src = "";
        });
    },
    file_type(ix) {
        const f = typeof ix === 'undefined' ? this.file_info : this.page[ix];
        return this._type(f);

    },
    _type(f) {
        if (f === undefined) {
            return '---';
        }
        if (f && f.type.startsWith("image/svg")) {
            return "svg";
        } else if (f && f.type.startsWith("image")) {
            return "img";
        } else if (f && f.type.startsWith("video")) {
            return "vid";
        } else if (f && f.type.startsWith("audio")) {
            return "audio";
        } 
        return "doc";
    },
    doc_type(ix) {
        const f = typeof ix === 'undefined' ? this.file_info : this.page[ix];
        if (!f) {
            return '-?-';
        }
        const t = f.type.split('/');
        if (t.length !== 2) {
            return '-?-';
        }
        const result = t[1];
        if (result.length === 3) {
            return result;
        }
        if (result.includes('officedocument.word')) {
            return 'DOC';
        }
        if (result.includes('officedocument.spreadsheet')) {
            return 'XLS';
        }
        return '-?-';
    },
    vid_type(ix) {
        const f = typeof ix === 'undefined' ? this.file_info : this.page[ix];
        const default_result = 'mp4';
        if (!f) {
            return default_result;
        }
        const t = f.type.split('/');
        if (t.length !== 2) {
            return default_result;
        }
        return t[1];
    },
    audio_type(ix) {
        const f = typeof ix === 'undefined' ? this.file_info : this.page[ix];
        const default_result = 'mp3';
        if (!f) {
            return default_result;
        }
        const t = f.type.split('/');
        if (t.length !== 2) {
            return default_result;
        }
        return t[1];
    },
    // .files_meta's title/description are i18n ({lang: value}), but older
    // records (or a freshly-uploaded file that was never titled) may still
    // be a plain string, missing entirely, or (via json_encode([])) an
    // empty array — normalize all of those to a real per-language object
    // so every binding/PUT downstream can assume the same shape.
    _normalize_i18n_field(value) {
        if (typeof value === 'string') {
            return value === '' ? {} : { [nb.lang]: value };
        }
        if (value && typeof value === 'object' && !Array.isArray(value)) {
            return value;
        }
        return {};
    },
    _resolve_i18n(value) {
        if (!value) {
            return '';
        }
        if (value[nb.lang]) {
            return value[nb.lang];
        }
        const first = Object.values(value).find((v) => v);
        return first || '';
    },
    resolve_title() {
        return this.file_info ? this._resolve_i18n(this.file_info.title) : '';
    },
    resolve_description() {
        return this.file_info ? this._resolve_i18n(this.file_info.description) : '';
    },
    // populate_template() does a raw, unescaped {{var}} string replace —
    // safe for numeric/uuid data, but title/description are free text an
    // editor typed. Used both as element text content (safe with just
    // &/</> escaped) and inside a double-quoted attribute (alt="..."),
    // so quotes need escaping too or the value breaks out of the attribute.
    _html_escape(str) {
        if (!str) {
            return '';
        }
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    },
    // Builds a fresh file_info rather than mutating the object passed in —
    // that object is often the same one rendered elsewhere (the grid's
    // page[ix], unfiltered[]), and normalizing title/description in place
    // would silently corrupt those other views too.
    _load_file_info(info) {
        this.file_info = {
            ...info,
            title: this._normalize_i18n_field(info.title),
            description: this._normalize_i18n_field(info.description),
        };
        this._original_title = JSON.stringify(this.file_info.title);
        this._original_description = JSON.stringify(this.file_info.description);
        this.caption_lang = nb.lang;
    },
    switch_caption_lang(lang) {
        this.caption_lang = lang;
    },
    // Same PUT as save_media(), but without the "Saved" toast — used
    // internally where persisting is an implementation detail of some
    // other action (like translating) rather than something the editor
    // asked for directly.
    _save_media_silent() {
        return nb.api.put(nb.base_url + "/api/v1/.files_meta/" + this.file_info.uuid, {
            title: this.file_info.title,
            description: this.file_info.description,
        });
    },
    // Matches the article field-translate buttons: only offered, and only
    // ever fills, an empty field — never overwrites something an editor
    // already wrote.
    _caption_field_empty(field, lang) {
        const value = this.file_info?.[field]?.[lang];
        return !value || String(value).trim() === '';
    },
    has_empty_caption_fields(lang) {
        return ['title', 'description'].some((field) => this._caption_field_empty(field, lang));
    },
    // The AI endpoint translates from whatever's already saved on the
    // record (it reads the file fresh server-side), so the current
    // language's caption has to be persisted first or there's nothing to
    // translate from. Title and description translate together, one click,
    // but only whichever of the two is actually empty.
    ai_translate_caption(lang) {
        const fields = ['title', 'description'].filter((field) => this._caption_field_empty(field, lang));
        if (fields.length === 0) {
            return;
        }
        this.ai_busy_caption = lang;
        this._save_media_silent()
            .then(() =>
                Promise.all(
                    fields.map((field) =>
                        nb.api.post(nb.base_url + '/api/v1/openai/complete', {
                            resource: '.files_meta',
                            uuid: this.file_info.uuid,
                            field: field,
                            lang: lang,
                        }).then((data) => ({ field, data }))
                    )
                )
            )
            .then((results) => {
                this.ai_busy_caption = null;
                results.forEach(({ field, data }) => {
                    if (!data.success) {
                        nb.notify(data.message);
                    } else if (data.completion) {
                        this.file_info[field][lang] = data.completion;
                    }
                });
            })
            .catch((err) => {
                this.ai_busy_caption = null;
                nb.notify(err.message || 'Could not complete AI request');
            });
    },
    handle_upload_ready(e) {
        if (typeof e.detail !== "undefined" && e.detail.success) {
            e.detail.files.size = e.detail.files.size || 0;
            this._load_file_info(e.detail.files);
            this.files.unshift(this.file_info);
            this.set_page(this.current_page);
        }
    },
    select_media(ix) {
        this._load_file_info(this.page[ix]);
    },
    _file_info_changed() {
        return JSON.stringify(this.file_info.title) !== this._original_title ||
            JSON.stringify(this.file_info.description) !== this._original_description;
    },
    can_embed() {
        if (this.embed_info.active) {
            switch (this.embed_info.active) {
                case 'youtube':
                    return !!this.embed_info.youtube.id;
                case 'vimeo':
                    return !!this.embed_info.vimeo.id;
                case 'extimg':
                    return !!this.embed_info.extimg.url;
            }
        }
        return false;
    },
    delete_file(uuid) {
        nb.api.delete(nb.base_url + "/api/v1/.files/" + uuid).then((data) => {
            if (data.success) {
                nb.notify(nb.text.file_deleted);
                this.file_info = null;
                this.files = this.files.filter((file) => {
                    return file.uuid !== uuid;
                });
                this.set_page(this.current_page);
            } else {
                nb.notify(data.message);
            }
        });
    },
    save_media() {
        return nb.api.put(nb.base_url + "/api/v1/.files_meta/" + this.file_info.uuid, {
            title: this.file_info.title,
            description: this.file_info.description
        }).then((data) => {
            if (data.success) {
                nb.notify(nb.text.saved);
            }
            return data;
        })
    }
};

export default nb_media_library;
