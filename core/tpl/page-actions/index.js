// Page actions: one pill with the controls for this page (edit mode, page settings).
// Drag it by its grip anywhere; dropped near a corner it docks there (remembered per browser).
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

/* placement: free anywhere, or docked in a corner when dropped near one */

function nb_page_actions_dock(root) {
  const corners = ['top-left', 'top-right', 'bottom-left', 'bottom-right'];
  const storage_key = 'nb_page_actions_place';
  const gap = 16;
  const snap = 80; // px from a corner of the content area
  // { corner } when docked, { x, y } (fractions of the window) when placed freely
  let place = { corner: root.dataset.corner };
  try {
    const saved = JSON.parse(localStorage.getItem(storage_key) || 'null');
    if (saved && (corners.includes(saved.corner) || (typeof saved.x === 'number' && typeof saved.y === 'number'))) {
      place = saved;
    }
  } catch (e) {
    // no storage: default corner
  }

  // the area not covered by the Nimbly bar (body padding) and the chat button's side
  const area = () => {
    const body = getComputedStyle(document.body);
    const chat = document.querySelector('#agent-chat > button');
    const chat_rect = chat ? chat.getBoundingClientRect() : null;
    return {
      left: parseFloat(body.paddingLeft) || 0,
      right: window.innerWidth - (parseFloat(body.paddingRight) || 0),
      bottom: window.innerHeight - (parseFloat(body.paddingBottom) || 0),
      chat_rect: chat_rect && chat_rect.width > 0 ? chat_rect : null,
      chat_side: chat_rect && chat_rect.width > 0
        ? (chat_rect.left + chat_rect.width / 2 < window.innerWidth / 2 ? 'left' : 'right') : null
    };
  };

  const apply = () => {
    const a = area();
    const style = root.style;
    style.left = style.right = style.top = style.bottom = '';
    root.removeAttribute('data-over-chat');
    root.removeAttribute('data-above-chat-panel');
    if (place.corner) {
      const [v, h] = place.corner.split('-');
      if (h === 'left') {
        style.left = a.left + gap + 'px';
      } else {
        style.right = window.innerWidth - a.right + gap + 'px';
      }
      if (v === 'top') {
        style.top = gap + 'px';
      } else if (a.chat_side === h) {
        style.bottom = window.innerHeight - a.chat_rect.top + 12 + 'px'; // just above the chat button
      } else {
        style.bottom = window.innerHeight - a.bottom + gap + 'px';
      }
      root.dataset.corner = place.corner;
      root.toggleAttribute('data-over-chat', v === 'bottom' && a.chat_side === h);
      root.toggleAttribute('data-above-chat-panel', v === 'top' && a.chat_side === h);
      return;
    }
    const w = root.offsetWidth;
    const h = root.offsetHeight;
    const x = Math.max(0, Math.min(place.x * window.innerWidth, window.innerWidth - w));
    const y = Math.max(0, Math.min(place.y * window.innerHeight, window.innerHeight - h));
    style.left = x + 'px';
    style.top = y + 'px';
    // tooltips open towards the page: the half of the screen the pill is in
    root.dataset.corner = (y + h / 2 < window.innerHeight / 2 ? 'top' : 'bottom') + '-'
      + (x + w / 2 < window.innerWidth / 2 ? 'left' : 'right');
  };

  const save = (next) => {
    place = next;
    try {
      localStorage.setItem(storage_key, JSON.stringify(place));
    } catch (e) {
      // not remembered
    }
    apply();
  };

  // dropped: dock when near a corner, otherwise stay put
  const drop = () => {
    const a = area();
    const r = root.getBoundingClientRect();
    const near_left = r.left - a.left < snap;
    const near_right = a.right - r.right < snap;
    const near_top = r.top < snap;
    const bottom_edge = (side) => (a.chat_side === side ? a.chat_rect.top : a.bottom);
    const near_bottom_left = bottom_edge('left') - r.bottom < snap;
    const near_bottom_right = bottom_edge('right') - r.bottom < snap;
    let corner = null;
    if (near_top && near_left) {
      corner = 'top-left';
    } else if (near_top && near_right) {
      corner = 'top-right';
    } else if (near_bottom_left && near_left) {
      corner = 'bottom-left';
    } else if (near_bottom_right && near_right) {
      corner = 'bottom-right';
    }
    save(corner ? { corner: corner } : { x: r.left / window.innerWidth, y: r.top / window.innerHeight });
  };

  apply();
  window.addEventListener('resize', apply);
  // the Nimbly bar changes the body padding when it collapses or switches to mobile
  new MutationObserver(apply).observe(document.body, { attributes: true, attributeFilter: ['style', 'class'] });

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
      drop();
    };
    grip.addEventListener('pointermove', move);
    grip.addEventListener('pointerup', up);
    grip.addEventListener('pointercancel', up);
  });
  // keyboard: arrow keys move the pill to another corner
  grip.addEventListener('keydown', (e) => {
    const [v, h] = (place.corner || root.dataset.corner).split('-');
    const next = {
      ArrowUp: 'top-' + h, ArrowDown: 'bottom-' + h, ArrowLeft: v + '-left', ArrowRight: v + '-right'
    }[e.key];
    if (next) {
      e.preventDefault();
      save({ corner: next });
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
