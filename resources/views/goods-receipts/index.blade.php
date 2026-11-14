@extends('layouts.app')

@section('title', 'Réceptions')

@section('content')
    <x-page-header title="Réceptions fournisseurs">
        <x-slot:actions>
            <a class="btn btn-primary" href="{{ route('purchase-orders.index', ['status' => 'pending']) }}"><i class="bi bi-box-arrow-in-down" aria-hidden="true"></i> Réceptionner une commande</a>
            <a class="btn btn-outline-secondary" href="{{ route('goods-receipts.create') }}">Réception sans commande</a>
        </x-slot:actions>
    </x-page-header>

    <div data-live-list>
        <form method="GET" action="{{ route('goods-receipts.index') }}" class="filter-bar" data-live-form>
            <div class="filter">
                <label class="form-label" for="supplier_id">Fournisseur</label>
                <select class="form-select" id="supplier_id" name="supplier_id">
                    <option value="">Tous</option>
                    @foreach ($suppliers as $id => $name)
                        <option value="{{ $id }}" @selected(($filters['supplier_id'] ?? null) == $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter">
                <label class="form-label" for="origin">Origine</label>
                <select class="form-select" id="origin" name="origin">
                    <option value="">Toutes</option>
                    <option value="order" @selected(($filters['origin'] ?? null) === 'order')>Sur commande</option>
                    <option value="direct" @selected(($filters['origin'] ?? null) === 'direct')>Sans commande</option>
                </select>
            </div>
            <noscript><button type="submit" class="btn btn-outline-secondary">Filtrer</button></noscript>
        </form>

        <div data-live-target aria-live="polite">
            @include('goods-receipts._table')
        </div>
    </div>
@endsection
