@props(['column', 'filters' => [], 'default' => false])
@php
    // Lien d'en-tête de colonne : un clic trie, un second inverse le sens.
    $current = ($filters['sort'] ?? null) === $column || ($default && empty($filters['sort']));
    $dir = $current && ($filters['dir'] ?? 'asc') === 'asc' ? 'desc' : 'asc';
    $url = request()->fullUrlWithQuery(['sort' => $column, 'dir' => $dir, 'page' => null]);
@endphp
<a href="{{ $url }}" data-live-link class="text-reset text-decoration-none"
   @if ($current) aria-sort="{{ ($filters['dir'] ?? 'asc') === 'asc' ? 'ascending' : 'descending' }}" @endif>
    {{ $slot }}
    @if ($current)
        <i class="bi bi-arrow-{{ ($filters['dir'] ?? 'asc') === 'asc' ? 'up' : 'down' }}" aria-hidden="true"></i>
    @endif
</a>
