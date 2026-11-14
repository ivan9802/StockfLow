@extends('layouts.app')

@section('title', 'Commande '.$order->reference)

@php use App\Enums\PurchaseOrderStatus; @endphp

@section('content')
    <x-page-header :title="'Commande '.$order->reference" :breadcrumbs="['Commandes' => route('purchase-orders.index')]">
        <x-slot:context>
            <x-status-badge :status="$order->status" />
            <span>{{ $order->supplier->name }} · créée par {{ $order->user->name }}</span>
        </x-slot:context>
        <x-slot:actions>
            @if ($order->status === PurchaseOrderStatus::Draft)
                <form method="POST" action="{{ route('purchase-orders.confirm', $order) }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-primary" data-confirm="Passer la commande {{ $order->reference }} au fournisseur ? Elle ne sera plus modifiable."
                        data-confirm-title="Passer la commande" data-confirm-label="Passer la commande" data-confirm-tone="primary">
                        <i class="bi bi-send" aria-hidden="true"></i> Passer la commande
                    </button>
                </form>
                <a class="btn btn-outline-secondary" href="{{ route('purchase-orders.edit', $order) }}"><i class="bi bi-pencil" aria-hidden="true"></i> Modifier</a>
            @endif
            @if ($order->status->canReceive())
                <a class="btn btn-primary" href="{{ route('purchase-orders.receive', $order) }}"><i class="bi bi-box-arrow-in-down" aria-hidden="true"></i> Réceptionner une livraison</a>
            @endif
            @if (in_array($order->status, [PurchaseOrderStatus::Draft, PurchaseOrderStatus::Ordered], true))
                <x-reason-action :action="route('purchase-orders.cancel', $order)" label="Annuler"
                    question="Annuler la commande {{ $order->reference }} ? Rien n'a encore été reçu." title="Annuler la commande" />
            @endif
            @if ($order->status === PurchaseOrderStatus::PartiallyReceived)
                <x-reason-action :action="route('purchase-orders.close', $order)" label="Solder" tone="secondary" icon="archive"
                    question="Solder la commande : le reliquat ne sera plus attendu. Le stock déjà reçu n'est pas modifié." title="Solder la commande" />
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($order->closure_reason)
        <x-alert type="info" :title="$order->status->label()" :message="$order->closure_reason" />
    @endif

    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <section class="card h-100">
                <div class="card-body">
                    <dl class="definition-list">
                        <dt>Fournisseur</dt><dd>{{ $order->supplier->name }}</dd>
                        <dt>Livraison à</dt><dd>{{ $order->warehouse->name }}</dd>
                        <dt>Commandée le</dt><dd>{{ Format::date($order->ordered_at) ?: 'Pas encore passée' }}</dd>
                        <dt>Livraison prévue</dt><dd>{{ Format::date($order->expected_at) ?: 'Non fixée' }}</dd>
                        @if ($order->notes)<dt>Notes</dt><dd>{{ $order->notes }}</dd>@endif
                    </dl>
                </div>
            </section>
        </div>
        <div class="col-lg-6">
            <section class="card h-100">
                <div class="card-body">
                    <p class="overline mb-1">Montant commandé</p>
                    <p class="kpi-value tabular fs-3 fw-semibold mb-3">{{ Format::money($order->totalAmount()) }}</p>
                    <p class="overline mb-1">Réception</p>
                    <progress max="100" value="{{ $order->receivedPercent() }}" class="w-100" aria-label="Reçu à {{ $order->receivedPercent() }} %"></progress>
                    <p class="small text-secondary mb-0">{{ $order->receivedPercent() }} % des quantités reçues</p>
                </div>
            </section>
        </div>
    </div>

    <section class="table-card mb-4">
        <table class="table data-table">
            <caption class="visually-hidden">Articles commandés</caption>
            <thead>
                <tr>
                    <th scope="col">Produit</th>
                    <th scope="col" class="num">Commandé</th>
                    <th scope="col" class="num">Reçu</th>
                    <th scope="col" class="num">Reste</th>
                    <th scope="col" class="num col-p2">Prix unitaire</th>
                    <th scope="col" class="num col-p2">Montant</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($order->items as $item)
                    <tr>
                        <td>
                            <a class="row-title" href="{{ route('products.show', $item->product_id) }}">{{ $item->product->name }}</a>
                            <span class="row-sub code">{{ $item->product->sku }}</span>
                        </td>
                        <td class="num">{{ Format::number($item->quantity_ordered) }}</td>
                        <td class="num">{{ Format::number($item->quantity_received) }}</td>
                        <td class="num fw-semibold">{{ Format::number($item->remaining()) }}</td>
                        <td class="num col-p2">{{ Format::money($item->unit_cost) }}</td>
                        <td class="num col-p2">{{ Format::money($item->unit_cost * $item->quantity_ordered) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>

    <section class="table-card">
        <div class="card-header px-3 py-2"><h2>Livraisons reçues</h2></div>
        @if ($order->receipts->isEmpty())
            <x-empty-state icon="box-arrow-in-down" title="Aucune livraison reçue pour cette commande." />
        @else
            <table class="table data-table">
                <thead><tr><th scope="col">Bon de réception</th><th scope="col">Date</th><th scope="col" class="col-p2">Bon de livraison</th><th scope="col">Par</th></tr></thead>
                <tbody>
                    @foreach ($order->receipts as $receipt)
                        <tr>
                            <td><a class="row-title code" href="{{ route('goods-receipts.show', $receipt) }}">{{ $receipt->reference }}</a></td>
                            <td>{{ Format::dateTime($receipt->received_at) }}</td>
                            <td class="col-p2">{{ $receipt->delivery_note_number }}</td>
                            <td>{{ $receipt->user->name }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>
@endsection
