import nb_api from './nb_api.jsx';
import nb_edit from './nb_edit.jsx';
import nb_upload from './nb_upload.jsx';
import nb_forms from './nb_forms.jsx';
import nb_media_library from './nb_media_library.jsx';
import Alpine from 'alpinejs';

window.nb.api = nb_api;
window.nb.edit = nb_edit;
window.nb.upload = nb_upload;
window.nb.forms = nb_forms;
window.nb.media_library = nb_media_library;
window.nb.media_modal = window.nb.media_modal || {};
window.Alpine = Alpine;

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

window.nb.populate_template = function(tpl_id, data) {
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