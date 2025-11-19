document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('deleteModal');
  if (!modal) return;

  const titleEl = document.getElementById('deleteModalTitle');
  const nameEl = document.getElementById('deleteModalName');
  const cancelBtn = document.getElementById('deleteModalCancel');
  const confirmBtn = document.getElementById('deleteModalConfirm');
  const inputId = document.getElementById('deleteInputId');
  const form = document.getElementById('deleteForm');

  let pendingAction = null;

  const closeModal = () => {
    modal.classList.add('hidden');
    if (nameEl) nameEl.textContent = '';
    if (inputId) inputId.value = '';
    pendingAction = null;
  };

  const openModal = (title, label, onConfirm) => {
    if (titleEl) titleEl.textContent = title || 'Elimina elemento';
    if (nameEl) nameEl.textContent = label || '';
    pendingAction = () => {
      onConfirm?.();
      closeModal();
    };
    modal.classList.remove('hidden');
  };

  window.openDeleteModal = openModal;

  document.querySelectorAll('.delete-btn').forEach(btn => {
    btn.addEventListener('click', e => {
      e.preventDefault();
      if (!inputId || !form) return;

      const id = btn.dataset.id || '';
      const label = btn.dataset.label || '';
      const tipo = btn.dataset.type || 'elemento';
      const titolo = tipo.charAt(0).toUpperCase() + tipo.slice(1);

      openModal(`Elimina ${titolo}`, label, () => {
        if (!form || !inputId) return;
        inputId.value = id;
        form.submit();
      });
    });
  });

  cancelBtn?.addEventListener('click', closeModal);
  modal.addEventListener('click', e => {
    if (e.target === modal) closeModal();
  });

  confirmBtn?.addEventListener('click', () => {
    if (pendingAction) {
      pendingAction();
    }
  });
});
