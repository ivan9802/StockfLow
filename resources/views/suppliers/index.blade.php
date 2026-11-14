@extends('layouts.app')

@section('title', 'Fournisseurs')

@section('content')
    <x-page-header title="Fournisseurs">
        <x-slot:actions>
            <a class="btn btn-primary" href="{{ route('suppliers.create') }}"><i class="bi bi-plus-lg" aria-hidden="true"></i> Nouveau fournisseur</a>
        </x-slot:actions>
    </x-page-header>

    <div data-live-list>
        <form method="GET" action="{{ route('suppliers.index') }}" class="filter-bar" data-live-form role="search">
            <div class="filter filter-search">
                <label class="form-label" for="q">Recherche</label>
                <input type="search" class="form-control" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Raison sociale ou interlocuteur" autocomplete="off">
            </div>
            <div class="filter">
                <label class="form-label" for="state">État</label>
                <select class="form-select" id="state" name="state">
                    <option value="">Tous</option>
                    <option value="active" @selected(($filters['state'] ?? null) === 'active')>Actifs</option>
                    <option value="inactive" @selected(($filters['state'] ?? null) === 'inactive')>Inactifs</option>
                </select>
            </div>
            <noscript><button type="submit" class="btn btn-outline-secondary">Filtrer</button></noscript>
        </form>

        <div data-live-target aria-live="polite">
            @include('suppliers._table')
        </div>
    </div>
@endsection
