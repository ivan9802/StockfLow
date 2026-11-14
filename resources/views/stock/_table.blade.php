<div class="table-card">
    @if ($products->isEmpty())
        <x-empty-state icon="boxes" title="Aucun produit ne correspond à ces critères." />
    @else
        <div class="table-responsive">
            <table class="table table-hover data-table">
                <caption class="visually-hidden">Stock par produit et par emplacement</caption>
                <thead>
                    <tr>
                        <th scope="col">Produit</th>
                        @foreach ($warehouses as $warehouse)
                            <th scope="col" class="num">{{ $warehouse->name }}</th>
                        @endforeach
                        <th scope="col" class="num">Total</th>
                        <th scope="col" class="num col-p3">Seuil</th>
                        @if ($showValue)<th scope="col" class="num col-p2">Valeur</th>@endif
                        <th scope="col">État</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($products as $product)
                        @php $levels = $product->stockLevels->keyBy('warehouse_id'); @endphp
                        <tr>
                            <td>
                                <a class="row-title" href="{{ route('products.show', $product) }}">{{ $product->name }}</a>
                                <span class="row-sub"><span class="code">{{ $product->sku }}</span> · {{ $product->category->name }}</span>
                            </td>
                            @foreach ($warehouses as $warehouse)
                                @php $level = $levels->get($warehouse->id); @endphp
                                <td class="num">
                                    {{ Format::number($level?->quantity ?? 0) }}
                                    @if ($level && $level->reorder_point !== null && $level->quantity <= $level->reorder_point)
                                        <i class="bi bi-exclamation-triangle text-warning" title="Sous le seuil de réassort ({{ $level->reorder_point }})" aria-label="Sous le seuil de réassort"></i>
                                    @endif
                                </td>
                            @endforeach
                            <td class="num fw-semibold">{{ Format::number($product->totalStock()) }}</td>
                            <td class="num col-p3">{{ Format::number($product->alert_threshold) }}</td>
                            @if ($showValue)<td class="num col-p2">{{ Format::money($product->totalStock() * $product->purchase_price) }}</td>@endif
                            <td><x-stock-badge :quantity="$product->totalStock()" :threshold="$product->alert_threshold" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <x-table-foot :paginator="$products" noun="produit" />
    @endif
</div>
