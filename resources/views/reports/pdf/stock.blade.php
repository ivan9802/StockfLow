@extends('reports.pdf.layout')

@section('title', 'État des stocks')

@section('content')
    <table class="lines">
        <thead>
            <tr>
                <th>Référence</th>
                <th>Produit</th>
                <th>Catégorie</th>
                @foreach ($warehouses as $warehouse)<th class="num">{{ $warehouse->name }}</th>@endforeach
                <th class="num">Total</th>
                <th class="num">Seuil</th>
                <th>État</th>
                <th class="num">Prix d'achat</th>
                <th class="num">Valeur</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($products as $product)
                @php
                    $levels = $product->stockLevels->keyBy('warehouse_id');
                    $total = $product->totalStock();
                    $status = $product->stockStatus();
                @endphp
                <tr>
                    <td class="code">{{ $product->sku }}</td>
                    <td>{{ $product->name }}@unless ($product->is_active) <span class="muted">(inactif)</span>@endunless</td>
                    <td>{{ $product->category->name }}</td>
                    @foreach ($warehouses as $warehouse)<td class="num">{{ Format::number($levels[$warehouse->id]->quantity ?? 0) }}</td>@endforeach
                    <td class="num"><strong>{{ Format::number($total) }}</strong></td>
                    <td class="num">{{ Format::number($product->alert_threshold) }}</td>
                    <td class="{{ $status }}">{{ ['out' => 'Rupture', 'low' => 'Sous seuil', 'ok' => 'En stock'][$status] }}</td>
                    <td class="num">{{ Format::number($product->purchase_price) }}</td>
                    <td class="num">{{ Format::number($total * $product->purchase_price) }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="{{ 3 + $warehouses->count() + 4 }}">Valeur totale du stock ({{ $products->count() }} produits)</td>
                <td class="num">{{ Format::money($totalValue) }}</td>
            </tr>
        </tbody>
    </table>
    <p class="muted">Montants en F CFA. Valeur calculée au prix d'achat courant de chaque fiche produit.</p>
@endsection
