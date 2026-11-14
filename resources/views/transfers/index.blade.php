@extends('layouts.app')

@section('title', 'Transferts')

@section('content')
    <x-page-header title="Transferts entre emplacements">
        <x-slot:actions>
            <a class="btn btn-primary" href="{{ route('transfers.create') }}"><i class="bi bi-shuffle" aria-hidden="true"></i> Nouveau transfert</a>
        </x-slot:actions>
    </x-page-header>

    <div data-live-list>
        <form method="GET" action="{{ route('transfers.index') }}" class="filter-bar" data-live-form>
            <div class="filter">
                <label class="form-label" for="from_warehouse_id">Départ</label>
                <select class="form-select" id="from_warehouse_id" name="from_warehouse_id">
                    <option value="">Tous</option>
                    @foreach ($warehouses as $id => $name)
                        <option value="{{ $id }}" @selected(($filters['from_warehouse_id'] ?? null) == $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter">
                <label class="form-label" for="status">Statut</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Tous</option>
                    <option value="completed" @selected(($filters['status'] ?? null) === 'completed')>Effectués</option>
                    <option value="cancelled" @selected(($filters['status'] ?? null) === 'cancelled')>Annulés</option>
                </select>
            </div>
            <noscript><button type="submit" class="btn btn-outline-secondary">Filtrer</button></noscript>
        </form>

        <div data-live-target aria-live="polite">
            @include('transfers._table')
        </div>
    </div>
@endsection
