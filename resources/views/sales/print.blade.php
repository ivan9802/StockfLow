@extends('layouts.print')

@section('title', 'Bon de sortie '.$sale->reference)
@section('back', route('sales.show', $sale))
@section('doc-title', 'Bon de sortie')
@section('doc-ref', $sale->reference)
@section('doc-date', Format::dateTime($sale->sold_at))

@section('content')
    @if ($sale->isCancelled())
        <div class="stamp">Annulé le {{ Format::date($sale->cancelled_at) }}</div>
    @endif

    <dl class="meta">
        <div><dt>Point de vente</dt><dd>{{ $sale->warehouse->name }}</dd></div>
        <div><dt>Vendeur</dt><dd>{{ $sale->user->name }}</dd></div>
        <div><dt>Client</dt><dd>{{ $sale->customer_name ?? 'Client de passage' }}</dd></div>
    </dl>

    <table class="lines">
        <thead><tr><th>Référence</th><th>Désignation</th><th class="num">Qté</th><th class="num">Prix unitaire</th><th class="num">Montant</th></tr></thead>
        <tbody>
            @foreach ($sale->items as $item)
                <tr>
                    <td class="code">{{ $item->product->sku }}</td>
                    <td>{{ $item->product->name }}</td>
                    <td class="num">{{ Format::number($item->quantity) }}</td>
                    <td class="num">{{ Format::money($item->unit_price) }}</td>
                    <td class="num">{{ Format::money($item->line_total) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot><tr><td colspan="4">Total</td><td class="num">{{ Format::money($sale->total_amount) }}</td></tr></tfoot>
    </table>

    <p class="muted">Ce bon de sortie atteste la remise des articles. Ce n'est pas une facture.</p>

    <div class="signatures">
        <div>Le vendeur</div>
        <div>Le client</div>
    </div>
@endsection
