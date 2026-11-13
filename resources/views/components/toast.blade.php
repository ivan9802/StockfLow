@props(['message'])
<div class="toast" role="status" data-autoshow data-bs-delay="5000">
    <div class="d-flex">
        <div class="toast-body d-flex gap-2">
            <i class="bi bi-check-circle text-success" aria-hidden="true"></i>
            <span>{{ $message }}</span>
        </div>
        <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Fermer"></button>
    </div>
</div>
