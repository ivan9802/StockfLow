@extends('layouts.app')

@section('title', 'Historique des ventes')

@section('content')
    <x-page-header :title="$showSeller ? 'Historique des ventes' : 'Mes ventes'">
        <x-slot:actions>
            @can('sales.create')
                <a class="btn btn-primary" href="{{ route('sales.create') }}"><i class="bi bi-cash-coin" aria-hidden="true"></i> Nouvelle vente</a>
            @endcan
            @can('reports.view')
                <a class="btn btn-outline-secondary" href="{{ route('reports.sales', array_filter(['from' => $filters['from'] ?? null, 'to' => $filters['to'] ?? null])) }}"><i class="bi bi-filetype-csv" aria-hidden="true"></i> Export CSV</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div data-live-list>
        <form method="GET" action="{{ route('sales.index') }}" class="filter-bar" data-live-form role="search">
            <div class="filter filter-search">
                <label class="form-label" for="q">Recherche</label>
                <input type="search" class="form-control" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Numéro de bon ou client" autocomplete="off">
            </div>
            @if ($showSeller)
                <div class="filter">
                    <label class="form-label" for="user_id">Vendeur</label>
                    <select class="form-select" id="user_id" name="user_id">
                        <option value="">Tous</option>
                        @foreach ($sellers as $id => $name)
                            <option value="{{ $id }}" @selected(($filters['user_id'] ?? null) == $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="filter">
                <label class="form-label" for="status">Statut</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Tous</option>
                    <option value="completed" @selected(($filters['status'] ?? null) === 'completed')>Enregistrées</option>
                    <option value="cancelled" @selected(($filters['status'] ?? null) === 'cancelled')>Annulées</option>
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
            @include('sales._table')
        </div>
    </div>
@endsection
