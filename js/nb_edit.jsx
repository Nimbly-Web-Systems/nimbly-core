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
            if (!Array.from(e.dataTransfer?.types || []).includes('Files')) return;
            e.preventDefault();
            e.dataTransfer.dropEffect = 'none';
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
            result[key][form_lang] = nb_edit.editor_html(f);
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

            result[field][lang] = nb_edit.editor_html(f);
        } else {
            result[key] = nb_edit.editor_html(f);
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
            detail: { value: nb_edit.editor_html(editor) }
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
    if (nb_edit.defer_until_uploaded(document, () => nb_edit.save())) return;
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
    const val = is_img ? ed.dataset.nbEditImgValue : nb_edit.editor_html(ed);
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

// Editor HTML without upload placeholders, which must never be stored.
nb_edit.editor_html = function (ed) {
    if (!ed.querySelector('[data-nb-upload-placeholder]')) return nb_edit.links_for_storage(ed.innerHTML.trim());
    const clone = ed.cloneNode(true);
    clone.querySelectorAll('[data-nb-upload-placeholder]').forEach(el => el.remove());
    return nb_edit.links_for_storage(clone.innerHTML.trim());
};

// Links to this site are stored from its root: without this environment's own address and
// without its base path. The page adds the base path again when it is shown.
nb_edit.links_for_storage = function (html, base_url = nb.base_url, origin = window.location.origin) {
    const escape = text => text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const base = String(base_url || '').replace(/\/+$/, '');
    const own = '(?:' + escape(String(origin || '')) + ')' + (origin ? '?' : '') + escape(base);
    if (!origin && !base) return html;
    return html.replace(
        new RegExp('(<a\\b[^>]*?\\shref=")(' + own + ')(?=[/?#"])', 'gi'),
        (match, start, address, offset, whole) => address === '' ? match : start + (whole[offset + match.length] === '/' ? '' : '/')
    );
};

nb_edit.has_pending_uploads = function (scope = null) {
    return Array.from(nb_edit.pending_uploads).some(item => !scope || scope.contains(item.editor));
};

nb_edit.uploads_pending = function (scope = null) {
    const pending = nb_edit.has_pending_uploads(scope);
    if (pending) nb.notify(nb.text.images_uploading || 'Images are uploading. Please wait.');
    return pending;
};

// Runs fn once the uploads within scope are done; returns true when it has to wait.
nb_edit.defer_until_uploaded = function (scope, fn) {
    if (!nb_edit.has_pending_uploads(scope)) return false;
    if (!nb_edit.deferred_saves.has(scope)) {
        nb.notify(nb.text.save_after_uploads || 'Saving as soon as the images are uploaded…');
    }
    nb_edit.deferred_saves.set(scope, fn);
    return true;
};

nb_edit.upload_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif', 'image/bmp', 'image/svg+xml'];
nb_edit.upload_concurrency = 3;
nb_edit.upload_queue = [];
nb_edit.uploads_active = 0;
nb_edit.deferred_saves = new Map();

nb_edit.upload_error = function (file) {
    if (/^image\/hei[cf]/.test(file.type) || /\.hei[cf]$/i.test(file.name)) {
        return nb.text.image_upload_heic || 'HEIC photos are not supported. Please export the photo as JPG.';
    }
    if (!nb_edit.upload_types.includes(file.type)) {
        return nb.text.image_upload_type || 'Please use a JPG, PNG, GIF, WebP, AVIF or SVG image.';
    }
    if (file.size > Number(nb.max_upload_size || Infinity)) {
        return nb_edit.upload_too_large_text();
    }
    return '';
};

nb_edit.upload_too_large_text = function () {
    const mb = Math.floor(Number(nb.max_upload_size) / 1048576);
    return (nb.text.image_upload_too_large || 'This image is larger than the upload limit.') + (mb > 0 ? ' (' + mb + ' MB)' : '');
};

nb_edit.upload_images = function (editor, files, range, pasted_html = null) {
    if (editor._nb_editor_options?.media !== true || !files.length) return;
    const errors = new Set();
    const items = files.map(file => {
        const error = nb_edit.upload_error(file);
        if (error) {
            errors.add(error);
            return null;
        }
        return { editor, file, alt: '', placeholder: nb_edit.upload_placeholder(file) };
    });
    errors.forEach(error => nb.notify(error));
    const valid = items.filter(Boolean);
    range = range.cloneRange();
    if (pasted_html) {
        // Pasted text appears immediately; each embedded image uploads in place.
        const embedded = pasted_html.content.querySelectorAll('img[src^="data:image/"]');
        embedded.forEach((img, index) => {
            if (items[index]) {
                items[index].alt = img.getAttribute('alt') || '';
                img.replaceWith(items[index].placeholder);
            } else {
                img.remove();
            }
        });
        nb_edit.insert_html(pasted_html.innerHTML, editor, range);
        valid.forEach(item => {
            item.placeholder = editor.querySelector('[data-nb-upload-id="' + item.placeholder.dataset.nbUploadId + '"]');
        });
    } else {
        if (!valid.length) return;
        range.collapse(true);
        const frag = document.createDocumentFragment();
        valid.forEach(item => frag.append(item.placeholder));
        range.insertNode(frag);
    }
    valid.forEach(item => {
        if (!item.placeholder) return;
        item.url = item.placeholder.querySelector('.nb-upload-preview')?.src;
        nb_edit.bind_upload_placeholder(item);
        nb_edit.queue_upload(item);
    });
    nb.bubble_editor.update_empty(editor);
};

nb_edit.upload_placeholder = function (file) {
    const t = nb.text;
    const el = document.createElement('span');
    el.className = 'nb-upload';
    el.setAttribute('contenteditable', 'false');
    el.setAttribute('data-nb-upload-placeholder', '');
    el.dataset.nbUploadId = String(++nb_edit.upload_seq);
    el.innerHTML = '<img class="nb-upload-preview" alt="">'
        + '<span class="nb-upload-panel">'
        + '<span class="nb-upload-label" role="status" aria-live="polite"></span>'
        + '<span class="nb-upload-track" role="progressbar" aria-valuemin="0" aria-valuemax="100"><span class="nb-upload-bar"></span></span>'
        + '<span class="nb-upload-actions">'
        + '<button type="button" class="btn btn-xs btn-primary" data-upload-action="retry"></button>'
        + '<button type="button" class="btn btn-xs btn-ghost" data-upload-action="remove"></button>'
        + '</span></span>'
        + '<button type="button" class="nb-upload-cancel" data-upload-action="cancel">'
        + '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>'
        + '</button>';
    el.querySelector('.nb-upload-preview').src = URL.createObjectURL(file);
    el.querySelector('[data-upload-action="retry"]').textContent = t.retry || 'Retry';
    el.querySelector('[data-upload-action="remove"]').textContent = t.remove || 'Remove';
    el.querySelector('[data-upload-action="cancel"]').setAttribute('aria-label', t.cancel_upload || 'Cancel upload');
    el.querySelector('.nb-upload-track').setAttribute('aria-label', file.name);
    return el;
};
nb_edit.upload_seq = 0;

nb_edit.bind_upload_placeholder = function (item) {
    const el = item.placeholder;
    // keep the caret and focus where they are when a placeholder button is used
    el.addEventListener('mousedown', e => e.preventDefault());
    el.addEventListener('click', e => {
        const action = e.target.closest('[data-upload-action]')?.dataset.uploadAction;
        if (!action) return;
        e.preventDefault();
        e.stopPropagation();
        if (action === 'retry') nb_edit.queue_upload(item);
        else nb_edit.remove_upload(item);
    });
};

nb_edit.set_upload_state = function (item, state, progress = 0) {
    const el = item.placeholder;
    const t = nb.text;
    el.dataset.state = state;
    const percent = Math.round(progress * 100);
    const track = el.querySelector('.nb-upload-track');
    const label = {
        queued: t.upload_waiting || 'Waiting…',
        uploading: (t.uploading || 'Uploading') + ' ' + percent + '%',
        processing: t.upload_processing || 'Processing…',
        error: item.error || t.image_upload_failed || 'Image upload failed. Please try again.',
    }[state];
    el.querySelector('.nb-upload-label').textContent = label;
    if (state === 'uploading') {
        track.setAttribute('aria-valuenow', percent);
        el.style.setProperty('--nb-upload-progress', percent + '%');
    } else {
        track.removeAttribute('aria-valuenow');
    }
};

nb_edit.queue_upload = function (item) {
    item.error = '';
    nb_edit.pending_uploads.add(item);
    item.editor.setAttribute('aria-busy', 'true');
    nb_edit.set_upload_state(item, 'queued');
    nb_edit.upload_queue.push(item);
    nb_edit.pump_uploads();
};

nb_edit.pump_uploads = function () {
    while (nb_edit.uploads_active < nb_edit.upload_concurrency && nb_edit.upload_queue.length) {
        nb_edit.run_upload(nb_edit.upload_queue.shift());
    }
};

nb_edit.run_upload = async function (item) {
    nb_edit.uploads_active++;
    item.controller = new AbortController();
    nb_edit.set_upload_state(item, 'uploading', 0);
    try {
        const res = await nb.upload.upload(item.file, null, {
            quiet: true,
            signal: item.controller.signal,
            on_progress: fraction => {
                // after the last byte the server still stores and inspects the file
                if (item.placeholder.dataset.state !== 'uploading') return;
                nb_edit.set_upload_state(item, fraction >= 1 ? 'processing' : 'uploading', fraction);
            }
        });
        if (!res.success || !res.files?.uuid) {
            throw new Error(res.message === 'UPLOAD_TOO_LARGE' ? nb_edit.upload_too_large_text()
                : (nb.text.image_upload_failed || 'Image upload failed. Please try again.'));
        }
        nb_edit.set_upload_state(item, 'processing');
        await nb_edit.insert_uploaded(item, res.files);
        nb_edit.end_upload(item);
    } catch (error) {
        if (error.name !== 'AbortError') {
            item.error = error.message === 'Network error' || !error.message
                ? (nb.text.image_upload_failed || 'Image upload failed. Please try again.') : error.message;
            nb_edit.end_upload(item, true);
        }
    } finally {
        nb_edit.uploads_active--;
        nb_edit.pump_uploads();
    }
};

nb_edit.insert_uploaded = async function (item, file) {
    const { editor, placeholder } = item;
    const html = nb.upload.image_html(file, editor._nb_editor_options, item.alt);
    await nb_edit.preload_image(html);
    if (!placeholder.isConnected) return; // removed from the content meanwhile
    if (editor.contentEditable !== 'true') {
        nb.notify(nb.text.image_upload_cancelled || 'Image uploaded. Reopen the editor to insert it from the media library.');
        return;
    }
    const destination = document.createRange();
    destination.selectNode(placeholder);
    nb_edit.insert_html(html, editor, destination);
};

// Fetch the responsive image before swapping it in, so it never flashes empty.
nb_edit.preload_image = function (html) {
    const tpl = document.createElement('template');
    tpl.innerHTML = html;
    const source = tpl.content.querySelector('img');
    if (!source) return Promise.resolve();
    const img = new Image();
    if (source.sizes) img.sizes = source.sizes;
    if (source.srcset) img.srcset = source.srcset;
    img.src = source.getAttribute('src');
    const timeout = new Promise(resolve => setTimeout(resolve, 8000));
    return Promise.race([img.decode().catch(() => {}), timeout]);
};

nb_edit.remove_upload = function (item) {
    nb_edit.upload_queue = nb_edit.upload_queue.filter(queued => queued !== item);
    item.controller?.abort();
    item.placeholder.remove();
    nb_edit.end_upload(item);
};

nb_edit.end_upload = function (item, failed = false) {
    const { editor } = item;
    nb_edit.pending_uploads.delete(item);
    if (failed && item.placeholder.isConnected) {
        nb_edit.set_upload_state(item, 'error');
    } else {
        item.placeholder.remove();
        if (item.url) URL.revokeObjectURL(item.url);
    }
    if (!nb_edit.has_pending_uploads(editor)) editor.removeAttribute('aria-busy');
    nb.bubble_editor.update_empty(editor);
    nb_edit.deferred_saves.forEach((fn, scope) => {
        if (failed && scope.contains(editor)) {
            // never save silently without an image the user is waiting for
            nb_edit.deferred_saves.delete(scope);
            nb.notify(nb.text.save_upload_failed || 'Not saved yet: an image failed to upload.');
        } else if (!nb_edit.has_pending_uploads(scope)) {
            nb_edit.deferred_saves.delete(scope);
            fn();
        }
    });
};

nb_edit.has_changes = function () {
    return nb_edit.inputs > nb_edit.last_inputs;
}





export default nb_edit;
