(() => {
  'use strict';
  let trigger = null;
  document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-toggle-password]');
    if (toggle) { const field = document.getElementById(toggle.dataset.togglePassword); if (field) { const show = field.type === 'password'; field.type = show ? 'text' : 'password'; toggle.textContent = show ? 'Hide password' : 'Show password'; } }
    const open = event.target.closest('[data-open]');
    if (open) {
      const dialog = document.getElementById(open.dataset.open);
      if (dialog instanceof HTMLDialogElement) { trigger = open; dialog.showModal(); }
    }
    const close = event.target.closest('[data-close]');
    if (close) { close.closest('dialog')?.close(); }
    const move = event.target.closest('[data-move]');
    if (move) {
      const item = move.closest('[data-photo-id]');
      const list = item?.parentElement;
      if (!item || !list) return;
      if (move.dataset.move === '-1' && item.previousElementSibling) list.insertBefore(item, item.previousElementSibling);
      else if (move.dataset.move === '1' && item.nextElementSibling) list.insertBefore(item.nextElementSibling, item);
      const field = list.closest('form')?.querySelector('[name="photo_order"]');
      if (field) field.value = JSON.stringify([...list.children].map((photo) => Number(photo.dataset.photoId)));
    }
  });
  document.querySelectorAll('dialog').forEach((dialog) => {
    dialog.addEventListener('close', () => { trigger?.focus(); });
    dialog.addEventListener('click', (event) => { if (event.target === dialog) { const bounds = dialog.getBoundingClientRect(); if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) dialog.close(); } });
  });
  document.addEventListener('submit', (event) => {
    const form = event.target;
    if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) { event.preventDefault(); return; }
    const submit = event.submitter;
    if (submit) { submit.disabled = true; submit.textContent = form.hasAttribute('data-review-login') ? 'Signing in…' : 'Saving…'; }
  });
})();
