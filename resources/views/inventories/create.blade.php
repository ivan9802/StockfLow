@extends('layouts.app')

@section('title', 'Ouvrir un inventaire')

@section('content')
    <x-page-header title="Ouvrir un inventaire" :breadcrumbs="['Inventaires' => route('inventories.index')]" />

    <form method="POST" action="{{ route('inventories.store') }}" class="form-narrow card card-body" data-once novalidate>
        @csrf

        <x-form.select name="warehouse_id" label="Emplacement à inventorier" :options="$warehouses" placeholder="Choisir l'emplacement" />
        <x-form.select name="category_id" label="Périmètre" :options="$categories" placeholder="Tous les produits" optional
            help="Limiter à une catégorie permet des inventaires tournants, plus courts." />
        <x-form.textarea name="notes" label="Notes" optional maxlength="2000" rows="2" />

        <x-alert type="info">
            Les ventes continuent pendant le comptage. Le stock enregistré de chaque produit est relevé au moment où vous saisissez sa quantité :
            saisissez au fil du comptage, sur la tablette, plutôt qu'en fin de journée.
        </x-alert>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">Ouvrir l'inventaire</button>
            <a class="btn btn-outline-secondary" href="{{ route('inventories.index') }}">Annuler</a>
        </div>
    </form>
@endsection
