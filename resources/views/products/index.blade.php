@extends('layouts.app')

@section('title', 'Produits')

@section('content')
    <x-page-header title="Produits">
        @can('catalog.manage')
            <x-slot:actions>
                <a class="btn btn-primary" href="{{ route('products.create') }}"><i class="bi bi-plus-lg" aria-hidden="true"></i> Nouveau produit</a>
            </x-slot:actions>
        @endcan
    </x-page-header>

    <div data-live-list>
        <form method="GET" action="{{ route('products.index') }}" class="filter-bar" data-live-form role="search">
            <div class="filter filter-search">
                <label class="form-label" for="q">Recherche</label>
                <input type="search" class="form-control" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Nom, référence ou code-barres" autocomplete="off">
            </div>
            <div class="filter">
                <label class="form-label" for="category_id">Catégorie</label>
                <select class="form-select" id="category_id" name="category_id">
                    <option value="">Toutes</option>
                    @foreach ($categories as $id => $name)
                        <option value="{{ $id }}" @selected(($filters['category_id'] ?? null) == $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            @if ($suppliers->isNotEmpty())
                <div class="filter">
                    <label class="form-label" for="supplier_id">Fournisseur</label>
                    <select class="form-select" id="supplier_id" name="supplier_id">
                        <option value="">Tous</option>
                        @foreach ($suppliers as $id => $name)
                            <option value="{{ $id }}" @selected(($filters['supplier_id'] ?? null) == $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="filter">
                <label class="form-label" for="stock">État du stock</label>
                <select class="form-select" id="stock" name="stock">
                    <option value="">Tous</option>
                    <option value="ok" @selected(($filters['stock'] ?? null) === 'ok')>En stock</option>
                    <option value="low" @selected(($filters['stock'] ?? null) === 'low')>Sous seuil</option>
                    <option value="out" @selected(($filters['stock'] ?? null) === 'out')>Rupture</option>
                </select>
            </div>
            <div class="filter">
                <label class="form-label" for="state">Fiche</label>
                <select class="form-select" id="state" name="state">
                    <option value="">Toutes</option>
                    <option value="active" @selected(($filters['state'] ?? null) === 'active')>Actives</option>
                    <option value="inactive" @selected(($filters['state'] ?? null) === 'inactive')>Inactives</option>
                </select>
            </div>
            <noscript><button type="submit" class="btn btn-outline-secondary">Filtrer</button></noscript>
        </form>

        <div data-live-target aria-live="polite">
            @include('products._table')
        </div>
    </div>
@endsection
