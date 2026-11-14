@extends('layouts.app')

@section('title', 'Réception sans commande')

@section('content')
    <x-page-header title="Réception sans commande" :breadcrumbs="['Réceptions' => route('goods-receipts.index')]" />

    <x-alert type="info">
        Réservée aux achats de dépannage et aux excédents livrés au-delà d'une commande. Le circuit normal reste la commande fournisseur.
    </x-alert>

    <form method="POST" action="{{ route('goods-receipts.store') }}" data-once novalidate style="max-width: 960px">
        @csrf

        <section class="card mb-4">
            <div class="card-header"><h2>Livraison</h2></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <x-form.select name="supplier_id" label="Fournisseur" :options="$suppliers" placeholder="Choisir un fournisseur" />
                    </div>
                    <div class="col-md-6">
                        <x-form.select name="warehouse_id" label="Emplacement de réception" :options="$warehouses" />
                    </div>
                    <div class="col-md-6">
                        <x-form.input name="delivery_note_number" label="Numéro du bon de livraison" optional maxlength="60" autocomplete="off" />
                    </div>
                    <div class="col-md-6">
                        <x-form.input name="received_at" label="Date de réception" type="date" optional :value="today()->format('Y-m-d')" max="{{ today()->format('Y-m-d') }}" />
                    </div>
                </div>
                <x-form.textarea name="notes" label="Notes" optional maxlength="2000" rows="2" />
            </div>
        </section>

        <x-line-items with-cost label="Articles reçus" />

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">Enregistrer la réception</button>
            <a class="btn btn-outline-secondary" href="{{ route('goods-receipts.index') }}">Annuler</a>
        </div>
    </form>
@endsection
