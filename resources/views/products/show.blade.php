@extends('layouts.app')

@section('title', $product->name)

@section('content')
    <x-page-header :title="$product->name" :breadcrumbs="['Produits' => route('products.index')]">
        <x-slot:context>
            <span class="code">{{ $product->sku }}</span>
            <x-stock-badge :quantity="$product->totalStock()" :threshold="$product->alert_threshold" />
            @unless ($product->is_active)
                <x-status-badge :active="false" />
            @endunless
        </x-slot:context>
        @can('catalog.manage')
            <x-slot:actions>
                <a class="btn btn-primary" href="{{ route('products.edit', $product) }}"><i class="bi bi-pencil" aria-hidden="true"></i> Modifier</a>
                <form method="POST" action="{{ route('products.destroy', $product) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger"
                        data-confirm="Retirer {{ $product->name }} du catalogue ? Son historique de mouvements reste consultable."
                        data-confirm-title="Supprimer le produit" data-confirm-label="Supprimer">
                        <i class="bi bi-trash" aria-hidden="true"></i> Supprimer
                    </button>
                </form>
            </x-slot:actions>
        @endcan
    </x-page-header>

    <div class="row g-4">
        <div class="col-lg-7">
            <section class="card mb-4">
                <div class="card-header"><h2>Fiche</h2></div>
                <div class="card-body">
                    <dl class="definition-list">
                        <dt>Catégorie</dt>
                        <dd>{{ $product->category->name }}</dd>
                        @can('suppliers.manage')
                            <dt>Fournisseur principal</dt>
                            <dd>{{ $product->supplier?->name ?? 'Non renseigné' }}</dd>
                        @endcan
                        @can('prices.view-purchase')
                            <dt>Prix d'achat</dt>
                            <dd class="tabular">{{ Format::money($product->purchase_price) }}</dd>
                        @endcan
                        <dt>Prix de vente</dt>
                        <dd class="tabular fw-semibold">{{ Format::money($product->selling_price) }}</dd>
                        <dt>Seuil d'alerte</dt>
                        <dd class="tabular">{{ Format::number($product->alert_threshold) }} (sur le stock total)</dd>
                        @if ($product->description)
                            <dt>Description</dt>
                            <dd>{{ $product->description }}</dd>
                        @endif
                    </dl>
                </div>
            </section>

            <section class="table-card mb-4">
                <table class="table data-table">
                    <caption class="visually-hidden">Stock par emplacement</caption>
                    <thead>
                        <tr>
                            <th scope="col">Emplacement</th>
                            <th scope="col" class="num">Quantité</th>
                            <th scope="col" class="num col-p2">Seuil de réassort</th>
                            <th scope="col">État</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($product->stockLevels->sortBy('warehouse_id') as $level)
                            <tr>
                                <td>{{ $level->warehouse->name }}</td>
                                <td class="num fw-semibold">{{ Format::number($level->quantity) }}</td>
                                <td class="num col-p2">{{ $level->reorder_point !== null ? Format::number($level->reorder_point) : 'Aucun' }}</td>
                                <td><x-stock-badge :quantity="$level->quantity" :threshold="$level->reorder_point" /></td>
                            </tr>
                        @endforeach
                        <tr>
                            <th scope="row">Total</th>
                            <td class="num fw-semibold">{{ Format::number($product->totalStock()) }}</td>
                            <td class="col-p2"></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </div>

        <div class="col-lg-5">
            <section class="card mb-4">
                <div class="card-body text-center">
                    @if ($product->image_path)
                        <img class="product-image mb-3" src="{{ route('products.image', $product) }}" alt="Photo de {{ $product->name }}" width="280" height="280">
                    @endif
                    {{-- SVG produit par le serveur à partir d'un code validé : inséré tel quel. --}}
                    <div class="barcode" role="img" aria-label="Code-barres {{ $product->barcode }}">{!! $barcodeSvg !!}</div>
                    <div class="code mt-1">{{ $product->barcode }}</div>
                </div>
            </section>
        </div>
    </div>

    @can('movements.view')
        <section class="table-card">
            <div class="card-header px-3 py-2">
                <h2>Derniers mouvements</h2>
                @if (Route::has('movements.index'))
                    <a href="{{ route('movements.index', ['product_id' => $product->id]) }}">Toute la fiche de stock</a>
                @endif
            </div>
            @if ($movements->isEmpty())
                <x-empty-state icon="arrow-left-right" title="Aucun mouvement pour ce produit." />
            @else
                @include('movements._rows', ['movements' => $movements, 'showProduct' => false])
            @endif
        </section>
    @endcan
@endsection
