@props(['quantity', 'threshold' => null, 'showQuantity' => false])
@php
    // Règle du cahier des charges : alerte quand quantité <= seuil. Sans seuil, seule la rupture est signalée.
    if ((int) $quantity === 0) {
        [$tone, $icon, $label] = ['danger', 'x-octagon', 'Rupture'];
    } elseif ($threshold !== null && $quantity <= $threshold) {
        [$tone, $icon, $label] = ['warning', 'exclamation-triangle', 'Sous seuil'];
    } else {
        [$tone, $icon, $label] = ['success', 'check-circle', 'En stock'];
    }
@endphp
<span class="sf-badge is-{{ $tone }}"><i class="bi bi-{{ $icon }}" aria-hidden="true"></i>{{ $label }}@if ($showQuantity) · <span class="tabular">{{ Format::number($quantity) }}</span>@endif</span>
