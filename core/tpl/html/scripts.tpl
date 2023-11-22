
window.nb={
    base_url: "[base-url]"
};
[include [base-path]js/bundle/tw-elements.umd.min.js]
[feature-cond edit tpl=edit-script]
[include [base-path]js/bundle/app.js]
[include [uri-path]/index.js]
Alpine.start();