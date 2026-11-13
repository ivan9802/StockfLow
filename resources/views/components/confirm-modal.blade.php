{{--
    Fenêtre de confirmation unique, ouverte par tout bouton portant data-confirm.
    Attributs lus sur le bouton : data-confirm (question), data-confirm-title,
    data-confirm-label (libellé du bouton), data-confirm-reason="1" pour exiger un motif
    (envoyé dans le champ "reason" du formulaire du bouton).
--}}
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="confirmModalTitle">Confirmer</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0" data-confirm-message></p>
                <div class="mt-3" data-confirm-reason-group hidden>
                    <label class="form-label" for="confirmReason">Motif</label>
                    <textarea class="form-control" id="confirmReason" rows="3" maxlength="500"></textarea>
                    <div class="invalid-feedback">Indiquez un motif d'au moins 5 caractères.</div>
                    <div class="form-text">Le motif est conservé dans l'historique.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-danger" data-confirm-accept>Confirmer</button>
            </div>
        </div>
    </div>
</div>
