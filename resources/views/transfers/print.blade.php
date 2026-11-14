@extends('layouts.print')

@section('title', 'Bon de transfert '.$transfer->reference)
@section('back', route('transfers.show', $transfer))
@section('doc-title', 'Bon de transfert')
@section('doc-ref', $transfer->reference)
@section('doc-date', Format::dateTime($transfer->transferred_at))

@section('content')
    @if ($transfer->isCancelled())
        <div class="stamp">Annulé le {{ Format::date($transfer->cancelled_at) }}</div>
    @endif

    <dl class="meta">
        <div><dt>Départ</dt><dd>{{ $transfer->fromWarehouse->name }}</dd></div>
        <div><dt>Arrivée</dt><dd>{{ $transfer->toWarehouse->name }}</dd></div>
        <div><dt>Établi par</dt><dd>{{ $transfer->user->name }}</dd></div>
        @if ($transfer->notes)<div><dt>Note</dt><dd>{{ $transfer->notes }}</dd></div>@endif
    </dl>

    <table class="lines">
        <thead><tr><th>Référence</th><th>Désignation</th><th class="num">Quantité</th><th class="num">Reçu</th></tr></thead>
        <tbody>
            @foreach ($transfer->items as $item)
                <tr>
                    <td class="code">{{ $item->product->sku }}</td>
                    <td>{{ $item->product->name }}</td>
                    <td class="num">{{ Format::number($item->quantity) }}</td>
                    <td class="num"><span class="count-box"></span></td>
                </tr>
            @endforeach
        </tbody>
        <tfoot><tr><td colspan="2">Total</td><td class="num">{{ Format::number($transfer->items->sum('quantity')) }}</td><td></td></tr></tfoot>
    </table>

    <div class="signatures">
        <div>Remis par (départ)</div>
        <div>Reçu par (arrivée)</div>
    </div>
@endsection
