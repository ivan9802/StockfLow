@extends('layouts.app')

@section('title', 'Catégories')

@section('content')
    <x-page-header title="Catégories">
        <x-slot:actions>
            <a class="btn btn-primary" href="{{ route('categories.create') }}"><i class="bi bi-plus-lg" aria-hidden="true"></i> Nouvelle catégorie</a>
        </x-slot:actions>
    </x-page-header>

    <div class="table-card">
        @if ($categories->isEmpty())
            <x-empty-state icon="tags" title="Aucune catégorie pour l'instant.">
                Créez une première catégorie pour pouvoir ajouter des produits.
            </x-empty-state>
        @else
            <table class="table table-hover data-table">
                <caption class="visually-hidden">Liste des catégories</caption>
                <thead>
                    <tr>
                        <th scope="col">Catégorie</th>
                        <th scope="col">Code</th>
                        <th scope="col" class="col-p2">Description</th>
                        <th scope="col" class="num">Produits</th>
                        <th scope="col" class="actions"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($categories as $category)
                        <tr>
                            <td><a class="row-title" href="{{ route('products.index', ['category_id' => $category->id]) }}">{{ $category->name }}</a></td>
                            <td><span class="code">{{ $category->code }}</span></td>
                            <td class="col-p2">{{ $category->description }}</td>
                            <td class="num">{{ Format::number($category->products_count) }}</td>
                            <td class="actions">
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('categories.edit', $category) }}">Modifier<span class="visually-hidden"> {{ $category->name }}</span></a>
                                <form method="POST" action="{{ route('categories.destroy', $category) }}" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger"
                                        data-confirm="Supprimer la catégorie {{ $category->name }} ?" data-confirm-title="Supprimer la catégorie" data-confirm-label="Supprimer">
                                        Supprimer<span class="visually-hidden"> {{ $category->name }}</span>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
