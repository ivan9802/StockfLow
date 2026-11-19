@extends('layouts.guest')

@section('title', 'Connexion')

@section('aside')
    <div class="name">Stock<span>Flow</span></div>
    <div>
        <p class="pitch">Le stock de l'entrepôt et de la boutique, à jour à chaque vente, réception et transfert.</p>
        <ul>
            <li><i class="bi bi-check2-circle" aria-hidden="true"></i> Ventes refusées quand le stock manque</li>
            <li><i class="bi bi-check2-circle" aria-hidden="true"></i> Historique de chaque mouvement, avec son auteur</li>
            <li><i class="bi bi-check2-circle" aria-hidden="true"></i> Alertes avant la rupture</li>
        </ul>
    </div>
    <div class="company">{{ config('stockflow.company.name') }}</div>
@endsection

@section('content')
    <div class="guest-card">
        <h1 class="h2 mb-1">Connexion</h1>
        <p class="text-secondary mb-4">Identifiez-vous avec votre adresse professionnelle.</p>

        @if ($errors->any())
            <x-alert type="danger" :message="$errors->first()" data-autofocus />
        @endif

        <form method="POST" action="{{ route('login.store') }}" data-once novalidate>
            @csrf

            <div class="mb-3">
                <label class="form-label" for="email">Adresse e-mail</label>
                <input type="email" class="form-control" id="email" name="email" value="{{ old('email') }}"
                    autocomplete="username" inputmode="email" required @unless ($errors->any()) autofocus @endunless>
            </div>

            <div class="mb-3">
                <label class="form-label" for="password">Mot de passe</label>
                <div class="input-group">
                    <input type="password" class="form-control" id="password" name="password" autocomplete="current-password" required>
                    <button type="button" class="btn btn-outline-secondary" data-password-toggle="password" aria-pressed="false">Afficher</button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100">Se connecter</button>
        </form>

        <p class="form-text mt-3 mb-0">
            Mot de passe oublié ? Adressez-vous à votre administrateur, qui vous en attribuera un nouveau.
        </p>
    </div>
@endsection
