
var nb_media_library = {
    page_size: 20,
    current_page: 0,
    first: 0,
    last: 0,
    file_info: null,
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
        }
    },
    files: [],
    unfiltered: [],
    allowed_types: [],
    page: [],
    init() {
        this.fetch_media();
        if (typeof nb_modal_insert_media !== 'undefined') {
            window.nb.media_modal.el = nb_modal_insert_media;
            window.nb.media_alpine = this;
            window.addEventListener('show.te.modal', this.handle_modal_show);
        }
    },
    fetch_media() {
        nb.api.get(nb.base_url + "/api/v1/.files_meta").then((data) => {
            if (!data.success) {
                nb.notify(data.message);
                return;
            }
            this.unfiltered = Object.values(data[".files_meta"]);
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
        this.set_page(this.current_page);
    },
    reset_tab() {
        const media_tab_el = document.getElementById('tab_media_library_btn');
        const media_tab = new te.Tab(media_tab_el);
        media_tab.show();
    },
    sort_files() {
        this.files.sort((a, b) => {
            return b._modified - a._modified;
        });
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
        this.current_page = p;
        this.first = first + 1;
        this.last = Math.min(this.files.length, first + this.page_size);
        this.page = this.files.slice(first, first + this.page_size);
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
        return default_result;
    },
    handle_upload_ready(e) {
        if (typeof e.detail !== "undefined" && e.detail.success) {
            e.detail.files.size = e.detail.files.size || 0;
            this.file_info = e.detail.files;
            this.files.unshift(this.file_info);
            this.set_page(this.current_page);
        }
    },
    select_media(ix) {
        this.file_info = this.page[ix];
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
        nb.api.put(nb.base_url + "/api/v1/.files_meta/" + this.file_info.uuid, {
            title: this.file_info.title,
            description: this.file_info.description
        }).then((data) => {
            if (data.success) {
                nb.notify(nb.text.saved);
            }
        })
    }
};

export default nb_media_library;