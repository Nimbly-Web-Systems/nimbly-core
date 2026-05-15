import Alpine from 'alpinejs';
import nb_api from './nb_api.jsx';
import nb_edit from './nb_edit.jsx';
import nb_upload from './nb_upload.jsx';
import nb_forms from './nb_forms.jsx';
import nb_media_library from './nb_media_library.jsx';
import nb_breakpoints from './nb_breakpoints.jsx';

window.Alpine = Alpine;
window.nb.api = nb_api;
window.nb.edit = nb_edit;
window.nb.upload = nb_upload;
window.nb.forms = nb_forms;
window.nb.media_library = nb_media_library;
window.nb.media_modal = window.nb.media_modal || {};

window.nb.edit.init();
window.nb.upload.init();

window.nb.notify = function (msg) {
    const el = document.getElementById('nb-system-messages');
    el.querySelector('p').innerHTML = msg;
    el.classList.remove('hidden');
}

window.nb.system_message = function (msg) {
    return nb.api.post(nb.base_url + '/api/v1/session', { "system_message": msg });
}

window.nb.hide_notification = function () {
    const el = document.getElementById('nb-system-messages');
    el.classList.add('hidden');
}

window.nb.modal = {
    open(id) {
        const el = typeof id === 'string' ? document.getElementById(id) : id;
        if (!el) {
            return;
        }
        el.classList.remove('hidden');
        el.removeAttribute('aria-hidden');
        el.setAttribute('aria-modal', 'true');
        el.dispatchEvent(new CustomEvent('nb:modal:show', { bubbles: true }));
    },
    close(id) {
        const el = typeof id === 'string' ? document.getElementById(id) : id;
        if (!el) {
            return;
        }
        el.classList.add('hidden');
        el.setAttribute('aria-hidden', 'true');
        el.removeAttribute('aria-modal');
        el.dispatchEvent(new CustomEvent('nb:modal:hide', { bubbles: true }));
    }
};

window.nb.populate_template = function (tpl_id, data) {
    const tpl_el = document.getElementById(tpl_id);
    if (!tpl_el) {
        console.warn('template not found:', tpl_id);
        return '';
    }
    var result = tpl_el.innerHTML;
    for (v in data) {
        const re = new RegExp('\\{\\{' + v + '\\}\\}', 'g')
        result = result.replace(re, data[v]);
    }
    return result;
}

// console.log((186457865).fileSize()) ==> 177.8 Mb
Object.defineProperty(Number.prototype, 'fileSize', {
    value: function (a, b, c, d) {
        return (a = a ? [1e3, 'k', 'B'] : [1024, 'K', 'iB'], b = Math, c = b.log,
            d = c(this) / c(a[0]) | 0, this / b.pow(a[0], d)).toFixed(1)
            + ' ' + (d ? (a[1] + 'MGTPEZY')[--d] + a[2] : 'Bytes');
    }, writable: false, enumerable: false
});

window.nb.tw_breakpoints = {
    sm: 640,
    md: 768,
    lg: 1024,
    xl: 1280,
    xxl: 1536
};

nb_breakpoints.init();

Alpine.data('nb_table', () => ({
    search: '',
    sort_index: null,
    sort_direction: 'asc',
    rows: [],
    init() {
        this.rows = Array.from(this.$refs.body.querySelectorAll('tr'));
    },
    filter_rows() {
        const term = this.search.trim().toLowerCase();
        this.rows.forEach((row) => {
            row.classList.toggle('hidden', term && !row.textContent.toLowerCase().includes(term));
        });
    },
    sort_by(index) {
        if (this.sort_index === index) {
            this.sort_direction = this.sort_direction === 'asc' ? 'desc' : 'asc';
        } else {
            this.sort_index = index;
            this.sort_direction = 'asc';
        }

        const direction = this.sort_direction === 'asc' ? 1 : -1;
        this.rows.sort((a, b) => {
            const a_text = (a.children[index]?.textContent || '').trim().toLowerCase();
            const b_text = (b.children[index]?.textContent || '').trim().toLowerCase();
            return a_text.localeCompare(b_text, undefined, { numeric: true }) * direction;
        });

        this.rows.forEach((row) => this.$refs.body.appendChild(row));
        this.filter_rows();
    },
}));
