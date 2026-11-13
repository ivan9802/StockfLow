@extends('layouts.app')

@section('title', 'Changer mon mot de passe')

@section('content')
    <x-page-header title="Changer mon mot de passe" />

    @if ($forced)
        <x-alert type="warning" title="Choisissez votre propre mot de passe">
            Celui que vous utilisez vous a été attribué par un administrateur. Remplacez-le pour continuer.
        </x-alert>
    @endif

    <form method="POST" action="{{ route('password.update') }}" class="form-narrow card card-body" novalidate>
        @csrf
        @method('PUT')

        <x-form.password name="current_password" label="Mot de passe actuel" autocomplete="current-password" />
        <x-form.password name="password" label="Nouveau mot de passe" autocomplete="new-password"
            help="12 caractères au moins. Une phrase de plusieurs mots fait un bon mot de passe." />

        <div>
            <button type="submit" class="btn btn-primary">Enregistrer le mot de passe</button>
        </div>
    </form>
@endsection
