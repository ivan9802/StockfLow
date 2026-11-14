@extends('layouts.app')

@section('title', 'Utilisateurs')

@section('content')
    <x-page-header title="Utilisateurs">
        <x-slot:actions>
            <a class="btn btn-primary" href="{{ route('users.create') }}"><i class="bi bi-person-plus" aria-hidden="true"></i> Nouveau compte</a>
        </x-slot:actions>
    </x-page-header>

    <div class="table-card">
        <div class="table-responsive">
            <table class="table table-hover data-table">
                <caption class="visually-hidden">Comptes utilisateurs</caption>
                <thead>
                    <tr>
                        <th scope="col">Nom</th>
                        <th scope="col" class="col-p2">Adresse e-mail</th>
                        <th scope="col">Profil</th>
                        <th scope="col" class="col-p3">Emplacement</th>
                        <th scope="col" class="col-p3">Dernière connexion</th>
                        <th scope="col">État</th>
                        <th scope="col" class="actions"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $user)
                        <tr>
                            <td>
                                <a class="row-title" href="{{ route('users.edit', $user) }}">{{ $user->name }}</a>
                                @if ($user->must_change_password)<span class="row-sub">Mot de passe provisoire</span>@endif
                            </td>
                            <td class="col-p2">{{ $user->email }}</td>
                            <td>{{ $user->role->label() }}</td>
                            <td class="col-p3">{{ $user->warehouse?->name }}</td>
                            <td class="col-p3">{{ $user->last_login_at ? Format::dateTime($user->last_login_at) : 'Jamais' }}</td>
                            <td><x-status-badge :active="$user->is_active" /></td>
                            <td class="actions">
                                @unless ($user->is(auth()->user()))
                                    <form method="POST" action="{{ route('users.reset-password', $user) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-secondary"
                                            data-confirm="Attribuer un nouveau mot de passe provisoire à {{ $user->name }} ? Ses sessions ouvertes seront fermées."
                                            data-confirm-title="Réinitialiser le mot de passe" data-confirm-label="Réinitialiser" data-confirm-tone="primary">
                                            Réinitialiser le mot de passe<span class="visually-hidden"> de {{ $user->name }}</span>
                                        </button>
                                    </form>
                                @endunless
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <p class="text-secondary small mt-3">Un compte ne se supprime pas : désactivez-le. L'historique des opérations garde ainsi son auteur.</p>
@endsection
