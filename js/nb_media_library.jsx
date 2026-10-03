
var nb_media_library = {
    // number of files rendered per scroll step
    page_size: 40,
    shown: 0,
    search: '',
    type_filter: '',
    unused_only: false,
    unused_loading: false,
    sort: 'newest',
    _unused_all: null,
    _in_use_tolerance: new Date() - 4 * 60 * 60 * 1000, //now minus four hours
    file_info: null,
    upload_status: null,
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
            this.apply_filters();
        });

    },
    // restricts the library to the types a picker accepts; the user's own
    // search and filters start clean and narrow down within that set
    filter(allowed_types) {
        this.allowed_types = allowed_types || [];
        this.search = '';
        this.type_filter = '';
        this.unused_only = false;
        this.apply_filters();
    },
    type_available(t) {
        if (this.allowed_types.length === 0) {
            return true;
        }
        return this.allowed_types.includes(t) || (t === 'img' && this.allowed_types.includes('svg'));
    },
    type_options() {
        return ['img', 'vid', 'audio', 'doc'].filter((t) => this.type_available(t));
    },
    file_title(f) {
        return this._resolve_i18n(this._normalize_i18n_field(f.title));
    },
    set_type_filter(t) {
        this.type_filter = t;
        this.apply_filters();
    },
    // keep: stay on the current images (after a delete or upload) instead of
    // jumping back to the first batch
    apply_filters(keep = false) {
        const q = this.search.trim().toLowerCase();
        this.files = this.unfiltered.filter((f) => {
            const t = this._type(f);
            if (this.allowed_types.length > 0 && !this.allowed_types.includes(t)) {
                return false;
            }
            if (this.type_filter && (t === 'svg' ? 'img' : t) !== this.type_filter) {
                return false;
            }
            if (this.unused_only && f.in_use !== false) {
                return false;
            }
            return !q || this._search_text(f).includes(q);
        });
        this.sort_files();
        this.shown = keep ? Math.max(this.shown, this.page_size) : this.page_size;
        this.render_page();
    },
    _search_text(f) {
        return [
            f.name,
            ...Object.values(this._normalize_i18n_field(f.title)),
            ...Object.values(this._normalize_i18n_field(f.description)),
        ].filter((v) => typeof v === 'string').join(' ').toLowerCase();
    },
    toggle_unused() {
        this.unused_only = !this.unused_only;
        if (!this.unused_only || this._unused_all) {
            this.apply_filters();
            return;
        }
        // one scan for the whole library, reused until the next upload
        this.unused_loading = true;
        nb.api.get(nb.base_url + "/api/v1/.files-unused").then((unused_files) => {
            this.unused_loading = false;
            if (!unused_files.success) {
                this.unused_only = false;
                return;
            }
            this._unused_all = unused_files['.files_unused'] || [];
            this.unfiltered.forEach((f) => this._set_in_use(f, this._unused_all));
            this.apply_filters();
        });
    },
    _set_in_use(f, unused_ids) {
        f.in_use = !unused_ids.includes(f.uuid) || ((1000 * f._created) > this._in_use_tolerance);
    },
    reset_tab() {
        if (this.mode === 'embed') {
            this.mode = 'insert';
        }
    },
    sort_files() {
        if (this.sort === 'name') {
            this.files.sort((a, b) => String(a.name).localeCompare(String(b.name), undefined, { numeric: true, sensitivity: 'base' }));
            return;
        }
        const dir = this.sort === 'oldest' ? -1 : 1;
        this.files.sort((a, b) => {
            let d = b._created - a._created;
            if (d == 0) {
                d = b._modified - a._modified;
            }
            return dir * d;
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
    render_page() {
        this.page = this.files.slice(0, this.shown);
        // the in-use check scans site content; show the files first, badges follow
        const fs = this.page.filter((f) => typeof f.in_use === 'undefined');
        if (fs.length === 0) {
            return;
        }
        nb.api.get(nb.base_url + "/api/v1/.files-unused?_ids=" + fs.map(f => f.uuid).join()).then((unused_files) => {
            if (!unused_files.success) {
                return;
            }
            const ufs = unused_files['.files_unused'] || [];
            fs.forEach((f) => this._set_in_use(f, ufs));
        });
    },
    load_more() {
        if (this.shown >= this.files.length) {
            return;
        }
        this.shown += this.page_size;
        this.render_page();
    },
    // renders the next batch whenever the end of the grid scrolls into view
    observe_grid_end(el) {
        const observer = new IntersectionObserver((entries) => {
            if (!entries[0].isIntersecting || this.shown >= this.files.length) {
                return;
            }
            this.load_more();
            // re-observe: fires again if the new batch still leaves the end in view
            observer.unobserve(el);
            this.$nextTick(() => observer.observe(el));
        }, { rootMargin: '400px' });
        observer.observe(el);
    },
    // makes sure a file further down the list is rendered
    reveal(file) {
        const ix = this.files.findIndex((f) => f.uuid === file.uuid);
        if (ix >= this.shown) {
            this.shown = Math.ceil((ix + 1) / this.page_size) * this.page_size;
            this.render_page();
        }
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
    // A legacy scalar carries no language tag at all, so tagging it has to
    // be a guess either way — but nb.lang (whatever language the admin
    // happens to be browsing the site in right now) is an arbitrary UI
    // state, unrelated to what language the text was actually written in.
    // The site's primary configured language is a far better guess.
    _normalize_i18n_field(value) {
        if (typeof value === 'string') {
            return value === '' ? {} : { [nb.languages[0] || nb.lang]: value };
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
            // re-uploading a file returns the existing record; keep one entry
            this.unfiltered = this.unfiltered.filter((file) => file.uuid !== e.detail.files.uuid);
            this.unfiltered.unshift(e.detail.files);
            this._unused_all = null;
            this.apply_filters(true);
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
                this.unfiltered = this.unfiltered.filter((file) => {
                    return file.uuid !== uuid;
                });
                this.apply_filters(true);
            } else {
                nb.notify(data.message);
            }
        });
    },
    // the grid and search read the list records, file_info is a copy
    sync_caption() {
        const file = this.unfiltered.find((f) => f.uuid === this.file_info.uuid);
        if (file) {
            file.title = { ...this.file_info.title };
            file.description = { ...this.file_info.description };
        }
    },
    save_media() {
        return nb.api.put(nb.base_url + "/api/v1/.files_meta/" + this.file_info.uuid, {
            title: this.file_info.title,
            description: this.file_info.description
        }).then((data) => {
            if (data.success) {
                nb.notify(nb.text.saved);
                this.sync_caption();
            }
            return data;
        })
    }
};

export default nb_media_library;
