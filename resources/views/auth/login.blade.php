@extends('layouts.guest')

@section('title', 'Connexion')

@section('content')
    <div class="guest-card">
        <h1 class="h2 mb-3">Connexion</h1>

        @if ($errors->any())
            <x-alert type="danger" :message="$errors->first()" data-autofocus />
        @endif

        <form method="POST" action="{{ route('login.store') }}" novalidate>
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

            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="remember" name="remember" value="1" @checked(old('remember'))>
                <label class="form-check-label" for="remember">Rester connecté sur cet appareil</label>
            </div>

            <button type="submit" class="btn btn-primary w-100">Se connecter</button>
        </form>

        <p class="form-text mt-3 mb-0">
            Mot de passe oublié ? Adressez-vous à votre administrateur, qui vous en attribuera un nouveau.
        </p>
    </div>
@endsection
