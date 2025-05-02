
var nb_media_library = {
    page_size: 20,
    current_page: 0,
    first: 0,
    last: 0,
    _in_use_tolerance: new Date() - 4 * 60 * 60 * 1000, //now minus four hours
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
        if (typeof nb_modal_insert_media !== 'undefined') {
            window.nb.media_modal.el = nb_modal_insert_media;
            window.nb.media_alpine = this;
            window.addEventListener('show.te.modal', this.handle_modal_show);
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
        const media_tab_el = document.getElementById('tab_media_library_btn');
        const media_tab = new te.Tab(media_tab_el);
        media_tab.show();
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