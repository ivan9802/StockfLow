@extends('layouts.print')

@section('title', 'Feuille de comptage '.$session->reference)
@section('back', route('inventories.show', $session))
@section('doc-title', 'Feuille de comptage')
@section('doc-ref', $session->reference)
@section('doc-date', 'Ouvert le '.Format::date($session->started_at))

@section('content')
    <dl class="meta">
        <div><dt>Emplacement</dt><dd>{{ $session->warehouse->name }}</dd></div>
        <div><dt>Périmètre</dt><dd>{{ $session->category?->name ?? 'Tous les produits' }}</dd></div>
    </dl>

    {{-- Pas de quantité théorique : le compteur note ce qu'il voit, sans être influencé. --}}
    <table class="lines">
        <thead><tr><th>Référence</th><th>Code-barres</th><th>Désignation</th><th class="num">Quantité comptée</th></tr></thead>
        <tbody>
            @foreach ($items as $item)
                <tr>
                    <td class="code">{{ $item->product->sku }}</td>
                    <td class="code">{{ $item->product->barcode }}</td>
                    <td>{{ $item->product->name }}</td>
                    <td class="num"><span class="count-box"></span></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="signatures">
        <div>Compté par (nom, date)</div>
        <div>Contrôlé par</div>
    </div>
@endsection
