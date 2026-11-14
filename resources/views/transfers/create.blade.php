@extends('layouts.app')

@section('title', 'Nouveau transfert')

@section('content')
    <x-page-header title="Nouveau transfert" :breadcrumbs="['Transferts' => route('transfers.index')]" />

    <form method="POST" action="{{ route('transfers.store') }}" data-once novalidate style="max-width: 960px">
        @csrf

        <section class="card mb-4">
            <div class="card-header"><h2>Trajet</h2></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <x-form.select name="from_warehouse_id" label="Départ" :options="$warehouses" :value="$defaultFrom" />
                    </div>
                    <div class="col-md-6">
                        <x-form.select name="to_warehouse_id" label="Arrivée" :options="$warehouses" :value="$defaultTo" />
                    </div>
                </div>
                <x-form.input name="notes" label="Note" optional maxlength="500" placeholder="Réassort hebdomadaire" />
            </div>
        </section>

        <x-line-items warehouse-field="from_warehouse_id" label="Produits à transférer" />

        <p class="text-secondary small">
            Le stock quitte l'emplacement de départ et arrive à destination au même instant. Si une quantité dépasse le
            disponible, rien n'est transféré.
        </p>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">Effectuer le transfert</button>
            <a class="btn btn-outline-secondary" href="{{ route('transfers.index') }}">Annuler</a>
        </div>
    </form>
@endsection
