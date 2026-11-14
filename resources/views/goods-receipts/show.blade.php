@extends('layouts.app')

@section('title', 'Réception '.$receipt->reference)

@section('content')
    <x-page-header :title="'Réception '.$receipt->reference" :breadcrumbs="['Réceptions' => route('goods-receipts.index')]">
        <x-slot:context>
            <span>{{ Format::dateTime($receipt->received_at) }} · {{ $receipt->warehouse->name }} · {{ $receipt->user->name }}</span>
        </x-slot:context>
        <x-slot:actions>
            <a class="btn btn-primary" href="{{ route('goods-receipts.print', $receipt) }}"><i class="bi bi-printer" aria-hidden="true"></i> Bon de réception</a>
        </x-slot:actions>
    </x-page-header>

    <section class="card mb-4">
        <div class="card-body">
            <dl class="definition-list">
                <dt>Fournisseur</dt><dd>{{ $receipt->supplier->name }}</dd>
                <dt>Commande</dt>
                <dd>
                    @if ($receipt->purchaseOrder)
                        <a class="code" href="{{ route('purchase-orders.show', $receipt->purchaseOrder) }}">{{ $receipt->purchaseOrder->reference }}</a>
                    @else
                        Réception sans commande
                    @endif
                </dd>
                @if ($receipt->delivery_note_number)<dt>Bon de livraison</dt><dd>{{ $receipt->delivery_note_number }}</dd>@endif
                @if ($receipt->notes)<dt>Notes</dt><dd>{{ $receipt->notes }}</dd>@endif
            </dl>
        </div>
    </section>

    <section class="table-card mb-3">
        <table class="table data-table">
            <caption class="visually-hidden">Articles reçus</caption>
            <thead><tr><th scope="col">Produit</th><th scope="col" class="num">Quantité</th><th scope="col" class="num col-p2">Prix unitaire</th><th scope="col" class="num col-p2">Montant</th></tr></thead>
            <tbody>
                @foreach ($receipt->items as $item)
                    <tr>
                        <td>
                            <a class="row-title" href="{{ route('products.show', $item->product_id) }}">{{ $item->product->name }}</a>
                            <span class="row-sub code">{{ $item->product->sku }}</span>
                        </td>
                        <td class="num fw-semibold">{{ Format::number($item->quantity) }}</td>
                        <td class="num col-p2">{{ Format::money($item->unit_cost) }}</td>
                        <td class="num col-p2">{{ Format::money($item->quantity * $item->unit_cost) }}</td>
                    </tr>
                @endforeach
                <tr><th scope="row" colspan="3" class="text-end">Total</th><td class="num fw-bold">{{ Format::money($receipt->totalAmount()) }}</td></tr>
            </tbody>
        </table>
    </section>

    <p class="text-secondary small">
        Un bon de réception enregistré ne s'annule pas. En cas d'erreur, passez un ajustement manuel justifié qui cite {{ $receipt->reference }}.
    </p>
@endsection
