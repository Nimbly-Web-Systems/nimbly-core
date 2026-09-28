// Field bar: a toolbar docked to the top of the field being edited. It holds
// the field's configured formatting buttons (same registry as the bubble, see
// nb_bubble_editor.jsx), insert-at-caret actions (media) and, for inline page
// editing, Save. When the field's top scrolls out of view the bar stays pinned
// to the top of the viewport until the field itself scrolls away.
// Markup: core/tpl/bubble-editor. No imports: specs load this file with its
// export stripped.

var nb_field_bar = {
    bar: null,
    current: null
};

// config: { buttons: [names], media: bool, save: bool }
nb_field_bar.attach = function (ed, config) {
    const buttons = (config.buttons || []).filter((name) => { return window.nb.bubble_editor.buttons[name]; });
    const media = config.media === true && document.getElementById('nb-modal-insert-media') !== null;
    if (buttons.length === 0 && !media && !config.save) {
        return;
    }
    ed._nb_bar = {
        buttons: buttons,
        media: media,
        save: config.save === true,
        handlers: {
            focus: () => { nb_field_bar.show(ed); },
            blur: (e) => { nb_field_bar.on_blur(e); },
            keydown: (e) => { nb_field_bar.on_keydown(e); },
            input: () => { nb_field_bar.position(); }
        }
    };
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

nb_field_bar.in_bar = function (el) {
    return !!(el && nb_field_bar.bar && nb_field_bar.bar.contains(el));
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

nb_field_bar.get_bar = function () {
    if (nb_field_bar.bar) {
        return nb_field_bar.bar;
    }
    const tpl = document.getElementById('nb_field_bar');
    if (!tpl) {
        return null;
    }
    const bar = tpl.content.firstElementChild.cloneNode(true);
    // mousedown + preventDefault keeps focus (and the caret) in the field
    bar.addEventListener('mousedown', (e) => {
        const ed = nb_field_bar.current;
        if (!ed || !e.target.closest('button')) {
            return;
        }
        e.preventDefault();
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
    document.body.appendChild(bar);
    nb_field_bar.bar = bar;
    return bar;
}

nb_field_bar.show = function (ed) {
    const bar = nb_field_bar.get_bar();
    if (!bar || !ed._nb_bar) {
        return;
    }
    if (nb_field_bar.current !== ed) {
        nb_field_bar.render(ed);
    }
    nb_field_bar.current = ed;
    bar.classList.remove('hidden');
    nb_field_bar.update_states();
    nb_field_bar.position();
}

nb_field_bar.hide = function () {
    if (nb_field_bar.bar) {
        nb_field_bar.bar.classList.add('hidden');
    }
    nb_field_bar.current = null;
}

nb_field_bar.render = function (ed) {
    const bar = nb_field_bar.bar;
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
    if (!ed || !nb_field_bar.bar) {
        return;
    }
    const sel = window.getSelection();
    if (!sel || sel.rangeCount === 0 || !ed.contains(sel.anchorNode)) {
        return;
    }
    const bubble = window.nb.bubble_editor;
    nb_field_bar.bar.querySelectorAll('[data-nb-bubble-name]').forEach((btn) => {
        const name = btn.dataset.nbBubbleName;
        const button = bubble.buttons[name];
        const ctx = bubble.context(name);
        ctx.editor = ed;
        const active = button.is_active ? button.is_active(ctx) : false;
        btn.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
}

// docked above the field; pinned to the viewport top while the field is taller than what is left on screen
nb_field_bar.position = function () {
    const ed = nb_field_bar.current;
    const bar = nb_field_bar.bar;
    if (!ed || !bar) {
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

nb_field_bar.bottom = function () {
    const bar = nb_field_bar.bar;
    if (!bar || !nb_field_bar.current || bar.classList.contains('hidden') || bar.style.visibility === 'hidden') {
        return 0;
    }
    return bar.getBoundingClientRect().bottom;
}

export default nb_field_bar;
