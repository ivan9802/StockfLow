@extends('layouts.app')

@php
    $editing = $order->exists;
    $lines = $editing
        ? $order->items->map(fn ($i) => ['product_id' => $i->product_id, 'quantity' => $i->quantity_ordered, 'unit_cost' => $i->unit_cost])->all()
        : [];
@endphp

@section('title', $editing ? 'Modifier '.$order->reference : 'Nouvelle commande')

@section('content')
    <x-page-header :title="$editing ? 'Modifier la commande '.$order->reference : 'Nouvelle commande fournisseur'"
        :breadcrumbs="['Commandes' => route('purchase-orders.index')]" />

    <form method="POST" action="{{ $editing ? route('purchase-orders.update', $order) : route('purchase-orders.store') }}" data-once novalidate style="max-width: 960px">
        @csrf
        @if ($editing) @method('PUT') @endif

        <section class="card mb-4">
            <div class="card-header"><h2>Commande</h2></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <x-form.select name="supplier_id" label="Fournisseur" :value="$order->supplier_id" placeholder="Choisir un fournisseur"
                            :options="$suppliers->mapWithKeys(fn ($s) => [$s->id => $s->name.' (délai moyen '.$s->lead_time_days.' j)'])" />
                    </div>
                    <div class="col-md-6">
                        <x-form.select name="warehouse_id" label="Livrer à" :options="$warehouses" :value="$order->warehouse_id ?? $warehouses->keys()->first()" />
                    </div>
                </div>
                <x-form.input name="expected_at" label="Livraison prévue le" type="date" optional :value="$order->expected_at?->format('Y-m-d')"
                    help="Laissée vide, elle est calculée à partir du délai moyen du fournisseur quand la commande est passée." />
                <x-form.textarea name="notes" label="Notes" optional :value="$order->notes" maxlength="2000" rows="2" />
            </div>
        </section>

        <x-line-items :lines="$lines" with-cost label="Articles commandés" />

        <p class="text-secondary small">La commande est enregistrée en brouillon. Elle reste modifiable jusqu'à ce que vous la passiez au fournisseur.</p>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">{{ $editing ? 'Enregistrer les modifications' : 'Enregistrer le brouillon' }}</button>
            <a class="btn btn-outline-secondary" href="{{ $editing ? route('purchase-orders.show', $order) : route('purchase-orders.index') }}">Annuler</a>
        </div>
    </form>
@endsection
