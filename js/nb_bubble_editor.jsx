// In-house replacement for medium-editor: contenteditable + a floating
// "bubble" toolbar on text selection. Opt-in per browser while it is being
// built (?bubble_editor=1 / ?bubble_editor=0); nb_edit.init_editor() uses it
// instead of MediumEditor when enabled. No imports: specs load this file with
// its export stripped.

var nb_bubble_editor = {
    toolbar: null,
    current: null,
    saved_range: null,
    pointer_down: false
};

const link_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>';

// Keys are medium-editor's button names, so existing `buttons` settings in
// resource .meta files keep working unchanged.
nb_bubble_editor.button_registry = {
    bold: { type: 'toggle', label: 'Bold', icon: '<b>B</b>', command: 'bold', shortcut: 'b' },
    italic: { type: 'toggle', label: 'Italic', icon: '<i class="font-serif">I</i>', command: 'italic', shortcut: 'i' },
    h2: { type: 'block', label: 'Heading 2', icon: 'H2', tag: 'h2' },
    h3: { type: 'block', label: 'Heading 3', icon: 'H3', tag: 'h3' },
    h4: { type: 'block', label: 'Heading 4', icon: 'H4', tag: 'h4' },
    quote: { type: 'block', label: 'Quote', icon: '&ldquo;', tag: 'blockquote' },
    orderedlist: { type: 'list', label: 'Numbered list', icon: '1.', command: 'insertOrderedList' },
    unorderedlist: { type: 'list', label: 'Bulleted list', icon: '&bull;', command: 'insertUnorderedList' },
    anchor: { type: 'form', label: 'Link', icon: link_svg, shortcut: 'k' }
};

nb_bubble_editor.enabled = function () {
    try {
        const param = new URLSearchParams(window.location.search).get('bubble_editor');
        if (param === '1') {
            localStorage.setItem('nb_bubble_editor', '1');
        } else if (param === '0') {
            localStorage.removeItem('nb_bubble_editor');
        }
        return localStorage.getItem('nb_bubble_editor') === '1';
    } catch (e) {
        return false;
    }
}

nb_bubble_editor.init = function (ed, options) {
    const registry = nb_bubble_editor.button_registry;
    ed._nb_bubble = {
        buttons: options.buttons.filter((name) => { return registry[name]; }),
        paste_html: options.paste_html === true,
        as_form_field: options.as_form_field === true,
        handlers: {
            input: (e) => { nb_bubble_editor.on_input(e); },
            paste: (e) => { nb_bubble_editor.on_paste(e); },
            keydown: (e) => { nb_bubble_editor.on_keydown(e); },
            focus: (e) => { nb_bubble_editor.on_focus(e); },
            blur: (e) => { nb_bubble_editor.on_blur(e); }
        }
    };
    ed.setAttribute('contenteditable', true);
    ed.dataset.placeholder = options.placeholder;
    Object.entries(ed._nb_bubble.handlers).forEach(([type, handler]) => {
        ed.addEventListener(type, handler);
    });
    nb_bubble_editor.update_empty(ed);
    nb_bubble_editor.listen();
}

nb_bubble_editor.destroy = function (ed) {
    if (!ed._nb_bubble) {
        return;
    }
    Object.entries(ed._nb_bubble.handlers).forEach(([type, handler]) => {
        ed.removeEventListener(type, handler);
    });
    if (nb_bubble_editor.current === ed) {
        nb_bubble_editor.hide();
    }
    ed.removeAttribute('contenteditable');
    ed.removeAttribute('data-placeholder');
    ed.removeAttribute('data-nb-edit-empty');
    ed.removeAttribute('data-nb-edit-focused');
    delete ed._nb_bubble;
}

nb_bubble_editor.listen = function () {
    if (nb_bubble_editor.listening) {
        return;
    }
    nb_bubble_editor.listening = true;
    document.addEventListener('selectionchange', () => {
        requestAnimationFrame(nb_bubble_editor.update);
    });
    document.addEventListener('mousedown', (e) => {
        if (!nb_bubble_editor.toolbar || !nb_bubble_editor.toolbar.contains(e.target)) {
            nb_bubble_editor.pointer_down = true;
        }
    });
    document.addEventListener('mouseup', () => {
        nb_bubble_editor.pointer_down = false;
        requestAnimationFrame(nb_bubble_editor.update);
    });
    window.addEventListener('scroll', () => {
        if (nb_bubble_editor.current) {
            nb_bubble_editor.position();
        }
    }, true);
    window.addEventListener('resize', () => {
        if (nb_bubble_editor.current) {
            nb_bubble_editor.position();
        }
    });
}

/* editor events */

nb_bubble_editor.on_focus = function (e) {
    const ed = e.currentTarget;
    ed.setAttribute('data-nb-edit-focused', true);
    document.execCommand('defaultParagraphSeparator', false, 'p');
}

nb_bubble_editor.on_blur = function (e) {
    if (nb_bubble_editor.in_toolbar(e.relatedTarget)) {
        return;
    }
    e.currentTarget.removeAttribute('data-nb-edit-focused');
}

nb_bubble_editor.on_input = function (e) {
    const ed = e.currentTarget;
    nb_bubble_editor.ensure_paragraph(ed);
    nb_bubble_editor.update_empty(ed);
    if (ed._nb_bubble.as_form_field) {
        ed.dispatchEvent(new CustomEvent('nb:editor-change', {
            bubbles: true,
            detail: { value: ed.innerHTML.trim() }
        }));
    }
}

nb_bubble_editor.on_keydown = function (e) {
    const ed = e.currentTarget;
    if (!(e.metaKey || e.ctrlKey) || e.altKey || e.shiftKey) {
        return;
    }
    const key = e.key.toLowerCase();
    const name = Object.keys(nb_bubble_editor.button_registry).find((n) => {
        return nb_bubble_editor.button_registry[n].shortcut === key;
    });
    if (!name) {
        return;
    }
    e.preventDefault();
    if (ed._nb_bubble.buttons.includes(name)) {
        nb_bubble_editor.current = ed;
        nb_bubble_editor.exec(name);
    }
}

nb_bubble_editor.on_paste = function (e) {
    e.preventDefault();
    const data = e.clipboardData;
    if (!data) {
        return;
    }
    const ed = e.currentTarget;
    const html = ed._nb_bubble.paste_html ? data.getData('text/html') : '';
    const out = html ?
        nb_bubble_editor.clean_html(html)
        : nb_bubble_editor.plain_to_html(data.getData('text/plain'));
    if (out) {
        document.execCommand('insertHTML', false, out);
    }
}

/* content helpers */

nb_bubble_editor.is_empty = function (ed) {
    return ed.textContent.trim() === '' && !ed.querySelector('img, iframe, video, hr');
}

nb_bubble_editor.update_empty = function (ed) {
    if (nb_bubble_editor.is_empty(ed)) {
        ed.setAttribute('data-nb-edit-empty', true);
    } else {
        ed.removeAttribute('data-nb-edit-empty');
    }
}

// Typing into an empty editor produces a bare text node; wrap it in <p>
// (same guard as medium-editor: only when the editor has no child elements
// and is not itself a block such as an inline-editable <h1>).
nb_bubble_editor.block_tags = ['P', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'BLOCKQUOTE', 'PRE', 'UL', 'OL', 'LI',
    'ADDRESS', 'ARTICLE', 'ASIDE', 'DD', 'DL', 'DT', 'FIGCAPTION', 'FIGURE', 'FOOTER', 'HEADER', 'MAIN', 'NAV',
    'SECTION', 'TD', 'TH', 'TABLE', 'TBODY', 'THEAD', 'TFOOT', 'TR'];

nb_bubble_editor.ensure_paragraph = function (ed) {
    if (ed.children.length === 0 && ed.textContent.trim() !== ''
        && !nb_bubble_editor.block_tags.includes(ed.tagName)) {
        document.execCommand('formatBlock', false, 'p');
    }
}

nb_bubble_editor.escape = function (text) {
    const el = document.createElement('div');
    el.textContent = text;
    return el.innerHTML;
}

nb_bubble_editor.plain_to_html = function (text) {
    const lines = (text || '').split(/[\r\n]+/g).filter((line) => { return line.trim() !== ''; });
    if (lines.length < 2) {
        return nb_bubble_editor.escape(lines.join(''));
    }
    return lines.map((line) => { return '<p>' + nb_bubble_editor.escape(line) + '</p>'; }).join('');
}

// Strip office/browser cruft from pasted html. Real sanitizing happens
// server-side (sanitize_html_fields() in api.php).
nb_bubble_editor.clean_html = function (html) {
    const tpl = document.createElement('template');
    tpl.innerHTML = html;
    const root = tpl.content;
    root.querySelectorAll('meta, style, script, link, title, xml, o\\:p').forEach((el) => { el.remove(); });
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_COMMENT);
    const comments = [];
    while (walker.nextNode()) {
        comments.push(walker.currentNode);
    }
    comments.forEach((c) => { c.remove(); });

    root.querySelectorAll('span, b[id^="docs-internal-guid"]').forEach((el) => {
        const style = el.getAttribute('style') || '';
        let replacement = null;
        if (el.tagName === 'SPAN' && /font-weight:\s*(bold|[6-9]00)/i.test(style)) {
            replacement = document.createElement('b');
        } else if (el.tagName === 'SPAN' && /font-style:\s*italic/i.test(style)) {
            replacement = document.createElement('i');
        }
        if (replacement) {
            replacement.append(...el.childNodes);
            el.replaceWith(replacement);
        } else {
            el.replaceWith(...el.childNodes);
        }
    });
    root.querySelectorAll('*').forEach((el) => {
        ['class', 'style', 'dir', 'id'].forEach((attr) => { el.removeAttribute(attr); });
    });
    const div = document.createElement('div');
    div.append(root);
    return div.innerHTML.trim();
}

/* selection helpers */

nb_bubble_editor.in_toolbar = function (el) {
    return !!(el && nb_bubble_editor.toolbar && nb_bubble_editor.toolbar.contains(el));
}

nb_bubble_editor.editor_for_selection = function () {
    const sel = window.getSelection();
    if (!sel || sel.rangeCount === 0) {
        return null;
    }
    const node = sel.anchorNode;
    const el = node && (node.nodeType === Node.ELEMENT_NODE ? node : node.parentElement);
    const ed = el && el.closest('[data-nb-edit]');
    if (!ed || !ed._nb_bubble || !ed.isContentEditable || !ed.contains(sel.focusNode)) {
        return null;
    }
    return ed;
}

nb_bubble_editor.selected_link = function () {
    const sel = window.getSelection();
    if (!sel || sel.rangeCount === 0 || !nb_bubble_editor.current) {
        return null;
    }
    const node = sel.anchorNode;
    const el = node && (node.nodeType === Node.ELEMENT_NODE ? node : node.parentElement);
    const link = el && el.closest('a');
    return link && nb_bubble_editor.current.contains(link) ? link : null;
}

/* commands */

nb_bubble_editor.is_active = function (name) {
    const entry = nb_bubble_editor.button_registry[name];
    if (entry.type === 'block') {
        return document.queryCommandValue('formatBlock').toLowerCase() === entry.tag;
    }
    if (entry.type === 'form') {
        return nb_bubble_editor.selected_link() !== null;
    }
    return document.queryCommandState(entry.command);
}

nb_bubble_editor.exec = function (name) {
    const entry = nb_bubble_editor.button_registry[name];
    if (entry.type === 'block') {
        const tag = nb_bubble_editor.is_active(name) ? 'p' : entry.tag;
        document.execCommand('formatBlock', false, '<' + tag + '>');
    } else if (entry.type === 'list') {
        document.execCommand(entry.command);
        nb_bubble_editor.unwrap_list();
    } else if (entry.type === 'form') {
        const link = nb_bubble_editor.selected_link();
        if (link) {
            const range = document.createRange();
            range.selectNodeContents(link);
            const sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(range);
            document.execCommand('unlink');
        } else {
            nb_bubble_editor.open_link_form();
            return;
        }
    } else {
        document.execCommand(entry.command);
    }
    nb_bubble_editor.update();
}

// Chrome nests a new list inside the <p> it came from
nb_bubble_editor.unwrap_list = function () {
    const sel = window.getSelection();
    const node = sel && sel.anchorNode;
    const el = node && (node.nodeType === Node.ELEMENT_NODE ? node : node.parentElement);
    const list = el && el.closest('ul, ol');
    const p = list && list.parentElement;
    if (!p || p.tagName !== 'P' || !nb_bubble_editor.current || !nb_bubble_editor.current.contains(p)) {
        return;
    }
    const r = sel.getRangeAt(0);
    const bounds = [r.startContainer, r.startOffset, r.endContainer, r.endOffset];
    p.replaceWith(...p.childNodes);
    const range = document.createRange();
    range.setStart(bounds[0], bounds[1]);
    range.setEnd(bounds[2], bounds[3]);
    sel.removeAllRanges();
    sel.addRange(range);
    nb_bubble_editor.current.dispatchEvent(new Event('input', { bubbles: true }));
}

nb_bubble_editor.normalize_url = function (url) {
    url = url.trim();
    if (url && !/^([a-z][a-z0-9+.-]*:|\/|#|\?)/i.test(url) && /^[^\s\/]+\.[a-z]{2,}(\/|$)/i.test(url)) {
        return 'https://' + url;
    }
    return url;
}

nb_bubble_editor.open_link_form = function () {
    const sel = window.getSelection();
    if (!sel || sel.rangeCount === 0) {
        return;
    }
    nb_bubble_editor.saved_range = sel.getRangeAt(0).cloneRange();
    const tb = nb_bubble_editor.get_toolbar();
    tb.querySelector('[data-nb-bubble-buttons]').classList.add('hidden');
    tb.querySelector('[data-nb-bubble-link]').classList.remove('hidden');
    tb.classList.remove('hidden');
    const input = tb.querySelector('[data-nb-bubble-link] input');
    input.value = '';
    nb_bubble_editor.position();
    input.focus();
}

nb_bubble_editor.close_link_form = function (url) {
    const range = nb_bubble_editor.saved_range;
    const ed = nb_bubble_editor.current;
    nb_bubble_editor.saved_range = null;
    const tb = nb_bubble_editor.get_toolbar();
    tb.querySelector('[data-nb-bubble-link]').classList.add('hidden');
    tb.querySelector('[data-nb-bubble-buttons]').classList.remove('hidden');
    if (!range || !ed) {
        nb_bubble_editor.hide();
        return;
    }
    ed.focus();
    const sel = window.getSelection();
    sel.removeAllRanges();
    sel.addRange(range);
    url = nb_bubble_editor.normalize_url(url || '');
    if (url) {
        document.execCommand('createLink', false, url);
    }
    nb_bubble_editor.update();
}

/* toolbar */

nb_bubble_editor.get_toolbar = function () {
    if (nb_bubble_editor.toolbar) {
        return nb_bubble_editor.toolbar;
    }
    const tb = document.createElement('div');
    tb.className = 'nb-bubble-toolbar hidden';
    tb.setAttribute('role', 'toolbar');
    tb.innerHTML = '<span class="nb-bubble-arrow" aria-hidden="true"></span>'
        + '<div class="join" data-nb-bubble-buttons></div>'
        + '<form class="join hidden" data-nb-bubble-link>'
        + '<input type="text" class="input input-sm join-item nb-bubble-input" placeholder="https://" aria-label="Link">'
        + '<button type="submit" class="btn btn-sm btn-square join-item nb-bubble-btn" aria-label="Apply">&#10003;</button>'
        + '<button type="button" class="btn btn-sm btn-square join-item nb-bubble-btn" aria-label="Cancel" data-nb-bubble-cancel>&times;</button>'
        + '</form>';
    // mousedown + preventDefault keeps focus (and the selection) in the editor
    tb.addEventListener('mousedown', (e) => {
        const btn = e.target.closest('[data-nb-bubble-name]');
        if (btn) {
            e.preventDefault();
            nb_bubble_editor.exec(btn.dataset.nbBubbleName);
        } else if (e.target.closest('[data-nb-bubble-cancel]')) {
            e.preventDefault();
            nb_bubble_editor.close_link_form('');
        }
    });
    const form = tb.querySelector('[data-nb-bubble-link]');
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        nb_bubble_editor.close_link_form(form.querySelector('input').value);
    });
    form.querySelector('input').addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            e.preventDefault();
            nb_bubble_editor.close_link_form('');
        }
    });
    form.querySelector('input').addEventListener('blur', (e) => {
        if (nb_bubble_editor.saved_range && !nb_bubble_editor.in_toolbar(e.relatedTarget)) {
            nb_bubble_editor.saved_range = null;
            form.classList.add('hidden');
            tb.querySelector('[data-nb-bubble-buttons]').classList.remove('hidden');
            nb_bubble_editor.hide();
        }
    });
    document.body.appendChild(tb);
    nb_bubble_editor.toolbar = tb;
    return tb;
}

nb_bubble_editor.render_buttons = function (ed) {
    const container = nb_bubble_editor.get_toolbar().querySelector('[data-nb-bubble-buttons]');
    container.innerHTML = '';
    ed._nb_bubble.buttons.forEach((name) => {
        const entry = nb_bubble_editor.button_registry[name];
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-sm btn-square join-item nb-bubble-btn';
        btn.dataset.nbBubbleName = name;
        btn.setAttribute('aria-label', entry.label);
        btn.setAttribute('title', entry.label);
        btn.setAttribute('aria-pressed', 'false');
        btn.innerHTML = entry.icon;
        container.appendChild(btn);
    });
}

nb_bubble_editor.update = function () {
    if (nb_bubble_editor.saved_range) {
        return; // link form is open
    }
    const ed = nb_bubble_editor.editor_for_selection();
    const sel = window.getSelection();
    if (!ed || sel.isCollapsed || nb_bubble_editor.pointer_down || ed._nb_bubble.buttons.length === 0) {
        if (!nb_bubble_editor.in_toolbar(document.activeElement)) {
            nb_bubble_editor.hide();
        }
        return;
    }
    const tb = nb_bubble_editor.get_toolbar();
    if (nb_bubble_editor.current !== ed || tb.classList.contains('hidden')) {
        nb_bubble_editor.render_buttons(ed);
    }
    nb_bubble_editor.current = ed;
    tb.querySelectorAll('[data-nb-bubble-name]').forEach((btn) => {
        const active = nb_bubble_editor.is_active(btn.dataset.nbBubbleName);
        btn.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
    tb.classList.remove('hidden');
    nb_bubble_editor.position();
}

nb_bubble_editor.hide = function () {
    if (nb_bubble_editor.toolbar) {
        nb_bubble_editor.toolbar.classList.add('hidden');
    }
    nb_bubble_editor.current = null;
}

nb_bubble_editor.position = function () {
    const tb = nb_bubble_editor.toolbar;
    const range = nb_bubble_editor.saved_range
        || (window.getSelection().rangeCount > 0 ? window.getSelection().getRangeAt(0) : null);
    if (!tb || !range) {
        return;
    }
    let rect = range.getBoundingClientRect();
    if (rect.width === 0 && rect.height === 0 && range.getClientRects().length > 0) {
        rect = range.getClientRects()[0];
    }
    const gap = 8;
    const w = tb.offsetWidth;
    const h = tb.offsetHeight;
    let top = rect.top - h - gap;
    const below = top < gap;
    if (below) {
        top = rect.bottom + gap;
    }
    const center = rect.left + rect.width / 2;
    let left = center - w / 2;
    left = Math.max(gap, Math.min(left, window.innerWidth - w - gap));
    tb.style.top = top + 'px';
    tb.style.left = left + 'px';
    // arrow points at the middle of the selection, kept clear of the rounded corners
    const arrow_x = Math.max(12, Math.min(center - left, w - 12));
    tb.querySelector('.nb-bubble-arrow').style.left = arrow_x + 'px';
    tb.classList.toggle('nb-bubble-below', below);
}

export default nb_bubble_editor;
