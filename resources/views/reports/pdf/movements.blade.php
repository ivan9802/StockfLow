@extends('reports.pdf.layout')

@section('title', 'Historique des mouvements')

@section('content')
    <p class="muted">
        Période : {{ isset($filters['from']) ? \Illuminate\Support\Carbon::parse($filters['from'])->format('d/m/Y') : 'origine' }}
        au {{ isset($filters['to']) ? \Illuminate\Support\Carbon::parse($filters['to'])->format('d/m/Y') : now()->format('d/m/Y') }}
        · {{ $movements->count() }} mouvement(s)
    </p>

    <table class="lines">
        <thead>
            <tr>
                <th>Date</th><th>Bon</th><th>Type</th><th>Produit</th><th>Emplacement</th>
                <th class="num">Qté</th><th class="num">Avant</th><th class="num">Après</th><th>Motif</th><th>Par</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($movements as $m)
                <tr>
                    <td>{{ $m->created_at->format('d/m/Y H:i') }}</td>
                    <td class="code">{{ $m->reference }}</td>
                    <td>{{ $m->type->label() }}</td>
                    <td><span class="code">{{ $m->product->sku }}</span> {{ $m->product->name }}</td>
                    <td>{{ $m->warehouse->name }}</td>
                    <td class="num">{{ Format::signed($m->signedQuantity()) }}</td>
                    <td class="num">{{ Format::number($m->stock_before) }}</td>
                    <td class="num">{{ Format::number($m->stock_after) }}</td>
                    <td>{{ $m->reason }}</td>
                    <td>{{ $m->user->name }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
