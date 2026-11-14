@extends('layouts.app')

@section('title', 'Commandes fournisseurs')

@section('content')
    <x-page-header title="Commandes fournisseurs">
        <x-slot:actions>
            <a class="btn btn-primary" href="{{ route('purchase-orders.create') }}"><i class="bi bi-cart-plus" aria-hidden="true"></i> Nouvelle commande</a>
        </x-slot:actions>
    </x-page-header>

    <div data-live-list>
        <form method="GET" action="{{ route('purchase-orders.index') }}" class="filter-bar" data-live-form>
            <div class="filter">
                <label class="form-label" for="status">Statut</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Tous</option>
                    <option value="pending" @selected(($filters['status'] ?? null) === 'pending')>En attente de livraison</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? null) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter">
                <label class="form-label" for="supplier_id">Fournisseur</label>
                <select class="form-select" id="supplier_id" name="supplier_id">
                    <option value="">Tous</option>
                    @foreach ($suppliers as $id => $name)
                        <option value="{{ $id }}" @selected(($filters['supplier_id'] ?? null) == $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <noscript><button type="submit" class="btn btn-outline-secondary">Filtrer</button></noscript>
        </form>

        <div data-live-target aria-live="polite">
            @include('purchase-orders._table')
        </div>
    </div>
@endsection
