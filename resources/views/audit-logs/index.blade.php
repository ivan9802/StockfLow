@extends('layouts.app')

@section('title', "Journal d'audit")

@section('content')
    <x-page-header title="Journal d'audit" />

    <div data-live-list>
        <form method="GET" action="{{ route('audit-logs.index') }}" class="filter-bar" data-live-form role="search">
            <div class="filter filter-search">
                <label class="form-label" for="q">Recherche</label>
                <input type="search" class="form-control" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Texte de la description" autocomplete="off">
            </div>
            <div class="filter">
                <label class="form-label" for="action">Action</label>
                <select class="form-select" id="action" name="action">
                    <option value="">Toutes</option>
                    @foreach ($actions as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['action'] ?? null) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter">
                <label class="form-label" for="subject">Objet</label>
                <select class="form-select" id="subject" name="subject">
                    <option value="">Tous</option>
                    @foreach ($subjects as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['subject'] ?? null) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter">
                <label class="form-label" for="user_id">Utilisateur</label>
                <select class="form-select" id="user_id" name="user_id">
                    <option value="">Tous</option>
                    @foreach ($users as $id => $name)
                        <option value="{{ $id }}" @selected(($filters['user_id'] ?? null) == $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter">
                <label class="form-label" for="from">Du</label>
                <input type="date" class="form-control" id="from" name="from" value="{{ $filters['from'] ?? '' }}">
            </div>
            <div class="filter">
                <label class="form-label" for="to">Au</label>
                <input type="date" class="form-control" id="to" name="to" value="{{ $filters['to'] ?? '' }}">
            </div>
            <noscript><button type="submit" class="btn btn-outline-secondary">Filtrer</button></noscript>
        </form>

        <div data-live-target aria-live="polite">
            @include('audit-logs._table')
        </div>
    </div>
@endsection
