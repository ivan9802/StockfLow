@extends('layouts.app')

@section('title', 'Vente '.$sale->reference)

@section('content')
    <x-page-header :title="'Vente '.$sale->reference" :breadcrumbs="['Ventes' => route('sales.index')]">
        <x-slot:context>
            <x-status-badge :status="$sale->status" />
            <span>{{ Format::dateTime($sale->sold_at) }} · {{ $sale->warehouse->name }} · {{ $sale->user->name }}</span>
        </x-slot:context>
        <x-slot:actions>
            <a class="btn btn-primary" href="{{ route('sales.print', $sale) }}"><i class="bi bi-printer" aria-hidden="true"></i> Bon de sortie</a>
            @can('cancel', $sale)
                <x-reason-action :action="route('sales.cancel', $sale)" label="Annuler la vente"
                    question="Les articles de la vente {{ $sale->reference }} seront réintégrés au stock de {{ $sale->warehouse->name }}." />
            @endcan
        </x-slot:actions>
    </x-page-header>

    @if ($sale->isCancelled())
        <x-alert type="warning" title="Vente annulée">
            Le {{ Format::dateTime($sale->cancelled_at) }} par {{ $sale->cancelledBy?->name }} : {{ $sale->cancel_reason }}
        </x-alert>
    @endif

    <section class="table-card">
        <table class="table data-table">
            <caption class="visually-hidden">Articles vendus</caption>
            <thead>
                <tr>
                    <th scope="col">Produit</th>
                    <th scope="col" class="num">Quantité</th>
                    <th scope="col" class="num col-p2">Prix unitaire</th>
                    <th scope="col" class="num">Montant</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($sale->items as $item)
                    <tr>
                        <td>
                            <a class="row-title" href="{{ route('products.show', $item->product_id) }}">{{ $item->product->name }}</a>
                            <span class="row-sub code">{{ $item->product->sku }}</span>
                        </td>
                        <td class="num">{{ Format::number($item->quantity) }}</td>
                        <td class="num col-p2">{{ Format::money($item->unit_price) }}</td>
                        <td class="num fw-semibold">{{ Format::money($item->line_total) }}</td>
                    </tr>
                @endforeach
                <tr>
                    <th scope="row" colspan="3" class="text-end">Total</th>
                    <td class="num fw-bold">{{ Format::money($sale->total_amount) }}</td>
                </tr>
            </tbody>
        </table>
    </section>

    <p class="text-secondary small mt-3">Client : {{ $sale->customer_name ?? 'Client de passage' }}</p>
@endsection
