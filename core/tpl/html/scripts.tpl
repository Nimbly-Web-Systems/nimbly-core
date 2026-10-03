<script>
window.nb = {
    base_url: "[#base-url#]",
    lang: "[#language#]",
    languages: [#site-languages-json#],
    ai_translate_available: [#ai-translate-available#],
    max_upload_size: "[#max-upload-size bytes#]",
    text: {
        record_deleted: "[#text Record deleted#]",
        file_deleted: "[#text File deleted#]",
        record_added: "[#text Added record#]",
        record_updated: "[#text Updated record#]",
        profile_updated: "[#text Updated profile#]",
        editor_placeholder: "[#text Type here#]",
        saved: "[#text Saved#]",
        unsaved_changes: "[#text You have unsaved changed. Are you sure you want to leave this page and discard your changes?#]",
        file_added: "[#text File uploaded#]",
        images_uploading: "[#text Images are uploading. Please wait.#]",
        image_upload_failed: "[#text Image upload failed. Please try again.#]",
        image_upload_type: "[#text Please use a JPG, PNG, GIF, WebP, AVIF or SVG image.#]",
        image_upload_heic: "[#text HEIC photos are not supported. Please export the photo as JPG.#]",
        image_upload_too_large: "[#text This image is larger than the upload limit.#]",
        image_upload_cancelled: "[#text Image uploaded. Reopen the editor to insert it from the media library.#]",
        save_after_uploads: "[#text Saving as soon as the images are uploaded…#]",
        save_upload_failed: "[#text Not saved yet: an image failed to upload.#]",
        drop_images: "[#text Drop images to upload#]",
        uploading: "[#text Uploading#]",
        upload_waiting: "[#text Waiting…#]",
        upload_processing: "[#text Processing…#]",
        cancel_upload: "[#text Cancel upload#]",
        retry: "[#text Retry#]",
        remove: "[#text Remove#]"
    }
};
</script>

<script src="[#base-url#]/app.js?v=[#app-modified#]"></script>

[#if data.config.site.pwa.enabled=true tpl=pwa-register tpl_else=pwa-unregister#]

[#collect-script#]

<script>
[#include [#uri-path#]/index.js#]
Alpine.start();
</script>
