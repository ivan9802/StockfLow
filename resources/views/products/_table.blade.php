<div class="table-card">
    @if ($products->isEmpty())
        <x-empty-state icon="box-seam" title="Aucun produit ne correspond à ces critères.">
            Modifiez la recherche ou retirez un filtre.
        </x-empty-state>
    @else
        <div class="table-responsive">
            <table class="table table-hover data-table">
                <caption class="visually-hidden">Liste des produits</caption>
                <thead>
                    <tr>
                        <th scope="col"><x-sort-link column="name" :filters="$filters" default>Produit</x-sort-link></th>
                        <th scope="col" class="col-p2"><x-sort-link column="sku" :filters="$filters">Référence</x-sort-link></th>
                        <th scope="col" class="col-p3">Catégorie</th>
                        @can('prices.view-purchase')
                            <th scope="col" class="num col-p3">Prix d'achat</th>
                        @endcan
                        <th scope="col" class="num col-p2"><x-sort-link column="selling_price" :filters="$filters">Prix de vente</x-sort-link></th>
                        <th scope="col" class="num"><x-sort-link column="total_stock" :filters="$filters">Stock</x-sort-link></th>
                        <th scope="col">État</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($products as $product)
                        <tr>
                            <td>
                                <a class="row-title" href="{{ route('products.show', $product) }}">{{ $product->name }}</a>
                                <span class="row-sub d-md-none code">{{ $product->sku }}</span>
                            </td>
                            <td class="col-p2"><span class="code">{{ $product->sku }}</span></td>
                            <td class="col-p3">{{ $product->category->name }}</td>
                            @can('prices.view-purchase')
                                <td class="num col-p3">{{ Format::money($product->purchase_price) }}</td>
                            @endcan
                            <td class="num col-p2">{{ Format::money($product->selling_price) }}</td>
                            <td class="num fw-semibold">{{ Format::number($product->totalStock()) }}</td>
                            <td>
                                <x-stock-badge :quantity="$product->totalStock()" :threshold="$product->alert_threshold" />
                                @unless ($product->is_active)
                                    <x-status-badge :active="false" />
                                @endunless
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <x-table-foot :paginator="$products" noun="produit" />
    @endif
</div>
