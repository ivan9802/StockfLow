@extends('layouts.app')

@php $editing = $supplier->exists; @endphp

@section('title', $editing ? 'Modifier '.$supplier->name : 'Nouveau fournisseur')

@section('content')
    <x-page-header :title="$editing ? 'Modifier le fournisseur' : 'Nouveau fournisseur'" :breadcrumbs="['Fournisseurs' => route('suppliers.index')]" />

    <form method="POST" action="{{ $editing ? route('suppliers.update', $supplier) : route('suppliers.store') }}" class="form-narrow card card-body" data-once novalidate>
        @csrf
        @if ($editing) @method('PUT') @endif

        <x-form.input name="name" label="Raison sociale" :value="$supplier->name" maxlength="150" autocomplete="organization" />
        <x-form.input name="contact_name" label="Interlocuteur" :value="$supplier->contact_name" optional maxlength="120" autocomplete="name" />
        <div class="row">
            <div class="col-md-6">
                <x-form.input name="email" label="Adresse e-mail" type="email" :value="$supplier->email" optional maxlength="190" autocomplete="email" inputmode="email" />
            </div>
            <div class="col-md-6">
                <x-form.input name="phone" label="Téléphone" type="tel" :value="$supplier->phone" optional maxlength="30" autocomplete="tel" inputmode="tel" placeholder="+225 07 08 09 10 11" />
            </div>
        </div>
        <x-form.input name="address" label="Adresse" :value="$supplier->address" optional maxlength="255" autocomplete="street-address" help="Commune, quartier, repère." />
        <x-form.input name="lead_time_days" label="Délai moyen de livraison" type="number" :value="$supplier->lead_time_days" min="0" max="365" suffix="jours" inputmode="numeric" class="input-qty tabular"
            help="Il sert à proposer la date de livraison prévue d'une commande." />

        <div class="form-check mb-3">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $supplier->is_active))>
            <label class="form-check-label" for="is_active">Fournisseur actif</label>
            <div class="form-text">Un fournisseur inactif n'est plus proposé pour une nouvelle commande.</div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">{{ $editing ? 'Enregistrer les modifications' : 'Créer le fournisseur' }}</button>
            <a class="btn btn-outline-secondary" href="{{ route('suppliers.index') }}">Annuler</a>
        </div>
    </form>
@endsection
