@props(['type' => 'info', 'message' => null, 'title' => null])
@php
    $icons = ['danger' => 'x-octagon', 'warning' => 'exclamation-triangle', 'success' => 'check-circle', 'info' => 'info-circle'];
@endphp
<div {{ $attributes->class(['alert', 'alert-'.$type, 'd-flex', 'gap-2']) }} role="{{ $type === 'danger' ? 'alert' : 'status' }}" tabindex="-1">
    <i class="bi bi-{{ $icons[$type] ?? 'info-circle' }} flex-shrink-0" aria-hidden="true"></i>
    <div>
        @if ($title)<div class="fw-semibold">{{ $title }}</div>@endif
        {{ $message ?? $slot }}
    </div>
</div>
