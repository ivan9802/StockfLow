@extends('layouts.app')

@section('title', 'Transfert '.$transfer->reference)

@section('content')
    <x-page-header :title="'Transfert '.$transfer->reference" :breadcrumbs="['Transferts' => route('transfers.index')]">
        <x-slot:context>
            <x-status-badge :status="$transfer->status" />
            <span>{{ Format::dateTime($transfer->transferred_at) }} par {{ $transfer->user->name }}</span>
        </x-slot:context>
        <x-slot:actions>
            <a class="btn btn-primary" href="{{ route('transfers.print', $transfer) }}"><i class="bi bi-printer" aria-hidden="true"></i> Bon de transfert</a>
            @unless ($transfer->isCancelled())
                <x-reason-action :action="route('transfers.cancel', $transfer)" label="Annuler le transfert"
                    question="La marchandise reviendra à {{ $transfer->fromWarehouse->name }}. L'annulation est refusée si une partie a déjà été vendue ou déplacée." />
            @endunless
        </x-slot:actions>
    </x-page-header>

    @if ($transfer->isCancelled())
        <x-alert type="warning" title="Transfert annulé">
            Le {{ Format::dateTime($transfer->cancelled_at) }} par {{ $transfer->cancelledBy?->name }} : {{ $transfer->cancel_reason }}
        </x-alert>
    @endif

    <section class="card mb-4">
        <div class="card-body">
            <dl class="definition-list">
                <dt>Départ</dt><dd>{{ $transfer->fromWarehouse->name }}</dd>
                <dt>Arrivée</dt><dd>{{ $transfer->toWarehouse->name }}</dd>
                @if ($transfer->notes)<dt>Note</dt><dd>{{ $transfer->notes }}</dd>@endif
            </dl>
        </div>
    </section>

    <section class="table-card">
        <table class="table data-table">
            <caption class="visually-hidden">Produits transférés</caption>
            <thead><tr><th scope="col">Produit</th><th scope="col" class="col-p2">Référence</th><th scope="col" class="num">Quantité</th></tr></thead>
            <tbody>
                @foreach ($transfer->items as $item)
                    <tr>
                        <td><a class="row-title" href="{{ route('products.show', $item->product_id) }}">{{ $item->product->name }}</a></td>
                        <td class="col-p2"><span class="code">{{ $item->product->sku }}</span></td>
                        <td class="num fw-semibold">{{ Format::number($item->quantity) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>
@endsection
