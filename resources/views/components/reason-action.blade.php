@props(['action', 'label', 'question', 'title' => null, 'tone' => 'danger', 'icon' => 'x-circle'])
{{-- Action qui exige un motif (annulation, solde, renvoi). Le motif est saisi dans la fenêtre de confirmation. --}}
<form method="POST" action="{{ $action }}" class="d-inline">
    @csrf
    <noscript>
        <label class="visually-hidden" for="reason-{{ md5($action) }}">Motif</label>
        <input type="text" name="reason" id="reason-{{ md5($action) }}" class="form-control form-control-sm mb-1" minlength="5" maxlength="500" placeholder="Motif (obligatoire)" required>
    </noscript>
    <button type="submit" class="btn btn-outline-{{ $tone }}" data-confirm="{{ $question }}" data-confirm-title="{{ $title ?? $label }}"
        data-confirm-label="{{ $label }}" data-confirm-reason="1" data-confirm-tone="{{ $tone === 'danger' ? 'danger' : 'primary' }}">
        <i class="bi bi-{{ $icon }}" aria-hidden="true"></i> {{ $label }}
    </button>
</form>
