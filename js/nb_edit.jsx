var nb_edit = {
    default_buttons: ['bold', 'italic', 'removeFormat'],
    enabled: false,
    editors: [],
    inputs: 0,
    pending_uploads: new Set(),
    active_editor: null
};

nb_edit.init = function () {
    // admin form fields are editable right away; page content waits for edit mode (page actions pill)
    const form_editors = document.querySelectorAll("form [data-nb-edit]");
    form_editors.forEach(ed => {
        nb_edit.init_editor(ed, true);
    });

    window.addEventListener('beforeunload', nb_edit.on_beforeunload);
}


nb_edit.init_editor = function (ed, as_form_field = false) {
    if (nb_edit.editors.includes(ed)) {
        ed.setAttribute('contenteditable', true);
        return;
    }
    const options = JSON.parse(ed.dataset.nbEditOptions || '{}');
    const buttons = typeof options.buttons === 'string' ?
        options.buttons.split(',').map((v) => { return v.trim(); })
        : nb_edit.default_buttons;
    const placeholder = options.placeholder ?
        options.placeholder
        : nb.text.editor_placeholder;
    ed._nb_plain = typeof options.plain === "boolean" && options.plain === true;

    if (ed._nb_plain) {
        ed.setAttribute('contenteditable', true);
        // Plain fields have no media support and must not receive native file drops.
        ed.addEventListener('dragover', e => {
            if (Array.from(e.dataTransfer?.types || []).includes('Files')) e.preventDefault();
        });
        ed.addEventListener('drop', e => {
            if (e.dataTransfer?.files.length) e.preventDefault();
        });
        ed.addEventListener('paste', e => {
            if (e.clipboardData?.files.length) e.preventDefault();
        });
    } else {
        window.nb.bubble_editor.init(ed, {
            buttons: buttons,
            placeholder: placeholder,
            paste_html: options.paste_html === true,
            as_form_field: as_form_field
        });
    }

    ed._nb_editor_options = options;
    ed._nb_mode = as_form_field ? 'form' : 'page';

    window.nb.field_bar.attach(ed, {
        buttons: ed._nb_plain ? [] : buttons,
        media: !ed._nb_plain && options.media === true,
        save: !as_form_field,
        docked: as_form_field
    });

    if (as_form_field && ed._nb_plain) {
        ed.addEventListener('input', () => {
            ed.dispatchEvent(new CustomEvent('nb:editor-change', {
                bubbles: true,
                detail: { value: ed.innerHTML.trim() }
            }));
        });
    }

    ed.addEventListener("focus", (e) => {
        nb_edit.on_focus(e);
    });
    ed.addEventListener("blur", (e) => {
        nb_edit.on_blur(e);
    });
    if (!as_form_field) {
        ed._nb_inputs = 0;
        ed.addEventListener('input', nb_edit.on_input);
        nb_edit.editors.push(ed);
    } // else form handles everything
}

nb_edit.on_focus = function (e) {
    nb_edit.active_editor = e.currentTarget;
}

nb_edit.on_blur = function (e) {
    const nb_bar_toggle_btn = document.getElementById('nb-bar-toggler');
    const moving_to_toolbar = e.relatedTarget?.closest?.('.nb-bubble-toolbar, .nb-field-bar');
    // keep the editor while picking media for it; the insert goes to its stored caret
    const media_modal = document.getElementById('nb-modal-insert-media');
    const picking_media = media_modal && !media_modal.classList.contains('hidden');
    if (!moving_to_toolbar && !picking_media && e.relatedTarget != nb_bar_toggle_btn) {
        nb_edit.active_editor = null;
    }
}

nb_edit.toggle = function () {
    const all_editors = document.querySelectorAll("[data-nb-edit]");
    const form_editors = Array.from(document.querySelectorAll("form [data-nb-edit]"));
    nb_edit.enabled = !nb_edit.enabled;
    // leaving edit mode: let the focused field blur first, so its toolbars close
    const focused = document.activeElement;
    if (!nb_edit.enabled && focused && focused.closest && focused.closest('[data-nb-edit]')) {
        focused.blur();
    }
    all_editors.forEach(ed => {
        if (form_editors.includes(ed)) {
            return;
        }
        if (nb_edit.enabled) {
            nb_edit.enable_editor(ed);
        } else {
            nb_edit.disable_editor(ed);
        }
    });
    const all_imgs = document.querySelectorAll("[data-nb-edit-img]");
    all_imgs.forEach(eimg => {
        if (nb_edit.enabled) {
            nb_edit.enable_img(eimg);
        } else {
            nb_edit.disable_img(eimg);
        }
    });
    document.dispatchEvent(new CustomEvent('nb:edit-mode', { detail: { enabled: nb_edit.enabled } }));
}

nb_edit.set_editing = function (on) {
    if (nb_edit.enabled !== on) {
        nb_edit.toggle();
    }
}

// the page has content that inline editing can change (not just admin form fields)
nb_edit.has_page_content = function () {
    return Array.from(document.querySelectorAll('[data-nb-edit]')).some((ed) => { return !ed.closest('form'); })
        || document.querySelector('[data-nb-edit-img]') !== null;
}

nb_edit.is_editable = function (ed) {
    if (!ed) {
        return false;
    }
    return nb_edit.enabled || ed._nb_mode === 'form';
}

nb_edit.enable_editor = function (ed) {
    if (!nb_edit.editors.includes(ed)) {
        nb_edit.init_editor(ed);
    } else {
        ed.setAttribute('contenteditable', true);
    }
}

nb_edit.disable_editor = function (ed) {
    ed.setAttribute('contenteditable', false);
}

nb_edit.enable_img = function (eimg) {
    eimg.setAttribute('data-nb-edit-img-enabled', true);
    eimg.classList.add('relative');
    const img_el = eimg.querySelector('img');
    if (!img_el) {
        return;
    }
    let img_uuid = img_el.src.slice(img_el.src.indexOf('/img/') + 5);
    img_uuid = img_uuid.substr(0, img_uuid.indexOf('/'));
    eimg.setAttribute('data-nb-edit-img-value', img_uuid);
    if (eimg.querySelectorAll('button[data-nb-open-media-modal]').length === 0) {
        eimg.insertAdjacentHTML('beforeend', document.getElementById('nb_edit_img_btn').innerHTML);
        eimg.querySelector('button[data-nb-open-media-modal]').addEventListener('click', function () {
            nb.media_alpine.mode = 'select';
            nb.media_alpine.filter(['img', 'svg']);
            nb.media_alpine.reset_tab();
            nb.media_modal._set_media = nb_edit.set_img;
            nb.media_modal.field = eimg;
            nb.modal.open('nb-modal-insert-media');
        });
    }
}

nb_edit.disable_img = function (eimg) {
    eimg.removeAttribute('data-nb-edit-img-enabled');
}

nb_edit.get_field_values = function (el) {
    var result = {};
    const fields = el.querySelectorAll('[data-nb-edit]');
    const alpine_scope = el._x_dataStack ? el._x_dataStack.find(scope => scope.form_data) : null;
    const form_data = alpine_scope ? alpine_scope.form_data : {};
    const form_lang = el.dataset.lang;

    fields.forEach(f => {
        const key = f.dataset.nbEdit;
        if (!key) return;

        nb_edit.make_links_target_blank(f);

        if (f.dataset.nbEditI18n === 'true' && form_lang) {
            const current_value = form_data[key];
            result[key] = current_value && typeof current_value === 'object' && !Array.isArray(current_value)
                ? { ...current_value }
                : {};
            result[key][form_lang] = f.innerHTML.trim();
            return;
        }

        const parts = key.split('.');
        const last = parts[parts.length - 1];

        // detect language (2-char, matches known languages if available)
        const is_lang = last.length === 2;

        if (is_lang) {
            const lang = last;
            const field = parts.slice(0, -1).join('.');

            if (!result[field] || typeof result[field] !== 'object') {
                result[field] = {};
            }

            result[field][lang] = f.innerHTML.trim();
        } else {
            result[key] = f.innerHTML.trim();
        }
    });

    const imgs = el.querySelectorAll('[data-nb-edit-img]');
    imgs.forEach(eimg => {
        const key = eimg.dataset.nbEditImg;
        if (key) {
            result[key] = eimg.dataset.nbEditImgValue;
        }
    });

    return result;
}

nb_edit.on_input = function (e) {
    if (!nb_edit.enabled) {
        return;
    }
    nb_edit.inputs++;
    if (e.currentTarget._nb_mode == 'page') {
        e.currentTarget._nb_inputs++;
    }
    document.querySelectorAll('[data-nb-edit-save]').forEach((btn) => {
        btn.removeAttribute('disabled');
    });
};

nb_edit.store_caret_pos = function () {
    if (!nb_edit.active_editor) {
        return;
    }
    const selection = window.getSelection();
    const range = selection.getRangeAt(0);
    const cloned_range = range.cloneRange();
    cloned_range.selectNodeContents(nb_edit.active_editor);
    cloned_range.setEnd(range.endContainer, range.endOffset);

    const last = cloned_range.toString().length;
    const first = last - (range.endOffset - range.startOffset);
    nb_edit.active_editor._nb_caret_pos = {
        start: first,
        end: last
    };
}

nb_edit.restore_caret_pos = function () {
    if (!nb_edit.active_editor) {
        return;
    }
    nb_edit.active_editor.focus();
    const range = nb_edit.create_range(nb_edit.active_editor, nb_edit.active_editor._nb_caret_pos.start, nb_edit.active_editor._nb_caret_pos.end);
    const selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(range);
}

nb_edit.create_range = function (n, start, stop) {
    let range = document.createRange();
    range.selectNode(n);
    range.setStart(n, 0);
    let pos = 0;
    const len = stop - start;
    const stack = [n];
    while (stack.length > 0) {
        const current = stack.pop();
        if (current.nodeType === Node.TEXT_NODE) {
            const len = current.textContent.length;
            if (pos + len >= start) {
                range.setStart(current, start - pos);
            }
            if (pos + len >= stop) {
                range.setEnd(current, stop - pos);
                return range;
            }
            pos += len;
        } else if (current.childNodes && current.childNodes.length > 0) {
            for (let i = current.childNodes.length - 1; i >= 0; i--) {
                stack.push(current.childNodes[i]);
            }
        }
    }

    range.setStart(n, n.childNodes.length - len);
    range.setEnd(n, n.childNodes.length);
    return range;
}

// elements that cannot live inside a paragraph
nb_edit.block_tags = ['FIGURE', 'DIV', 'VIDEO', 'TABLE', 'P', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6',
    'UL', 'OL', 'BLOCKQUOTE', 'PRE', 'HR', 'SECTION', 'ASIDE'];
// text blocks a block insert may split at the caret
nb_edit.split_tags = 'p, h1, h2, h3, h4, h5, h6, blockquote, pre';

nb_edit.insert_html = function (html, editor = this.active_editor, destination = null) {
    if (!editor) {
        return;
    }
    const sel = window.getSelection();
    let range = destination || (sel.rangeCount ? sel.getRangeAt(0) : null);
    if (!range || !editor.contains(range.commonAncestorContainer)) {
        return;
    }
    // Completing an asynchronous insertion must not steal another field's focus.
    const restore_selection = destination && !editor.contains(document.activeElement);
    const previous_range = restore_selection && sel.rangeCount ? sel.getRangeAt(0).cloneRange() : null;
    range.deleteContents();
    const el = document.createElement('div');
    el.innerHTML = html.trim();
    const nodes = Array.from(el.childNodes);
    if (nodes.length === 0) {
        return;
    }
    const is_block = nodes.some((n) => { return n.nodeType === Node.ELEMENT_NODE && nb_edit.block_tags.includes(n.tagName); });
    const start = range.startContainer.nodeType === Node.ELEMENT_NODE ? range.startContainer : range.startContainer.parentElement;
    const block = is_block ? start.closest(nb_edit.split_tags) : null;

    let caret_node = null;
    if (block && block !== editor && editor.contains(block)) {
        // split the paragraph at the caret and put the block content between the halves
        const tail_range = document.createRange();
        tail_range.setStart(range.startContainer, range.startOffset);
        tail_range.setEnd(block, block.childNodes.length);
        const after = block.cloneNode(false);
        after.append(tail_range.extractContents());
        block.after(...nodes, after);
        if (nb_edit.is_empty_block(block)) {
            block.remove();
        }
        if (nb_edit.is_empty_block(after)) {
            // somewhere to keep typing below the inserted block
            const p = document.createElement('p');
            p.append(document.createElement('br'));
            after.replaceWith(p);
            caret_node = p;
        } else {
            caret_node = after;
        }
        range = document.createRange();
        range.setStart(caret_node, 0);
    } else {
        const frag = document.createDocumentFragment();
        frag.append(...nodes);
        range.insertNode(frag);
        range = range.cloneRange();
        range.setStartAfter(nodes[nodes.length - 1]);
    }
    range.collapse(true);
    if (!restore_selection || previous_range) {
        sel.removeAllRanges();
        sel.addRange(previous_range || range);
    }

    if (editor._nb_mode === 'form') {
        editor.dispatchEvent(new CustomEvent('nb:editor-change', {
            bubbles: true,
            detail: { value: editor.innerHTML.trim() }
        }));
    } else {
        this.on_input({ currentTarget: editor });
    }
    if (editor._nb_bubble && window.nb.bubble_editor) {
        window.nb.bubble_editor.update_empty(editor);
    }
};

nb_edit.is_empty_block = function (el) {
    return el.textContent.replace(/\u00a0/g, ' ').trim() === '' && !el.querySelector('img, video, iframe, figure, hr');
}

nb_edit.set_img = function (eimg, data) {
    const old_uuid = eimg.dataset.nbEditImgValue;
    if (old_uuid === data.uuid) {
        return;
    }
    nb_edit.inputs++;
    eimg._nb = eimg._nb || { inputs: 0 };
    eimg._nb.inputs++;
    eimg.innerHTML = eimg.innerHTML.replaceAll('/img/' + old_uuid + '/', '/img/' + data.uuid + '/');
    eimg.setAttribute('data-nb-edit-img-value', data.uuid);
    const img = eimg.querySelector('img');
    img.setAttribute('width', data.width || '');
    img.setAttribute('height', data.height || '');
    if (data.orientation === 'landscape') {
        img.style.maxWidth = 'min(' + data.width + 'px, 100vw)';
        img.style.maxHeight = null;
        img.style.height = 'auto';
        img.style.width = null;
    } else if (data.orientation === 'portrait') {
        img.style.maxHeight = 'min(' + data.height + 'px, 100vh)';
        img.style.maxWidth = null;
        img.style.width = 'auto';
        img.style.height = null;
    }
    document.getElementById('nb_edit_save').removeAttribute('disabled');
}

nb_edit.make_links_target_blank = function (ed) {
    const links = ed.querySelectorAll('a');
    links.forEach(link => {
        const href = link.getAttribute('href');
        if (href && href.includes('://') && !href.startsWith(window.location.origin)) {
            link.setAttribute('target', '_blank');
            link.setAttribute('rel', 'noopener noreferrer'); // security best practice
        }
    });
};

nb_edit.open_insert_media = function () {
    if (nb.media_alpine) {
        nb.media_alpine.filter();
        nb.media_alpine.mode = 'insert';
        nb.media_alpine.reset_tab();
    }
    nb_edit.store_caret_pos();
    nb.modal.open('nb-modal-insert-media');
}

nb_edit.save = function () {
    if (nb_edit.uploads_pending()) return;
    nb_edit.inputs = 0;
    document.querySelectorAll('[data-nb-edit-save]').forEach((btn) => {
        btn.setAttribute('disabled', true);
    });

    /* loop through editors checking if it has changes */
    this.editors.forEach(ed => {
        if (ed._nb_inputs > 0) {
            ed._nb_inputs = 0;
            nb_edit.save_resource(ed);
        }
    });

    const imgs = document.querySelectorAll('[data-nb-edit-img]');
    imgs.forEach(eimg => {
        if (typeof eimg._nb === 'undefined'
            || typeof eimg._nb.inputs === 'undefined'
            || eimg._nb.inputs < 1) {
            return;
        }
        eimg._nb.inputs = 0;
        nb_edit.save_resource(eimg);

    })
};

nb_edit.save_resource = function (ed) {
    const is_img = typeof ed.dataset.nbEditImg !== 'undefined';
    let resource_dot = ed.dataset[is_img ? 'nbEditImg' : 'nbEdit'].trim().toLowerCase();
    var offset = 0;
    if (resource_dot.lastIndexOf('.', 0) === 0) {
        // hidden resource
        offset = 1;
    }

    const resource_set = resource_dot.split('.');
    let lang = false;
    if ((resource_set.length - offset) === 4 && resource_set[offset + 3].length === 2) {
        lang = resource_set[offset + 3];
    } else if ((resource_set.length - offset) !== 3) {
        console.warn('nb_edit.save_resource: unknown resource', resource_set, resource_set.length - offset);
        return;
    }
    var resource = resource_set[offset + 0];
    if (offset === 1) {
        resource = '.' + resource;
    }
    const uuid = resource_set[offset + 1];
    const field = resource_set[offset + 2];
    const api_url = nb.base_url + '/api/v1/' + resource + '/' + uuid;

    // add target=_blank to external links
    if (!is_img) {
        nb_edit.make_links_target_blank(ed);
    }

    const data = {};
    const val = is_img ? ed.dataset.nbEditImgValue : ed.innerHTML.trim();
    if (lang) {
        data[field] = {};
        data[field][lang] = val;
    } else {
        data[field] = val;
    }
    nb.api.put(api_url, data).then(d1 => {
        if (d1.success) {
            nb.notify(nb.text.saved);
        } else if (d1.code === 404) {
            // create resource
            nb.api.post(api_url, data).then(d2 => {
                if (d2.success) {
                    nb.notify(nb.text.saved);
                } else {
                    nb.notify(d2.message);
                }
            })
        } else {
            nb.notify(d1.message);
        }
    })
}

nb_edit.on_beforeunload = function (e) {
    if (nb_edit.pending_uploads.size) {
        e.returnValue = nb.text.unsaved_changes;
        return e.returnValue;
    }
    if (nb_edit.inputs < 1) {
        return undefined;
    }
    // Admin build-form fields are deliberately excluded from this dirty
    // counter (see the as_form_field branch in init_editor, and
    // nb_edit.save() — the only place that resets inputs — which build-form
    // never calls), so on an admin page inputs can only reflect an
    // incidental edit to inline-editable page chrome (e.g. an h1 rendered
    // via the .content system), never the record actually being edited.
    // That's never worth blocking a redirect over, and it was latching
    // stale after a fully successful save.
    var base_url = nb.base_url === "/" ? "" : nb.base_url;
    if (window.location.pathname.startsWith(base_url + "/nb-admin")) {
        return undefined;
    }
    var msg = nb.text.unsaved_changes;
    e.returnValue = msg;
    return msg;
};

nb_edit.uploads_pending = function (scope = null) {
    const pending = Array.from(nb_edit.pending_uploads).some(item => !scope || scope.contains(item.editor));
    if (pending) nb.notify(nb.text.images_uploading || 'Images are uploading. Please wait before saving.');
    return pending;
};

nb_edit.upload_images = async function (editor, files, range, pasted_html = null) {
    if (editor._nb_editor_options?.media !== true || !files.length) return;
    const max_size = nb.max_upload_size || Infinity;
    if (files.some(file => !file.type.startsWith('image/') || file.size > max_size)) {
        nb.notify(nb.text.image_upload_invalid || 'Please use image files within the upload size limit.');
        return;
    }
    const marker = document.createElement('span');
    marker.setAttribute('contenteditable', 'false');
    marker.setAttribute('data-nb-upload-placeholder', '');
    marker.setAttribute('role', 'status');
    marker.textContent = nb.text.images_uploading || 'Uploading images…';
    range = range.cloneRange();
    range.collapse(true);
    range.insertNode(marker);
    const pending = { editor, marker };
    nb_edit.pending_uploads.add(pending);
    editor.setAttribute('aria-busy', 'true');
    try {
        // All results retain input order; insert nothing if any upload fails.
        const results = await Promise.allSettled(files.map(file => nb.upload.upload(file)));
        if (results.some(result => result.status === 'rejected')) {
            throw new Error(nb.text.image_upload_failed || 'Image upload failed. Please try again.');
        }
        const uploaded = results.map(result => result.value);
        if (uploaded.some(result => !result.success || !result.files?.uuid)) {
            throw new Error(nb.text.image_upload_failed || 'Image upload failed. Please try again.');
        }
        let html;
        const options = editor._nb_editor_options;
        if (pasted_html) {
            const embedded = pasted_html.content.querySelectorAll('img[src^="data:image/"]');
            embedded.forEach((img, index) => {
                const replacement = document.createElement('template');
                replacement.innerHTML = nb.upload.image_html(uploaded[index].files, options, img.alt);
                img.replaceWith(replacement.content);
            });
            html = pasted_html.innerHTML;
        } else {
            html = uploaded.map(result => nb.upload.image_html(result.files, options)).join('');
        }
        if (!marker.isConnected || editor.contentEditable !== 'true') {
            throw new Error(nb.text.image_upload_cancelled || 'Image uploaded. Reopen the editor to insert it from the media library.');
        }
        const destination = document.createRange();
        destination.selectNode(marker);
        nb_edit.insert_html(html, editor, destination);
    } catch (error) {
        nb.notify(error.message || nb.text.image_upload_failed || 'Image upload failed. Please try again.');
    } finally {
        marker.remove();
        nb_edit.pending_uploads.delete(pending);
        if (!Array.from(nb_edit.pending_uploads).some(item => item.editor === editor)) {
            editor.removeAttribute('aria-busy');
        }
        nb.bubble_editor.update_empty(editor);
    }
};

nb_edit.has_changes = function () {
    return nb_edit.inputs > nb_edit.last_inputs;
}





export default nb_edit;
