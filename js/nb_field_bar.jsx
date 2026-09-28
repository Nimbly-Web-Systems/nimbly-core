// Field bar: the toolbar for the field being edited. It holds the field's
// configured formatting buttons (same registry as the bubble, see
// nb_bubble_editor.jsx), insert-at-caret actions (media) and, for inline page
// editing, Save.
//  - inline (page) editing: one shared floating bar, docked above the focused
//    field and pinned to the viewport top while a long field scrolls by
//  - form fields (docked): each field gets its own bar inside the field box,
//    always visible and sticky within the field
// Markup: core/tpl/bubble-editor. No imports: specs load this file with its
// export stripped.

var nb_field_bar = {
    bar: null, // shared floating bar
    current: null
};

// config: { buttons: [names], media: bool, save: bool, docked: bool }
nb_field_bar.attach = function (ed, config) {
    const buttons = (config.buttons || []).filter((name) => { return window.nb.bubble_editor.buttons[name]; });
    const media = config.media === true && document.getElementById('nb-modal-insert-media') !== null;
    const save = config.save === true && config.docked !== true;
    if (buttons.length === 0 && !media && !save) {
        return;
    }
    ed._nb_bar = {
        buttons: buttons,
        media: media,
        save: save,
        el: null,
        handlers: {
            focus: () => { nb_field_bar.on_focus(ed); },
            blur: (e) => { nb_field_bar.on_blur(e); },
            keydown: (e) => { nb_field_bar.on_keydown(e); },
            input: () => { nb_field_bar.position(); }
        }
    };
    if (config.docked === true) {
        const el = nb_field_bar.create_bar();
        if (el) {
            el.classList.add('nb-field-bar-docked');
            el.classList.remove('hidden');
            el._nb_editor = ed;
            // one field box around bar and text, styled (and focused) like a daisyUI input
            const box = document.createElement('div');
            box.className = 'nb-field-box';
            ed.before(box);
            box.append(el, ed);
            ed._nb_bar.el = el;
            ed._nb_bar.box = box;
            nb_field_bar.render(ed, el);
        }
    }
    Object.entries(ed._nb_bar.handlers).forEach(([type, handler]) => {
        ed.addEventListener(type, handler);
    });
    nb_field_bar.listen();
}

nb_field_bar.detach = function (ed) {
    if (!ed._nb_bar) {
        return;
    }
    Object.entries(ed._nb_bar.handlers).forEach(([type, handler]) => {
        ed.removeEventListener(type, handler);
    });
    if (nb_field_bar.current === ed) {
        nb_field_bar.hide();
    }
    if (ed._nb_bar.box) {
        ed._nb_bar.box.replaceWith(ed);
    }
    delete ed._nb_bar;
}

nb_field_bar.listen = function () {
    if (nb_field_bar.listening) {
        return;
    }
    nb_field_bar.listening = true;
    document.addEventListener('selectionchange', () => {
        requestAnimationFrame(nb_field_bar.update_states);
    });
    window.addEventListener('scroll', () => { nb_field_bar.position(); }, true);
    window.addEventListener('resize', () => { nb_field_bar.position(); });
}

// the bar showing ed's buttons: its own docked bar, or the shared floating one
nb_field_bar.bar_for = function (ed) {
    return ed && ed._nb_bar ? (ed._nb_bar.el || nb_field_bar.bar) : null;
}

nb_field_bar.in_bar = function (el) {
    return !!(el && el.closest && el.closest('.nb-field-bar'));
}

nb_field_bar.on_focus = function (ed) {
    if (ed._nb_bar.el) {
        nb_field_bar.current = ed;
        nb_field_bar.update_states();
    } else {
        nb_field_bar.show(ed);
    }
}

nb_field_bar.on_blur = function (e) {
    const to = e.relatedTarget;
    if (nb_field_bar.in_bar(to) || window.nb.bubble_editor.in_toolbar(to)) {
        return;
    }
    nb_field_bar.hide();
}

nb_field_bar.on_keydown = function (e) {
    const ed = e.currentTarget;
    if ((e.metaKey || e.ctrlKey) && !e.altKey && !e.shiftKey && e.key.toLowerCase() === 's' && ed._nb_bar.save) {
        e.preventDefault();
        window.nb.edit.save();
    }
}

nb_field_bar.create_bar = function () {
    const tpl = document.getElementById('nb_field_bar');
    if (!tpl) {
        return null;
    }
    const bar = tpl.content.firstElementChild.cloneNode(true);
    // mousedown + preventDefault keeps focus (and the caret) in the field
    bar.addEventListener('mousedown', (e) => {
        const ed = bar._nb_editor || nb_field_bar.current;
        if (!ed || !e.target.closest('button')) {
            return;
        }
        e.preventDefault();
        nb_field_bar.focus_editor(ed);
        const format = e.target.closest('[data-nb-bubble-name]');
        if (format) {
            window.nb.bubble_editor.exec(format.dataset.nbBubbleName, ed);
            nb_field_bar.update_states();
        } else if (e.target.closest('[data-nb-bar-media]')) {
            window.nb.edit.open_insert_media();
        } else if (e.target.closest('[data-nb-bar-save]')) {
            window.nb.edit.save();
        }
    });
    return bar;
}

// a docked bar can be used before its field has focus: start at the end of the field
nb_field_bar.focus_editor = function (ed) {
    const sel = window.getSelection();
    if (document.activeElement === ed && sel.rangeCount > 0 && ed.contains(sel.anchorNode)) {
        return;
    }
    ed.focus();
    const range = document.createRange();
    range.selectNodeContents(ed.lastElementChild || ed);
    range.collapse(false);
    sel.removeAllRanges();
    sel.addRange(range);
}

nb_field_bar.show = function (ed) {
    if (!nb_field_bar.bar) {
        nb_field_bar.bar = nb_field_bar.create_bar();
        if (!nb_field_bar.bar) {
            return;
        }
        document.body.appendChild(nb_field_bar.bar);
    }
    const bar = nb_field_bar.bar;
    if (nb_field_bar.current !== ed) {
        nb_field_bar.render(ed, bar);
    }
    nb_field_bar.current = ed;
    bar.classList.remove('hidden');
    nb_field_bar.update_states();
    nb_field_bar.position();
}

nb_field_bar.hide = function () {
    const ed = nb_field_bar.current;
    if (ed && ed._nb_bar && ed._nb_bar.el) {
        ed._nb_bar.el.querySelectorAll('[aria-pressed="true"]').forEach((btn) => {
            btn.setAttribute('aria-pressed', 'false');
        });
    } else if (nb_field_bar.bar) {
        nb_field_bar.bar.classList.add('hidden');
    }
    nb_field_bar.current = null;
}

nb_field_bar.render = function (ed, bar) {
    const config = ed._nb_bar;
    const format = bar.querySelector('[data-nb-bar-format]');
    const button_tpl = document.getElementById('nb_bubble_button');
    format.innerHTML = '';
    config.buttons.forEach((name) => {
        const button = window.nb.bubble_editor.buttons[name];
        const btn = button_tpl.content.firstElementChild.cloneNode(true);
        btn.dataset.nbBubbleName = name;
        btn.setAttribute('aria-label', button.label);
        btn.setAttribute('title', button.label);
        btn.innerHTML = button.icon;
        format.appendChild(btn);
    });
    format.classList.toggle('hidden', config.buttons.length === 0);
    bar.querySelector('[data-nb-bar-insert]').classList.toggle('hidden', !config.media);
    bar.querySelector('[data-nb-bar-save]').classList.toggle('hidden', !config.save);
}

nb_field_bar.update_states = function () {
    const ed = nb_field_bar.current;
    const bar = nb_field_bar.bar_for(ed);
    if (!bar) {
        return;
    }
    const sel = window.getSelection();
    if (!sel || sel.rangeCount === 0 || !ed.contains(sel.anchorNode)) {
        return;
    }
    const bubble = window.nb.bubble_editor;
    bar.querySelectorAll('[data-nb-bubble-name]').forEach((btn) => {
        const name = btn.dataset.nbBubbleName;
        const button = bubble.buttons[name];
        const ctx = bubble.context(name);
        ctx.editor = ed;
        const active = button.is_active ? button.is_active(ctx) : false;
        btn.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
}

// floating bar: above the field; pinned to the viewport top while the field continues below it
nb_field_bar.position = function () {
    const ed = nb_field_bar.current;
    const bar = nb_field_bar.bar;
    if (!ed || !bar || ed._nb_bar.el) {
        return;
    }
    if (!ed.isContentEditable || !ed.isConnected) {
        nb_field_bar.hide();
        return;
    }
    const gap = 6;
    const rect = ed.getBoundingClientRect();
    const h = bar.offsetHeight;
    let top = rect.top - h - gap; // above the field
    if (top < gap) {
        // pinned to the viewport top while the field continues below the bar, otherwise under the field
        top = rect.bottom - h - gap >= gap ? gap : rect.bottom + gap;
    }
    bar.classList.toggle('nb-field-bar-pinned', top === gap);
    bar.style.visibility = rect.bottom < 0 || rect.top > window.innerHeight ? 'hidden' : '';
    bar.style.top = top + 'px';
    bar.style.left = Math.max(gap, rect.left) + 'px';
    bar.style.maxWidth = (window.innerWidth - 2 * gap) + 'px';
}

// bottom edge of the active field's bar (the bubble keeps clear of it)
nb_field_bar.bottom = function () {
    const ed = nb_field_bar.current;
    const bar = nb_field_bar.bar_for(ed);
    if (!bar || bar.classList.contains('hidden') || bar.style.visibility === 'hidden') {
        return 0;
    }
    return bar.getBoundingClientRect().bottom;
}

export default nb_field_bar;
