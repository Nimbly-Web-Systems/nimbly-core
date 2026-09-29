// Page actions: one pill with the controls for this page (edit mode, page settings).
// Drag it by its grip; it docks in the nearest corner (remembered per browser).
// Opt-in with the bubble editor until the switch-over.
document.addEventListener('DOMContentLoaded', () => {
  const root = document.getElementById('nb-page-actions');
  if (!root || !window.nb.bubble_editor || !window.nb.bubble_editor.enabled()) {
    return;
  }
  const edit = root.querySelector('[data-nb-page-edit]');
  if (edit && !nb.edit.has_page_content()) {
    edit.remove();
  }
  if (!root.querySelector('.nb-page-action')) {
    return;
  }
  root.classList.remove('hidden');
  nb_page_actions_dock(root);
  if (edit) {
    nb_page_actions_edit(edit);
  }
});

/* docking */

function nb_page_actions_dock(root) {
  const corners = ['top-left', 'top-right', 'bottom-left', 'bottom-right'];
  const storage_key = 'nb_page_actions_corner';
  const gap = 16;
  let corner = root.dataset.corner;
  try {
    const saved = localStorage.getItem(storage_key);
    if (corners.includes(saved)) {
      corner = saved;
    }
  } catch (e) {
    // no storage: default corner
  }

  const chat_button = () => document.querySelector('#agent-chat > button');

  // left/right/top/bottom offsets for a corner, clear of the Nimbly bar and the chat button
  const place = () => {
    const [v, h] = corner.split('-');
    const body = getComputedStyle(document.body);
    const style = root.style;
    style.left = style.right = style.top = style.bottom = '';
    if (h === 'left') {
      style.left = (parseFloat(body.paddingLeft) || 0) + gap + 'px';
    } else {
      style.right = (parseFloat(body.paddingRight) || 0) + gap + 'px';
    }
    const chat = chat_button();
    const chat_rect = chat ? chat.getBoundingClientRect() : null;
    const chat_side = chat_rect && chat_rect.width > 0 ? (chat_rect.left + chat_rect.width / 2 < window.innerWidth / 2 ? 'left' : 'right') : null;
    if (v === 'top') {
      style.top = gap + 'px';
    } else if (chat_side === h) {
      style.bottom = window.innerHeight - chat_rect.top + 12 + 'px'; // just above the chat button
    } else {
      style.bottom = (parseFloat(body.paddingBottom) || 0) + gap + 'px';
    }
    root.dataset.corner = corner;
    root.toggleAttribute('data-over-chat', v === 'bottom' && chat_side === h);
    root.toggleAttribute('data-above-chat-panel', v === 'top' && chat_side === h);
  };

  const dock = (next) => {
    corner = next;
    try {
      localStorage.setItem(storage_key, corner);
    } catch (e) {
      // not remembered
    }
    place();
  };

  place();
  window.addEventListener('resize', place);
  // the Nimbly bar changes the body padding when it collapses or switches to mobile
  new MutationObserver(place).observe(document.body, { attributes: true, attributeFilter: ['style', 'class'] });

  const grip = root.querySelector('[data-nb-page-actions-grip]');
  if (!grip) {
    return;
  }
  grip.addEventListener('pointerdown', (e) => {
    e.preventDefault();
    grip.setPointerCapture(e.pointerId);
    const rect = root.getBoundingClientRect();
    const dx = e.clientX - rect.left;
    const dy = e.clientY - rect.top;
    root.classList.add('nb-page-actions-dragging');
    const move = (ev) => {
      root.style.right = root.style.bottom = '';
      root.style.left = Math.max(0, Math.min(ev.clientX - dx, window.innerWidth - rect.width)) + 'px';
      root.style.top = Math.max(0, Math.min(ev.clientY - dy, window.innerHeight - rect.height)) + 'px';
    };
    const up = () => {
      grip.removeEventListener('pointermove', move);
      grip.removeEventListener('pointerup', up);
      grip.removeEventListener('pointercancel', up);
      root.classList.remove('nb-page-actions-dragging');
      const r = root.getBoundingClientRect();
      const cx = r.left + r.width / 2;
      const cy = r.top + r.height / 2;
      dock((cy < window.innerHeight / 2 ? 'top' : 'bottom') + '-' + (cx < window.innerWidth / 2 ? 'left' : 'right'));
    };
    grip.addEventListener('pointermove', move);
    grip.addEventListener('pointerup', up);
    grip.addEventListener('pointercancel', up);
  });
  // keyboard: arrow keys move the pill to another corner
  grip.addEventListener('keydown', (e) => {
    const [v, h] = corner.split('-');
    const next = {
      ArrowUp: 'top-' + h, ArrowDown: 'bottom-' + h, ArrowLeft: v + '-left', ArrowRight: v + '-right'
    }[e.key];
    if (next) {
      e.preventDefault();
      dock(next);
    }
  });
}

/* edit mode toggle */

function nb_page_actions_edit(edit) {
  const leave_dialog = document.getElementById('nb-modal-leave-edit');

  // Edit is a toggle: same label, shown as on while editing
  const show_state = (enabled) => {
    edit.setAttribute('aria-pressed', enabled ? 'true' : 'false');
    edit.classList.toggle('btn-primary', enabled);
  };

  edit.addEventListener('click', () => {
    if (!nb.edit.enabled) {
      nb.edit.set_editing(true);
    } else if (nb.edit.inputs > 0 && leave_dialog) {
      leave_dialog.showModal();
    } else {
      nb.edit.set_editing(false);
    }
  });

  leave_dialog?.addEventListener('click', (e) => {
    const choice = e.target.closest('[data-nb-leave-edit]')?.dataset.nbLeaveEdit;
    if (!choice) {
      return;
    }
    leave_dialog.close();
    if (choice === 'save') {
      nb.edit.save();
      nb.edit.set_editing(false);
    } else if (choice === 'discard') {
      nb.edit.inputs = 0; // no second "unsaved changes" warning on the reload
      window.location.reload();
    }
  });

  // edit mode can also change from elsewhere (e.g. the Nimbly bar)
  document.addEventListener('nb:edit-mode', (e) => { show_state(e.detail.enabled); });
}
