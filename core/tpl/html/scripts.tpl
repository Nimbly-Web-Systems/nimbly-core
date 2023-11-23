
window.nb = {
    base_url: "[base-url]",
    text: {
        record_deleted: "[text Record deleted]",
        record_added: "[text Added record]",
        record_updated: "[text Updated record]",
    }
};
[include [base-path]js/bundle/tw-elements.umd.min.js]
[feature-cond edit tpl=edit-script]
[include [base-path]js/bundle/app.js]
[include [uri-path]/index.js]
Alpine.start();