@extends('layouts.app')

@section('title', 'Point de vente')

@section('content')
    <x-page-header title="Point de vente">
        <x-slot:context>
            <i class="bi bi-geo-alt" aria-hidden="true"></i> {{ $store->name }}
        </x-slot:context>
        @if ($stores->count() > 1)
            <x-slot:actions>
                <form method="GET" action="{{ route('sales.create') }}" class="d-flex align-items-center gap-2">
                    <label class="form-label mb-0" for="pos-store">Emplacement</label>
                    <select class="form-select" id="pos-store" name="warehouse_id" data-autosubmit>
                        @foreach ($stores as $s)
                            <option value="{{ $s->id }}" @selected($s->id === $store->id)>{{ $s->name }}</option>
                        @endforeach
                    </select>
                </form>
            </x-slot:actions>
        @endif
    </x-page-header>

    <form method="POST" action="{{ route('sales.store') }}" data-sale-cart novalidate>
        @csrf
        <input type="hidden" name="warehouse_id" value="{{ $store->id }}">
        <input type="hidden" name="form_token" value="{{ $formToken }}">

        <div data-cart-feedback aria-live="assertive">
            @if ($errors->any())
                <x-alert type="danger" title="La vente n'a pas été enregistrée" :message="$errors->first()" />
            @endif
        </div>

        <div class="row g-4">
            <div class="col-xl-8">
                <section class="card mb-4">
                    <div class="card-body">
                        <x-product-search id="pos-search" :warehouse="$store->id" label="Rechercher un produit à vendre"
                            placeholder="Nom, référence, ou lecture du code-barres" />
                        <div class="form-text">Raccourci : Entrée ajoute le produit surligné. Un lecteur de code-barres ajoute directement le produit lu.</div>
                    </div>
                </section>

                <section class="table-card">
                    <table class="table data-table mb-0">
                        <caption class="visually-hidden">Panier</caption>
                        <thead>
                            <tr>
                                <th scope="col">Produit</th>
                                <th scope="col" class="num col-p2">Disponible</th>
                                <th scope="col" class="num">Quantité</th>
                                <th scope="col" class="num col-p2">Prix unitaire</th>
                                <th scope="col" class="num">Montant</th>
                                <th scope="col" class="actions"><span class="visually-hidden">Retirer</span></th>
                            </tr>
                        </thead>
                        <tbody data-cart-rows></tbody>
                    </table>
                    <div data-cart-empty>
                        <x-empty-state icon="cart" title="Le panier est vide.">Recherchez un produit pour commencer la vente.</x-empty-state>
                    </div>
                </section>
            </div>

            <div class="col-xl-4">
                <section class="card">
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label" for="customer_name">Nom du client <span class="optional">(facultatif)</span></label>
                            <input type="text" class="form-control" id="customer_name" name="customer_name" maxlength="150" autocomplete="off">
                        </div>

                        <dl class="d-flex justify-content-between align-items-baseline mb-1">
                            <dt class="fw-normal text-secondary">Articles</dt>
                            <dd class="mb-0 tabular" data-cart-units>0</dd>
                        </dl>
                        <dl class="d-flex justify-content-between align-items-baseline mb-3">
                            <dt class="fw-semibold">Total</dt>
                            <dd class="mb-0 tabular fw-bold fs-4" data-cart-total>0 F CFA</dd>
                        </dl>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="print-after" data-print-after checked>
                            <label class="form-check-label" for="print-after">Imprimer le bon de sortie après l'enregistrement</label>
                        </div>

                        <button type="submit" class="btn btn-primary w-100" data-cart-submit disabled>
                            <i class="bi bi-check2-circle" aria-hidden="true"></i> Enregistrer la vente
                        </button>
                        <button type="button" class="btn btn-outline-secondary w-100 mt-2" data-cart-clear>Vider le panier</button>
                    </div>
                </section>
            </div>
        </div>
    </form>
@endsection
