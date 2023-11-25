
window.nb = {
    base_url: "[base-url]",
    text: {
        record_deleted: "[text Record deleted]",
        record_added: "[text Added record]",
        record_updated: "[text Updated record]",
        medium_editor_placeholder: "[text Type here]",
        saved: "[text Saved]",
        unsaved_changes: "[text You have unsaved changed. Are you sure you want to leave this page and discard your changes?]"
    }
};
[include [base-path]js/bundle/tw-elements.umd.min.js]
[feature-cond edit tpl=edit-script]
[include [base-path]js/bundle/app.js]
[include [uri-path]/index.js]
Alpine.start();