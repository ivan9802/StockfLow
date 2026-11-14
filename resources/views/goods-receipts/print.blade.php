@extends('layouts.print')

@section('title', 'Bon de réception '.$receipt->reference)
@section('back', route('goods-receipts.show', $receipt))
@section('doc-title', 'Bon de réception')
@section('doc-ref', $receipt->reference)
@section('doc-date', Format::dateTime($receipt->received_at))

@section('content')
    <dl class="meta">
        <div><dt>Fournisseur</dt><dd>{{ $receipt->supplier->name }}</dd></div>
        <div><dt>Emplacement</dt><dd>{{ $receipt->warehouse->name }}</dd></div>
        <div><dt>Commande</dt><dd>{{ $receipt->purchaseOrder?->reference ?? 'Sans commande' }}</dd></div>
        <div><dt>Bon de livraison</dt><dd>{{ $receipt->delivery_note_number ?: 'Non renseigné' }}</dd></div>
        <div><dt>Réceptionné par</dt><dd>{{ $receipt->user->name }}</dd></div>
    </dl>

    <table class="lines">
        <thead><tr><th>Référence</th><th>Désignation</th><th class="num">Qté</th><th class="num">Prix unitaire</th><th class="num">Montant</th></tr></thead>
        <tbody>
            @foreach ($receipt->items as $item)
                <tr>
                    <td class="code">{{ $item->product->sku }}</td>
                    <td>{{ $item->product->name }}</td>
                    <td class="num">{{ Format::number($item->quantity) }}</td>
                    <td class="num">{{ Format::money($item->unit_cost) }}</td>
                    <td class="num">{{ Format::money($item->quantity * $item->unit_cost) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot><tr><td colspan="4">Total</td><td class="num">{{ Format::money($receipt->totalAmount()) }}</td></tr></tfoot>
    </table>

    <div class="signatures">
        <div>Le livreur</div>
        <div>Le réceptionnaire</div>
    </div>
@endsection
