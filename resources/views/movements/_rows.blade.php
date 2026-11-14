{{-- Tableau de mouvements de stock, repris par l'historique et par la fiche produit. --}}
@php $showProduct = $showProduct ?? true; @endphp
<div class="table-responsive">
    <table class="table table-hover data-table">
        <caption class="visually-hidden">Mouvements de stock</caption>
        <thead>
            <tr>
                <th scope="col">Date</th>
                <th scope="col">Type</th>
                @if ($showProduct)<th scope="col">Produit</th>@endif
                <th scope="col" class="col-p2">Emplacement</th>
                <th scope="col" class="num">Quantité</th>
                <th scope="col" class="num col-p2">Avant</th>
                <th scope="col" class="num">Après</th>
                <th scope="col" class="col-p3">Référence</th>
                <th scope="col" class="col-p3">Par</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($movements as $movement)
                <tr>
                    <td class="text-nowrap">{{ Format::dateTime($movement->created_at) }}</td>
                    <td>
                        <x-movement-badge :movement="$movement" />
                        @if ($movement->reason)
                            <span class="row-sub">{{ $movement->reason }}</span>
                        @endif
                    </td>
                    @if ($showProduct)
                        <td>
                            <a class="row-title" href="{{ route('products.show', $movement->product_id) }}">{{ $movement->product->name }}</a>
                            <span class="row-sub code">{{ $movement->product->sku }}</span>
                        </td>
                    @endif
                    <td class="col-p2">{{ $movement->warehouse->name }}</td>
                    <td class="num fw-semibold">{{ Format::signed($movement->signedQuantity()) }}</td>
                    <td class="num col-p2">{{ Format::number($movement->stock_before) }}</td>
                    <td class="num">{{ Format::number($movement->stock_after) }}</td>
                    <td class="col-p3"><span class="code">{{ $movement->reference }}</span></td>
                    <td class="col-p3">{{ $movement->user->name }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
