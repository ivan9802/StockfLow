@extends('layouts.app')

@section('title', 'Modifier '.$warehouse->name)

@section('content')
    <x-page-header title="Modifier l'emplacement" :breadcrumbs="['Emplacements' => route('warehouses.index')]" />

    <form method="POST" action="{{ route('warehouses.update', $warehouse) }}" class="form-narrow card card-body" data-once novalidate>
        @csrf
        @method('PUT')

        <x-form.input name="name" label="Nom" :value="$warehouse->name" maxlength="100" autocomplete="off" />
        <x-form.input name="address" label="Adresse" :value="$warehouse->address" optional maxlength="255" help="Commune, quartier, repère." />

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">Enregistrer</button>
            <a class="btn btn-outline-secondary" href="{{ route('warehouses.index') }}">Annuler</a>
        </div>
    </form>
@endsection
