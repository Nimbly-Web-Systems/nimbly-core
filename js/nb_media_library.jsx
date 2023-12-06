
var nb_media_library = {
    page_size: 20,
    current_page: 0,
    first: 0,
    last: 0,
    file_info: null,
    files: [],
    page: [],
    init() {
        nb.api.get(nb.base_url + "/api/v1/.files_meta").then((data) => {
            if (!data.success) {
                nb.notify(data.message);
                return;
            }
            this.files = Object.values(data[".files_meta"]);
            this.sort_files();
            this.set_page(this.current_page);
        });
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
        if (f && f.type.startsWith("image")) {
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