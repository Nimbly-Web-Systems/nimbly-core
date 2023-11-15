import { nb_api_init } from './nb_api.jsx';

/* global helper functions */

window.nb_populate = function (html, context) {
    let result = html;
    for (v in context) {
        const re = new RegExp('\\(\\(' + v + '\\)\\)', 'g')
        result = result.replace(re, context[v]);
    }
    return result;
}

nb_api_init();