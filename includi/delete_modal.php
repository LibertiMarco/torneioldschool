<?php
/**
 * Modal di conferma eliminazione riutilizzabile.
 * Include anche il form GET con parametro "elimina".
 */
?>
<div id="deleteModal" class="admin-modal hidden" role="dialog" aria-modal="true" aria-labelledby="deleteModalTitle">
    <div class="admin-modal__content">
        <h3 id="deleteModalTitle">Elimina elemento</h3>
        <p>Vuoi davvero eliminare <strong id="deleteModalName"></strong>? L'azione non è reversibile.</p>
        <div class="admin-modal__actions">
            <button type="button" class="btn-secondary" id="deleteModalCancel">Annulla</button>
            <button type="button" class="btn-danger" id="deleteModalConfirm">Elimina</button>
        </div>
    </div>
</div>
<form id="deleteForm" method="GET" class="hidden">
    <input type="hidden" name="elimina" id="deleteInputId">
</form>
