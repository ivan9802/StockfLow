@props(['variant' => 'pick', 'id' => null, 'warehouse' => null, 'label' => 'Rechercher un produit', 'placeholder' => 'Nom, référence ou code-barres'])
@php $id = $id ?? 'product-search-'.$variant; @endphp
{{-- Recherche instantanée de produit. Voir resources/js/modules/product-search.js. --}}
<div class="position-relative">
    <label class="visually-hidden" for="{{ $id }}">{{ $label }}</label>
    <input type="search" class="form-control" id="{{ $id }}" placeholder="{{ $placeholder }}" autocomplete="off"
        data-product-search="{{ $variant }}" data-search-url="{{ route('internal.products.search') }}"
        @if ($warehouse) data-warehouse="{{ $warehouse }}" @endif
        role="combobox" aria-expanded="false" aria-controls="{{ $id }}-results" aria-autocomplete="list">
    <div class="search-results" id="{{ $id }}-results" role="listbox" aria-label="Résultats" aria-live="polite" data-search-results hidden></div>
</div>
