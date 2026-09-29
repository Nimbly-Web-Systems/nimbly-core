// The rich text editor: contenteditable plus a floating "bubble" toolbar on
// text selection (the field bar, nb_field_bar.jsx, holds the same buttons).
// nb_edit.init_editor() sets it up for every rich text field. No imports:
// specs load this file with its export stripped.
//
// Buttons are registered by name; a field's `buttons` option (resource .meta)
// picks which ones it shows. Each button carries its own behaviour:
//   { label, icon, shortcut?, insert?, run(ctx), is_active(ctx)?, prompt?, apply(ctx, value)? }
// `insert: true` puts a button in the field bar's insert group (next to Media)
// instead of with the formatting buttons and the bubble; add `bubble: true`
// when it also acts on selected text, to show it in the bubble as well.
// Most buttons are built from a kind (command, block, list, wrap, insert,
// event, link). Applications add buttons declaratively in the `bubble-editor-buttons`
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
    inline: (command) => {
        document.execCommand(command);
        // Chrome writes the obsolete <strike>; store <s>
        const ed = nb_bubble_editor.current;
        if (command === 'strikeThrough' && ed && ed.querySelector('strike')) {
            const sel = window.getSelection();
            const r = sel.rangeCount > 0 ? sel.getRangeAt(0) : null;
            const bounds = r ? [r.startContainer, r.startOffset, r.endContainer, r.endOffset] : null;
            ed.querySelectorAll('strike').forEach((el) => {
                const s = document.createElement('s');
                s.append(...el.childNodes);
                el.replaceWith(s);
            });
            if (bounds) {
                const range = document.createRange();
                range.setStart(bounds[0], bounds[1]);
                range.setEnd(bounds[2], bounds[3]);
                nb_bubble_editor.select(range);
            }
            nb_bubble_editor.changed();
        }
    },
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
    set_attribute: (el, name, value) => {
        el.setAttribute(name, value);
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

// what the link tools treat as a link: anchors with a URL (apps may use <a> without href
// for their own inline elements, e.g. jereis points of interest, with their own controls)
nb_bubble_editor.link_selector = 'a[href]';

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
    // hands over to application code: fires def.event on the field (bubbling), e.g. to open a picker;
    // def.active is an optional selector that marks the button active when the caret is inside a match
    event: (def) => ({
        run: (ctx) => {
            ctx.editor.dispatchEvent(new CustomEvent(def.event, {
                bubbles: true,
                detail: { editor: ctx.editor, range: ctx.range }
            }));
        },
        is_active: (ctx) => def.active ? nb_bubble_editor.closest(ctx, def.active) !== null : false
    }),
    // asks for a URL; on an existing link it edits that link (the whole link, prefilled)
    link: (def) => ({
        prompt: {
            placeholder: def.placeholder || 'https://',
            target: (ctx) => nb_bubble_editor.closest(ctx, nb_bubble_editor.link_selector),
            initial: (target) => target ? (target.getAttribute('href') || '') : ''
        },
        run: (ctx) => { nb_bubble_editor.open_prompt(ctx.name); },
        apply: (ctx, value) => {
            const url = nb_bubble_editor.normalize_url(value);
            if (!url) {
                if (ctx.target) {
                    nb_bubble_editor.doc.unlink(); // emptied the URL of an existing link
                }
                return;
            }
            if (ctx.target) {
                // editing an existing link: change its URL (Firefox's createLink would nest a new <a> inside it)
                nb_bubble_editor.doc.set_attribute(ctx.target, 'href', url);
            } else if (ctx.range && ctx.range.collapsed) {
                const a = document.createElement('a');
                a.href = url;
                a.textContent = url;
                nb_bubble_editor.doc.insert_html(a.outerHTML);
            } else {
                nb_bubble_editor.doc.link(url);
            }
        },
        remove: () => { nb_bubble_editor.doc.unlink(); },
        is_active: (ctx) => nb_bubble_editor.closest(ctx, nb_bubble_editor.link_selector) !== null
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

// Lucide "quote" (ISC), filled
const quote_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-3"><path d="M16 3a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2 1 1 0 0 1 1 1v1a2 2 0 0 1-2 2 1 1 0 0 0-1 1v2a1 1 0 0 0 1 1 6 6 0 0 0 6-6V5a2 2 0 0 0-2-2z"/><path d="M5 3a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2 1 1 0 0 1 1 1v1a2 2 0 0 1-2 2 1 1 0 0 0-1 1v2a1 1 0 0 0 1 1 6 6 0 0 0 6-6V5a2 2 0 0 0-2-2z"/></svg>';

// Lucide "remove-formatting" (ISC)
const clear_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M4 7V4h16v3"/><path d="M5 20h6"/><path d="M13 4 8 20"/><path d="m15 15 5 5"/><path d="m20 15-5 5"/></svg>';

// Names match medium-editor's, so existing `buttons` settings keep working.
[
    ['bold', { kind: 'command', command: 'bold', label: 'Bold', icon: '<b>B</b>', shortcut: 'b' }],
    ['italic', { kind: 'command', command: 'italic', label: 'Italic', icon: '<i class="font-serif">I</i>', shortcut: 'i' }],
    ['h2', { kind: 'block', tag: 'h2', label: 'Heading 2', icon: 'H2' }],
    ['h3', { kind: 'block', tag: 'h3', label: 'Heading 3', icon: 'H3' }],
    ['h4', { kind: 'block', tag: 'h4', label: 'Heading 4', icon: 'H4' }],
    ['quote', { kind: 'block', tag: 'blockquote', label: 'Quote', icon: quote_svg }],
    ['orderedlist', { kind: 'list', command: 'insertOrderedList', label: 'Numbered list', icon: '1.' }],
    ['unorderedlist', { kind: 'list', command: 'insertUnorderedList', label: 'Bulleted list', icon: '&bull;' }],
    ['anchor', { kind: 'link', label: 'Link', icon: link_svg, shortcut: 'k' }],
    // available, but in no default set: fields opt in via their `buttons`
    ['strikethrough', { kind: 'command', command: 'strikeThrough', label: 'Strikethrough', icon: '<s>S</s>' }],
    ['subscript', { kind: 'command', command: 'subscript', label: 'Subscript', icon: 'x<sub>2</sub>' }],
    ['superscript', { kind: 'command', command: 'superscript', label: 'Superscript', icon: 'x<sup>2</sup>' }],
    ['underline', { kind: 'command', command: 'underline', label: 'Underline', icon: '<u>U</u>', shortcut: 'u' }],
    ['pre', { kind: 'block', tag: 'pre', label: 'Preformatted', icon: '{ }' }],
    ['removeFormat', { kind: 'command', command: 'removeFormat', label: 'Clear formatting', icon: clear_svg }]
].forEach(([name, def]) => { nb_bubble_editor.register(name, def); });

// translated labels and application buttons, from the bubble-editor templates
nb_bubble_editor.load_declared_buttons = function () {
    const el = document.getElementById('nb_bubble_buttons');
    if (!el || el._nb_loaded) {
        return;
    }
    el._nb_loaded = true;
    // translated labels of the built-in buttons
    const labels = document.getElementById('nb_bubble_labels');
    if (labels) {
        labels.content.querySelectorAll('[data-name]').forEach((span) => {
            const button = nb_bubble_editor.buttons[span.dataset.name];
            if (button && span.textContent.trim() !== '') {
                button.label = span.textContent.trim();
            }
        });
    }
    try {
        const declared = JSON.parse(el.textContent.trim() || '{}');
        Object.entries(declared).forEach(([name, def]) => { nb_bubble_editor.register(name, def); });
    } catch (e) {
        console.warn('nb_bubble_editor: invalid bubble-editor-buttons JSON', e);
    }
}

nb_bubble_editor.init = function (ed, options) {
    nb_bubble_editor.load_declared_buttons();
    ed._nb_bubble = {
        buttons: options.buttons.filter((name) => { return nb_bubble_editor.buttons[name]; }),
        // the bubble formats a selection; insert-at-caret buttons live in the field bar only
        bubble_buttons: options.buttons.filter((name) => {
            const button = nb_bubble_editor.buttons[name];
            return button && (!button.insert || button.bubble === true);
        }),
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
        requestAnimationFrame(() => {
            nb_bubble_editor.remember_range();
            nb_bubble_editor.update();
            nb_bubble_editor.update_preview();
        });
    });
    document.addEventListener('mouseover', nb_bubble_editor.on_mouseover);
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
        nb_bubble_editor.hide_preview();
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

    root.querySelectorAll('span, font, b[id^="docs-internal-guid"]').forEach((el) => {
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
    // keep only attributes content needs; drops Word/Docs class, style, lang, align, ...
    const keep = { A: ['href'], IMG: ['src', 'alt', 'width', 'height'], TD: ['colspan', 'rowspan'], TH: ['colspan', 'rowspan'] };
    root.querySelectorAll('*').forEach((el) => {
        const allowed = keep[el.tagName] || [];
        Array.from(el.attributes).forEach((attr) => {
            if (!allowed.includes(attr.name)) {
                el.removeAttribute(attr.name);
            }
        });
    });
    // Word spaces paragraphs with empty ones (<p>&nbsp;</p>)
    root.querySelectorAll('p').forEach((p) => {
        if (p.textContent.replace(/\u00a0/g, ' ').trim() === '' && !p.querySelector('img, iframe, video')) {
            p.remove();
        }
    });
    const div = document.createElement('div');
    div.append(root);
    return div.innerHTML.trim();
}

/* selection helpers */

// the last selection inside each editor, so keyboard use of a toolbar can return to it
nb_bubble_editor.remember_range = function () {
    const ed = nb_bubble_editor.editor_for_selection();
    if (ed) {
        ed._nb_bubble.range = window.getSelection().getRangeAt(0).cloneRange();
    }
}

// focus ed again with its last selection (after a toolbar button was used from the keyboard)
nb_bubble_editor.refocus = function (ed) {
    ed.focus();
    const range = ed._nb_bubble && ed._nb_bubble.range;
    if (range && ed.contains(range.startContainer) && ed.contains(range.endContainer)) {
        nb_bubble_editor.select(range);
    }
}

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

// ed: the editor to act on when not triggered from the bubble (e.g. the field bar)
nb_bubble_editor.exec = function (name, ed) {
    if (ed) {
        nb_bubble_editor.current = ed;
    }
    const button = nb_bubble_editor.buttons[name];
    if (!button || !nb_bubble_editor.current) {
        return;
    }
    button.run(nb_bubble_editor.context(name));
    nb_bubble_editor.remember_range(); // the DOM changed: don't wait for selectionchange
    nb_bubble_editor.update();
}

// swap the buttons for a one-line input; used by buttons with a `prompt`
// swap the buttons for a one-line input; used by buttons with a `prompt`.
// prompt.target(ctx) may name an existing element to edit: the input starts
// from prompt.initial(target) and the button's remove() is offered.
nb_bubble_editor.open_prompt = function (name) {
    const button = nb_bubble_editor.buttons[name];
    const ctx = nb_bubble_editor.context(name);
    if (!button || !button.prompt || !ctx.range) {
        return;
    }
    const target = button.prompt.target ? button.prompt.target(ctx) : null;
    let range = ctx.range.cloneRange();
    if (target) {
        range = document.createRange();
        range.selectNodeContents(target);
    }
    nb_bubble_editor.hide_preview();
    nb_bubble_editor.prompt = { name: name, range: range, target: target };
    const tb = nb_bubble_editor.get_toolbar();
    const input = tb.querySelector('[data-nb-bubble-prompt] input');
    input.value = button.prompt.initial ? button.prompt.initial(target) : '';
    input.placeholder = button.prompt.placeholder || '';
    input.setAttribute('aria-label', button.label);
    tb.querySelector('[data-nb-bubble-remove]').classList.toggle('hidden', !(target && button.remove));
    nb_bubble_editor.show_prompt(true);
    tb.classList.remove('hidden');
    nb_bubble_editor.position();
    input.focus();
    input.select();
}

nb_bubble_editor.show_prompt = function (show) {
    const tb = nb_bubble_editor.get_toolbar();
    tb.querySelector('[data-nb-bubble-buttons]').classList.toggle('hidden', show);
    tb.querySelector('[data-nb-bubble-prompt]').classList.toggle('hidden', !show);
}

// value === null cancels; remove === true runs the button's remove() on the target
nb_bubble_editor.close_prompt = function (value, remove) {
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
    const button = nb_bubble_editor.buttons[prompt.name];
    const ctx = nb_bubble_editor.context(prompt.name);
    ctx.target = prompt.target;
    if (remove === true && button.remove) {
        button.remove(ctx);
    } else if (value !== null && value !== undefined) {
        button.apply(ctx, value);
    }
    nb_bubble_editor.update();
}

/* link preview: hovering a link (or putting the caret in it) shows its URL */

nb_bubble_editor.preview = { el: null, link: null, editor: null, timer: null, caret: false };

nb_bubble_editor.get_preview = function () {
    const pv = nb_bubble_editor.preview;
    if (pv.el) {
        return pv.el;
    }
    const tpl = document.getElementById('nb_bubble_link_preview');
    if (!tpl) {
        return null;
    }
    pv.el = tpl.content.firstElementChild.cloneNode(true);
    pv.el.addEventListener('mousedown', (e) => {
        const edit = e.target.closest('[data-nb-link-edit]');
        const remove = e.target.closest('[data-nb-link-remove]');
        if (!edit && !remove) {
            return; // the URL itself opens normally
        }
        e.preventDefault();
        const link = pv.link;
        const ed = pv.editor;
        nb_bubble_editor.hide_preview();
        if (!link || !ed) {
            return;
        }
        ed.focus();
        const range = document.createRange();
        range.selectNodeContents(link);
        nb_bubble_editor.select(range);
        nb_bubble_editor.current = ed;
        if (edit) {
            nb_bubble_editor.exec('anchor', ed);
        } else {
            nb_bubble_editor.doc.unlink();
            nb_bubble_editor.update();
        }
    });
    pv.el.addEventListener('mouseenter', () => { clearTimeout(pv.timer); });
    document.body.appendChild(pv.el);
    return pv.el;
}

nb_bubble_editor.link_in_editor = function (el) {
    const link = el && el.closest ? el.closest(nb_bubble_editor.link_selector) : null;
    const ed = link && link.closest('[data-nb-edit]');
    if (!ed || !ed._nb_bubble || !ed.isContentEditable) {
        return null;
    }
    return { link: link, editor: ed };
}

nb_bubble_editor.on_mouseover = function (e) {
    const pv = nb_bubble_editor.preview;
    if (pv.el && pv.el.contains(e.target)) {
        clearTimeout(pv.timer);
        return;
    }
    const hit = nb_bubble_editor.link_in_editor(e.target);
    if (hit && !nb_bubble_editor.prompt) {
        nb_bubble_editor.show_preview(hit.link, hit.editor, false);
    } else if (pv.link && !pv.caret) {
        clearTimeout(pv.timer);
        pv.timer = setTimeout(nb_bubble_editor.hide_preview, 300);
    }
}

// caret (no selection) inside a link keeps its preview open
nb_bubble_editor.update_preview = function () {
    if (nb_bubble_editor.prompt) {
        return;
    }
    const sel = window.getSelection();
    const hit = sel && sel.rangeCount > 0 && sel.isCollapsed
        ? nb_bubble_editor.link_in_editor(nb_bubble_editor.element_of(sel.anchorNode)) : null;
    if (hit) {
        nb_bubble_editor.show_preview(hit.link, hit.editor, true);
    } else if (nb_bubble_editor.preview.caret) {
        nb_bubble_editor.hide_preview();
    }
}

nb_bubble_editor.show_preview = function (link, ed, caret) {
    const el = nb_bubble_editor.get_preview();
    if (!el) {
        return;
    }
    const pv = nb_bubble_editor.preview;
    clearTimeout(pv.timer);
    pv.link = link;
    pv.editor = ed;
    pv.caret = caret;
    const href = link.getAttribute('href') || '';
    const url = el.querySelector('[data-nb-link-url]');
    url.textContent = href;
    url.setAttribute('href', href);
    const can_edit = ed._nb_bubble.buttons.includes('anchor');
    el.querySelectorAll('[data-nb-link-edit], [data-nb-link-remove]').forEach((btn) => {
        btn.classList.toggle('hidden', !can_edit);
    });
    el.classList.remove('hidden');
    const gap = 6;
    const rect = link.getBoundingClientRect();
    const bounds = nb_bubble_editor.content_bounds(8);
    let top = rect.bottom + gap;
    if (top + el.offsetHeight > window.innerHeight - 8) {
        top = rect.top - el.offsetHeight - gap;
    }
    const left = Math.max(bounds.left, Math.min(rect.left, bounds.right - el.offsetWidth));
    el.style.top = top + 'px';
    el.style.left = left + 'px';
}

nb_bubble_editor.hide_preview = function () {
    const pv = nb_bubble_editor.preview;
    clearTimeout(pv.timer);
    if (pv.el) {
        pv.el.classList.add('hidden');
    }
    pv.link = null;
    pv.editor = null;
    pv.caret = false;
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
    const act = (target) => {
        const btn = target.closest('[data-nb-bubble-name]');
        if (btn) {
            nb_bubble_editor.exec(btn.dataset.nbBubbleName);
        } else if (target.closest('[data-nb-bubble-remove]')) {
            nb_bubble_editor.close_prompt(null, true);
        } else if (target.closest('[data-nb-bubble-cancel]')) {
            nb_bubble_editor.close_prompt(null);
        } else {
            return false;
        }
        return true;
    };
    tb.addEventListener('mousedown', (e) => {
        if (e.target.closest('button[type=button]')) {
            e.preventDefault();
            act(e.target);
        }
    });
    // keyboard (Enter/Space on a focused button): back to the editor's selection first
    tb.addEventListener('click', (e) => {
        if (e.detail !== 0 || !e.target.closest('button[type=button]')) {
            return;
        }
        if (e.target.closest('[data-nb-bubble-name]') && nb_bubble_editor.current) {
            nb_bubble_editor.refocus(nb_bubble_editor.current);
        }
        act(e.target);
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
    ed._nb_bubble.bubble_buttons.forEach((name) => {
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
    if (!tb || !ed || sel.isCollapsed || nb_bubble_editor.pointer_down || ed._nb_bubble.bubble_buttons.length === 0) {
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

// horizontal room for floating toolbars: the viewport minus a vertical Nimbly bar docked left or right
nb_bubble_editor.content_bounds = function (gap) {
    let left = gap;
    let right = window.innerWidth - gap;
    const nb_bar = document.getElementById('nb-bar');
    if (nb_bar) {
        const r = nb_bar.getBoundingClientRect();
        if (r.height > r.width && r.width > 0) {
            if (r.left <= 0) {
                left = Math.max(left, r.right + gap);
            } else {
                right = Math.min(right, r.left - gap);
            }
        }
    }
    return { left: left, right: right };
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
    // stay clear of the field bar docked above the field
    const min_top = Math.max(gap, window.nb && window.nb.field_bar ? window.nb.field_bar.bottom() + gap : 0);
    let top = rect.top - h - gap;
    const below = top < min_top;
    if (below) {
        top = rect.bottom + gap;
    }
    const center = rect.left + rect.width / 2;
    const bounds = nb_bubble_editor.content_bounds(gap);
    let left = center - w / 2;
    left = Math.max(bounds.left, Math.min(left, bounds.right - w));
    tb.style.top = top + 'px';
    tb.style.left = left + 'px';
    // arrow points at the middle of the selection, kept clear of the rounded corners
    const arrow_x = Math.max(12, Math.min(center - left, w - 12));
    tb.querySelector('.nb-bubble-arrow').style.left = arrow_x + 'px';
    tb.classList.toggle('nb-bubble-below', below);
}

export default nb_bubble_editor;
