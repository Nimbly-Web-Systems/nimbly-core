import nb_api from './nb_api.jsx';
import Alpine from 'alpinejs';

window.nb.api = nb_api;
window.Alpine = Alpine;

window.nb.notify = function(msg) {
    const el = document.getElementById('nb-system-messages');
    el.querySelector('p').innerHTML = msg;
    el.classList.remove('hidden');
}

window.nb.system_message = function(msg) {
    return nb.api.post(nb.base_url + '/api/v1/session', { "system_message": msg });
}

window.nb.hide_notification = function() {
    const el = document.getElementById('nb-system-messages');
    el.classList.add('hidden');
}