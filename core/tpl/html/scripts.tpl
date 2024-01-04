<script>
window.nb = {
    base_url: "[#base-url#]",
    text: {
        record_deleted: "[#text Record deleted#]",
        file_deleted: "[#text File deleted#]",
        record_added: "[#text Added record#]",
        record_updated: "[#text Updated record#]",
        profile_updated: "[#text Updated profile#]",
        medium_editor_placeholder: "[#text Type here#]",
        saved: "[#text Saved#]",
        unsaved_changes: "[#text You have unsaved changed. Are you sure you want to leave this page and discard your changes?#]",
        file_added: "[#text File uploaded#]"
    }
};
</script>

<script src="[#base-url#]/app.js"></script>

[#feature-cond edit echo="<script src='[#base-url#]/medium-editor.min.js'></script>"#]

<script>
[#include [#uri-path#]/index.js#]
Alpine.start();
</script>

<script src="[#base-url#]/tw-elements.umd.min.js"></script>