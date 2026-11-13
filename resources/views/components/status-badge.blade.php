@props(['status' => null, 'active' => null])
@php
    // Couleur et icône de chaque statut de document. Le libellé vient de l'enum.
    $map = [
        'draft' => ['info', 'pencil'],
        'ordered' => ['accent', 'send'],
        'partially_received' => ['warning', 'hourglass-split'],
        'received' => ['success', 'check-circle'],
        'closed' => ['neutral', 'archive'],
        'completed' => ['success', 'check-circle'],
        'in_progress' => ['info', 'clipboard-data'],
        'pending_validation' => ['warning', 'hourglass-split'],
        'validated' => ['success', 'check-circle'],
        'cancelled' => ['danger', 'x-circle'],
    ];

    if ($active !== null) {
        [$tone, $icon, $label] = $active ? ['success', 'check-circle', 'Actif'] : ['neutral', 'slash-circle', 'Inactif'];
    } else {
        [$tone, $icon] = $map[$status->value] ?? ['neutral', 'circle'];
        $label = $status->label();
    }
@endphp
<span class="sf-badge is-{{ $tone }}"><i class="bi bi-{{ $icon }}" aria-hidden="true"></i>{{ $label }}</span>
