@extends('layouts.app')

@section('title', 'État des stocks')

@section('content')
    <x-page-header title="État des stocks">
        @can('reports.view')
            <x-slot:actions>
                <a class="btn btn-outline-secondary" href="{{ route('reports.stock', 'csv') }}"><i class="bi bi-filetype-csv" aria-hidden="true"></i> Export CSV</a>
                <a class="btn btn-outline-secondary" href="{{ route('reports.stock', 'pdf') }}"><i class="bi bi-filetype-pdf" aria-hidden="true"></i> État PDF</a>
            </x-slot:actions>
        @endcan
    </x-page-header>

    <div data-live-list>
        <form method="GET" action="{{ route('stock.index') }}" class="filter-bar" data-live-form role="search">
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
            <div class="filter">
                <label class="form-label" for="stock">État</label>
                <select class="form-select" id="stock" name="stock">
                    <option value="">Tous</option>
                    <option value="ok" @selected(($filters['stock'] ?? null) === 'ok')>En stock</option>
                    <option value="low" @selected(($filters['stock'] ?? null) === 'low')>Sous seuil</option>
                    <option value="out" @selected(($filters['stock'] ?? null) === 'out')>Rupture</option>
                </select>
            </div>
            <noscript><button type="submit" class="btn btn-outline-secondary">Filtrer</button></noscript>
        </form>

        <div data-live-target aria-live="polite">
            @include('stock._table')
        </div>
    </div>
@endsection
