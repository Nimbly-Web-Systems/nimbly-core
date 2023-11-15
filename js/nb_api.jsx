var nb_api = {};

nb_api.post = async function nb_api_post(url = "", data = {}) {
    const response = await fetch(url, {
        method: "POST",
        mode: "same-origin",
        cache: "no-cache",
        credentials: "same-origin",
        headers: {
            "Content-Type": "application/json",
        },
        redirect: "follow",
        referrerPolicy: "no-referrer",
        body: JSON.stringify(data),
    });
    return response.json();
}

nb_api.get = async function nb_api_get(url = "") {
    const response = await fetch(url, {
        method: "GET",
        mode: "same-origin",
        cache: "no-cache",
        credentials: "same-origin",
        headers: {
            "Content-Type": "application/json",
        },
        redirect: "follow",
        referrerPolicy: "no-referrer"
    });
    return response.json();
}

nb_api.init_get = function(elem) {
    const params = elem.dataset['nbGet'];
    if (!params) {
        return;
    }
    var settings = JSON.parse(params);
    if (!settings.url) {
        return;
    }
    nb_api.get(settings.url).then(data => {
        nb_api.handle_get(elem, data);
    });
}

nb_api.handle_get = function(el, data) {
    var html = nb_populate(el.innerHTML, data);
    if (el.nodeName === 'TEMPLATE') {
        el.replaceWith(html);
    }
}

nb_api.init = function() {
    const nb_gets = document.querySelectorAll('[data-nb-get]');
    nb_gets.forEach(item => {
        nb_api.init_get(item);
    });
}

export function nb_api_init() {
    nb_api.init();
}