@extends('layouts.app')

@php $editing = $user->exists; @endphp

@section('title', $editing ? 'Modifier '.$user->name : 'Nouveau compte')

@section('content')
    <x-page-header :title="$editing ? 'Modifier le compte' : 'Nouveau compte'" :breadcrumbs="['Utilisateurs' => route('users.index')]" />

    <form method="POST" action="{{ $editing ? route('users.update', $user) : route('users.store') }}" class="form-narrow card card-body" data-once novalidate>
        @csrf
        @if ($editing) @method('PUT') @endif

        <x-form.input name="name" label="Nom complet" :value="$user->name" maxlength="120" autocomplete="off" />
        <x-form.input name="email" label="Adresse e-mail" type="email" :value="$user->email" maxlength="190" autocomplete="off" inputmode="email" />
        <x-form.select name="role" label="Profil" :options="$roles" :value="$user->role" />
        <x-form.select name="warehouse_id" label="Emplacement d'affectation" :options="$warehouses" :value="$user->warehouse_id" placeholder="Aucun" optional
            help="Obligatoire pour un vendeur : il ne vend que depuis ce point de vente." />

        @unless ($editing)
            <x-form.password name="password" label="Mot de passe provisoire (facultatif)" autocomplete="new-password" :required="false"
                help="Laissé vide, un mot de passe provisoire est généré et affiché une fois. Dans les deux cas, il devra être changé à la première connexion." />
        @endunless

        <div class="form-check mb-3">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $user->is_active))>
            <label class="form-check-label" for="is_active">Compte actif</label>
            <div class="form-text">Un compte désactivé ne peut plus se connecter ; une session déjà ouverte est fermée à la requête suivante.</div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">{{ $editing ? 'Enregistrer les modifications' : 'Créer le compte' }}</button>
            <a class="btn btn-outline-secondary" href="{{ route('users.index') }}">Annuler</a>
        </div>
    </form>
@endsection
