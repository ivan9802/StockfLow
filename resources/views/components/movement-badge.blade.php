@props(['movement'])
{{-- Type d'un mouvement avec la flèche de son sens. --}}
<span class="sf-badge is-neutral">
    <i class="bi bi-{{ $movement->direction->value === 'in' ? 'arrow-down-left' : 'arrow-up-right' }}" aria-hidden="true"></i>
    <span class="visually-hidden">{{ $movement->direction->label() }} :</span>
    {{ $movement->type->label() }}
</span>
