// In-house replacement for medium-editor: contenteditable + a floating
// "bubble" toolbar on text selection. Opt-in per browser while it is being
// built (?bubble_editor=1 / ?bubble_editor=0); nb_edit.init_editor() uses it
// instead of MediumEditor when enabled. No imports: specs load this file with
// its export stripped.
//
// Buttons are registered by name; a field's `buttons` option (resource .meta)
// picks which ones it shows. Each button carries its own behaviour:
//   { label, icon, shortcut?, run(ctx), is_active(ctx)?, prompt?, apply(ctx, value)? }
// Most buttons are built from a kind (command, block, list, wrap, insert,
// link). Applications add buttons declaratively in the `bubble-editor-buttons`
// template (JSON, see core/tpl/bubble-editor-buttons) or from script with
// nb.bubble_editor.register(name, definition).

var nb_bubble_editor = {
    buttons: {},
    toolbar: null,
    current: null,
    prompt: null,
    pointer_down: false
};

/* document editing adapter */

// The only place that calls document.execCommand / queryCommand*. Buttons,
// paste and typing go through these functions, so any of them can later be
// reimplemented (e.g. with Range/DOM code) without touching anything else.
// execCommand is kept for now because it gives native undo/redo.
nb_bubble_editor.doc = {
    inline: (command) => { document.execCommand(command); },
    inline_active: (command) => document.queryCommandState(command),
    block: (tag) => { document.execCommand('formatBlock', false, '<' + tag + '>'); },
    current_block: () => document.queryCommandValue('formatBlock').toLowerCase(),
    list: (command) => { document.execCommand(command); },
    list_active: (command) => document.queryCommandState(command),
    insert_html: (html) => { document.execCommand('insertHTML', false, html); },
    link: (url) => { document.execCommand('createLink', false, url); },
    unlink: () => { document.execCommand('unlink'); },
    paragraph_separator: (tag) => { document.execCommand('defaultParagraphSeparator', false, tag); },
    // Chrome's insertHTML drops inline wrappers such as <mark>/<span>, so
    // wrapping is plain DOM work (not part of native undo)
    wrap: (tag, class_name) => {
        const range = window.getSelection().getRangeAt(0);
        const el = document.createElement(tag);
        if (class_name) {
            el.className = class_name;
        }
        el.append(range.extractContents());
        range.insertNode(el);
        const selected = document.createRange();
        selected.selectNodeContents(el);
        nb_bubble_editor.select(selected);
        nb_bubble_editor.changed();
    },
    unwrap: (el) => {
        const first = el.firstChild;
        const last = el.lastChild;
        el.replaceWith(...el.childNodes);
        if (first) {
            const selected = document.createRange();
            selected.setStartBefore(first);
            selected.setEndAfter(last);
            nb_bubble_editor.select(selected);
        }
        nb_bubble_editor.changed();
    }
};

// let the editor's input handling know about a change made outside the browser's editing commands
nb_bubble_editor.changed = function () {
    if (nb_bubble_editor.current) {
        nb_bubble_editor.current.dispatchEvent(new Event('input', { bubbles: true }));
    }
}

/* button kinds */

nb_bubble_editor.kinds = {
    // native formatting command, e.g. bold, italic, strikethrough
    command: (def) => ({
        run: () => { nb_bubble_editor.doc.inline(def.command); },
        is_active: () => nb_bubble_editor.doc.inline_active(def.command)
    }),
    // block format; clicking it again turns the block back into a paragraph
    block: (def) => ({
        run: (ctx) => {
            const tag = nb_bubble_editor.block_active(def.tag) ? 'p' : def.tag;
            nb_bubble_editor.doc.block(tag);
        },
        is_active: () => nb_bubble_editor.block_active(def.tag)
    }),
    list: (def) => ({
        run: (ctx) => {
            nb_bubble_editor.doc.list(def.command);
            nb_bubble_editor.unwrap_list(ctx.editor);
        },
        is_active: () => nb_bubble_editor.doc.list_active(def.command)
    }),
    // wrap the selection in an inline element (e.g. <mark>, <span class="...">)
    wrap: (def) => {
        const selector = def.tag + (def.class ? '.' + def.class.trim().split(/\s+/).join('.') : '');
        return {
            run: (ctx) => {
                const el = nb_bubble_editor.closest(ctx, selector);
                if (el) {
                    nb_bubble_editor.doc.unwrap(el);
                } else {
                    nb_bubble_editor.doc.wrap(def.tag, def.class);
                }
            },
            is_active: (ctx) => nb_bubble_editor.closest(ctx, selector) !== null
        };
    },
    insert: (def) => ({
        run: () => { nb_bubble_editor.doc.insert_html(def.html); },
        is_active: () => false
    }),
    // asks for a URL; on an existing link it removes the link instead
    link: (def) => ({
        prompt: { placeholder: def.placeholder || 'https://' },
        run: (ctx) => {
            const link = nb_bubble_editor.closest(ctx, 'a');
            if (!link) {
                nb_bubble_editor.open_prompt(ctx.name);
                return;
            }
            const range = document.createRange();
            range.selectNodeContents(link);
            nb_bubble_editor.select(range);
            nb_bubble_editor.doc.unlink();
        },
        apply: (ctx, value) => {
            const url = nb_bubble_editor.normalize_url(value);
            if (url) {
                nb_bubble_editor.doc.link(url);
            }
        },
        is_active: (ctx) => nb_bubble_editor.closest(ctx, 'a') !== null
    })
};

nb_bubble_editor.register = function (name, def) {
    const kind = def.kind ? nb_bubble_editor.kinds[def.kind] : null;
    if (def.kind && !kind) {
        console.warn('nb_bubble_editor: unknown button kind', def.kind, name);
        return;
    }
    nb_bubble_editor.buttons[name] = Object.assign({ label: name, icon: name }, kind ? kind(def) : {}, def);
}

const link_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>';

// Names match medium-editor's, so existing `buttons` settings keep working.
[
    ['bold', { kind: 'command', command: 'bold', label: 'Bold', icon: '<b>B</b>', shortcut: 'b' }],
    ['italic', { kind: 'command', command: 'italic', label: 'Italic', icon: '<i class="font-serif">I</i>', shortcut: 'i' }],
    ['h2', { kind: 'block', tag: 'h2', label: 'Heading 2', icon: 'H2' }],
    ['h3', { kind: 'block', tag: 'h3', label: 'Heading 3', icon: 'H3' }],
    ['h4', { kind: 'block', tag: 'h4', label: 'Heading 4', icon: 'H4' }],
    ['quote', { kind: 'block', tag: 'blockquote', label: 'Quote', icon: '&ldquo;' }],
    ['orderedlist', { kind: 'list', command: 'insertOrderedList', label: 'Numbered list', icon: '1.' }],
    ['unorderedlist', { kind: 'list', command: 'insertUnorderedList', label: 'Bulleted list', icon: '&bull;' }],
    ['anchor', { kind: 'link', label: 'Link', icon: link_svg, shortcut: 'k' }]
].forEach(([name, def]) => { nb_bubble_editor.register(name, def); });

// application buttons declared in the bubble-editor-buttons template
nb_bubble_editor.load_declared_buttons = function () {
    const el = document.getElementById('nb_bubble_buttons');
    if (!el || el._nb_loaded) {
        return;
    }
    el._nb_loaded = true;
    try {
        const declared = JSON.parse(el.textContent.trim() || '{}');
        Object.entries(declared).forEach(([name, def]) => { nb_bubble_editor.register(name, def); });
    } catch (e) {
        console.warn('nb_bubble_editor: invalid bubble-editor-buttons JSON', e);
    }
}

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
    nb_bubble_editor.load_declared_buttons();
    ed._nb_bubble = {
        buttons: options.buttons.filter((name) => { return nb_bubble_editor.buttons[name]; }),
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
        if (!nb_bubble_editor.in_toolbar(e.target)) {
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
    nb_bubble_editor.doc.paragraph_separator('p');
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
    const name = Object.keys(nb_bubble_editor.buttons).find((n) => {
        return nb_bubble_editor.buttons[n].shortcut === key;
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
        nb_bubble_editor.doc.insert_html(out);
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
        nb_bubble_editor.doc.block('p');
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

nb_bubble_editor.element_of = function (node) {
    return node && (node.nodeType === Node.ELEMENT_NODE ? node : node.parentElement);
}

nb_bubble_editor.select = function (range) {
    const sel = window.getSelection();
    sel.removeAllRanges();
    sel.addRange(range);
}

nb_bubble_editor.editor_for_selection = function () {
    const sel = window.getSelection();
    if (!sel || sel.rangeCount === 0) {
        return null;
    }
    const el = nb_bubble_editor.element_of(sel.anchorNode);
    const ed = el && el.closest('[data-nb-edit]');
    if (!ed || !ed._nb_bubble || !ed.isContentEditable || !ed.contains(sel.focusNode)) {
        return null;
    }
    return ed;
}

// what a button's run/is_active/apply receive
nb_bubble_editor.context = function (name) {
    const sel = window.getSelection();
    const range = sel && sel.rangeCount > 0 ? sel.getRangeAt(0) : null;
    return {
        name: name,
        editor: nb_bubble_editor.current,
        range: range,
        element: range ? nb_bubble_editor.element_of(range.startContainer) : null
    };
}

// nearest ancestor of the selection matching selector, inside the editor
nb_bubble_editor.closest = function (ctx, selector) {
    const el = ctx.element && ctx.element.closest(selector);
    return el && ctx.editor && ctx.editor.contains(el) && el !== ctx.editor ? el : null;
}

nb_bubble_editor.block_active = function (tag) {
    return nb_bubble_editor.doc.current_block() === tag;
}

// Chrome nests a new list inside the <p> it came from
nb_bubble_editor.unwrap_list = function (ed) {
    const sel = window.getSelection();
    const el = nb_bubble_editor.element_of(sel && sel.anchorNode);
    const list = el && el.closest('ul, ol');
    const p = list && list.parentElement;
    if (!p || p.tagName !== 'P' || !ed || !ed.contains(p)) {
        return;
    }
    const r = sel.getRangeAt(0);
    const bounds = [r.startContainer, r.startOffset, r.endContainer, r.endOffset];
    p.replaceWith(...p.childNodes);
    const range = document.createRange();
    range.setStart(bounds[0], bounds[1]);
    range.setEnd(bounds[2], bounds[3]);
    nb_bubble_editor.select(range);
    nb_bubble_editor.changed();
}

nb_bubble_editor.normalize_url = function (url) {
    url = (url || '').trim();
    if (url && !/^([a-z][a-z0-9+.-]*:|\/|#|\?)/i.test(url) && /^[^\s\/]+\.[a-z]{2,}(\/|$)/i.test(url)) {
        return 'https://' + url;
    }
    return url;
}

/* running buttons */

nb_bubble_editor.exec = function (name) {
    const button = nb_bubble_editor.buttons[name];
    if (!button || !nb_bubble_editor.current) {
        return;
    }
    button.run(nb_bubble_editor.context(name));
    nb_bubble_editor.update();
}

// swap the buttons for a one-line input; used by buttons with a `prompt`
nb_bubble_editor.open_prompt = function (name) {
    const button = nb_bubble_editor.buttons[name];
    const ctx = nb_bubble_editor.context(name);
    if (!button || !button.prompt || !ctx.range) {
        return;
    }
    nb_bubble_editor.prompt = { name: name, range: ctx.range.cloneRange() };
    const tb = nb_bubble_editor.get_toolbar();
    const input = tb.querySelector('[data-nb-bubble-prompt] input');
    input.value = '';
    input.placeholder = button.prompt.placeholder || '';
    input.setAttribute('aria-label', button.label);
    nb_bubble_editor.show_prompt(true);
    tb.classList.remove('hidden');
    nb_bubble_editor.position();
    input.focus();
}

nb_bubble_editor.show_prompt = function (show) {
    const tb = nb_bubble_editor.get_toolbar();
    tb.querySelector('[data-nb-bubble-buttons]').classList.toggle('hidden', show);
    tb.querySelector('[data-nb-bubble-prompt]').classList.toggle('hidden', !show);
}

// value === null cancels
nb_bubble_editor.close_prompt = function (value) {
    const prompt = nb_bubble_editor.prompt;
    const ed = nb_bubble_editor.current;
    nb_bubble_editor.prompt = null;
    nb_bubble_editor.show_prompt(false);
    if (!prompt || !ed) {
        nb_bubble_editor.hide();
        return;
    }
    ed.focus();
    nb_bubble_editor.select(prompt.range);
    if (value !== null) {
        nb_bubble_editor.buttons[prompt.name].apply(nb_bubble_editor.context(prompt.name), value);
    }
    nb_bubble_editor.update();
}

/* toolbar (markup: core/tpl/bubble-editor) */

nb_bubble_editor.get_toolbar = function () {
    if (nb_bubble_editor.toolbar) {
        return nb_bubble_editor.toolbar;
    }
    const tpl = document.getElementById('nb_bubble_toolbar');
    if (!tpl) {
        return null;
    }
    const tb = tpl.content.firstElementChild.cloneNode(true);
    // mousedown + preventDefault keeps focus (and the selection) in the editor
    tb.addEventListener('mousedown', (e) => {
        const btn = e.target.closest('[data-nb-bubble-name]');
        if (btn) {
            e.preventDefault();
            nb_bubble_editor.exec(btn.dataset.nbBubbleName);
        } else if (e.target.closest('[data-nb-bubble-cancel]')) {
            e.preventDefault();
            nb_bubble_editor.close_prompt(null);
        }
    });
    const form = tb.querySelector('[data-nb-bubble-prompt]');
    const input = form.querySelector('input');
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        nb_bubble_editor.close_prompt(input.value);
    });
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            e.preventDefault();
            nb_bubble_editor.close_prompt(null);
        }
    });
    input.addEventListener('blur', (e) => {
        if (nb_bubble_editor.prompt && !nb_bubble_editor.in_toolbar(e.relatedTarget)) {
            nb_bubble_editor.prompt = null;
            nb_bubble_editor.show_prompt(false);
            nb_bubble_editor.hide();
        }
    });
    document.body.appendChild(tb);
    nb_bubble_editor.toolbar = tb;
    return tb;
}

nb_bubble_editor.render_buttons = function (ed) {
    const tb = nb_bubble_editor.get_toolbar();
    const container = tb.querySelector('[data-nb-bubble-buttons]');
    const button_tpl = document.getElementById('nb_bubble_button');
    container.innerHTML = '';
    ed._nb_bubble.buttons.forEach((name) => {
        const button = nb_bubble_editor.buttons[name];
        const btn = button_tpl.content.firstElementChild.cloneNode(true);
        btn.dataset.nbBubbleName = name;
        btn.setAttribute('aria-label', button.label);
        btn.setAttribute('title', button.label);
        btn.innerHTML = button.icon;
        container.appendChild(btn);
    });
}

nb_bubble_editor.update = function () {
    if (nb_bubble_editor.prompt) {
        return;
    }
    const ed = nb_bubble_editor.editor_for_selection();
    const sel = window.getSelection();
    const tb = ed ? nb_bubble_editor.get_toolbar() : nb_bubble_editor.toolbar;
    if (!tb || !ed || sel.isCollapsed || nb_bubble_editor.pointer_down || ed._nb_bubble.buttons.length === 0) {
        if (!nb_bubble_editor.in_toolbar(document.activeElement)) {
            nb_bubble_editor.hide();
        }
        return;
    }
    if (nb_bubble_editor.current !== ed || tb.classList.contains('hidden')) {
        nb_bubble_editor.render_buttons(ed);
    }
    nb_bubble_editor.current = ed;
    tb.querySelectorAll('[data-nb-bubble-name]').forEach((btn) => {
        const name = btn.dataset.nbBubbleName;
        const button = nb_bubble_editor.buttons[name];
        const active = button.is_active ? button.is_active(nb_bubble_editor.context(name)) : false;
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
    const sel = window.getSelection();
    const range = nb_bubble_editor.prompt ? nb_bubble_editor.prompt.range
        : (sel.rangeCount > 0 ? sel.getRangeAt(0) : null);
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
