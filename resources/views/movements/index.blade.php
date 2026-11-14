@extends('layouts.app')

@section('title', 'Mouvements de stock')

@section('content')
    <x-page-header :title="$product ? 'Fiche de stock : '.$product->name : 'Mouvements de stock'"
        :breadcrumbs="$product ? ['Mouvements' => route('movements.index')] : []">
        <x-slot:actions>
            @can('movements.manage')
                <a class="btn btn-primary" href="{{ route('movements.create', $product ? ['product_id' => $product->id] : []) }}">
                    <i class="bi bi-plus-lg" aria-hidden="true"></i> Sortie ou ajustement
                </a>
            @endcan
            @can('reports.view')
                <a class="btn btn-outline-secondary" href="{{ route('reports.movements', ['format' => 'csv'] + array_filter($filters)) }}"><i class="bi bi-filetype-csv" aria-hidden="true"></i> CSV</a>
                <a class="btn btn-outline-secondary" href="{{ route('reports.movements', ['format' => 'pdf'] + array_filter($filters)) }}"><i class="bi bi-filetype-pdf" aria-hidden="true"></i> PDF</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div data-live-list>
        <form method="GET" action="{{ route('movements.index') }}" class="filter-bar" data-live-form role="search">
            @if ($product)<input type="hidden" name="product_id" value="{{ $product->id }}">@endif
            @unless ($product)
                <div class="filter filter-search">
                    <label class="form-label" for="q">Recherche</label>
                    <input type="search" class="form-control" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Produit ou numéro de bon" autocomplete="off">
                </div>
            @endunless
            <div class="filter">
                <label class="form-label" for="warehouse_id">Emplacement</label>
                <select class="form-select" id="warehouse_id" name="warehouse_id">
                    <option value="">Tous</option>
                    @foreach ($warehouses as $id => $name)
                        <option value="{{ $id }}" @selected(($filters['warehouse_id'] ?? null) == $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter">
                <label class="form-label" for="type">Type</label>
                <select class="form-select" id="type" name="type">
                    <option value="">Tous</option>
                    @foreach ($types as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['type'] ?? null) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter">
                <label class="form-label" for="user_id">Utilisateur</label>
                <select class="form-select" id="user_id" name="user_id">
                    <option value="">Tous</option>
                    @foreach ($users as $id => $name)
                        <option value="{{ $id }}" @selected(($filters['user_id'] ?? null) == $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter">
                <label class="form-label" for="from">Du</label>
                <input type="date" class="form-control" id="from" name="from" value="{{ $filters['from'] ?? '' }}">
            </div>
            <div class="filter">
                <label class="form-label" for="to">Au</label>
                <input type="date" class="form-control" id="to" name="to" value="{{ $filters['to'] ?? '' }}">
            </div>
            <noscript><button type="submit" class="btn btn-outline-secondary">Filtrer</button></noscript>
        </form>

        <div data-live-target aria-live="polite">
            @include('movements._table')
        </div>
    </div>
@endsection
