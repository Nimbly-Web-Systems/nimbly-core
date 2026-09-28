// Page actions: round buttons for the current page (edit mode, page settings),
// stacked above the chat button. Opt-in with the bubble editor until the switch-over.
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
  if (!edit) {
    return;
  }

  const leave_dialog = document.getElementById('nb-modal-leave-edit');

  const show_state = (enabled) => {
    const label = enabled ? edit.dataset.labelDone : edit.dataset.labelEdit;
    edit.setAttribute('aria-pressed', enabled ? 'true' : 'false');
    edit.setAttribute('aria-label', label);
    edit.dataset.tip = label;
    edit.classList.toggle('btn-primary', enabled);
    edit.querySelector('[data-icon-edit]').classList.toggle('hidden', enabled);
    edit.querySelector('[data-icon-done]').classList.toggle('hidden', !enabled);
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
});
