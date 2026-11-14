@extends('layouts.app')

@php $editing = $category->exists; @endphp

@section('title', $editing ? 'Modifier '.$category->name : 'Nouvelle catégorie')

@section('content')
    <x-page-header :title="$editing ? 'Modifier la catégorie' : 'Nouvelle catégorie'" :breadcrumbs="['Catégories' => route('categories.index')]" />

    <form method="POST" action="{{ $editing ? route('categories.update', $category) : route('categories.store') }}" class="form-narrow card card-body" data-once novalidate>
        @csrf
        @if ($editing) @method('PUT') @endif

        <x-form.input name="name" label="Nom" :value="$category->name" maxlength="100" autocomplete="off" />

        @if ($editing)
            <div class="mb-3">
                <span class="form-label d-block">Code</span>
                <span class="code">{{ $category->code }}</span>
                <div class="form-text">Le code préfixe les références déjà attribuées : il ne se modifie plus.</div>
            </div>
        @else
            <x-form.input name="code" label="Code" :value="$category->code" maxlength="4" autocomplete="off" class="input-qty text-uppercase text-start"
                help="3 ou 4 lettres. Il préfixe la référence des produits de la catégorie, par exemple IMP-000042." />
        @endif

        <x-form.textarea name="description" label="Description" :value="$category->description" optional maxlength="500" />

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">{{ $editing ? 'Enregistrer les modifications' : 'Créer la catégorie' }}</button>
            <a class="btn btn-outline-secondary" href="{{ route('categories.index') }}">Annuler</a>
        </div>
    </form>
@endsection
