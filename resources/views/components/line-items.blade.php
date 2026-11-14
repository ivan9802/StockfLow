@props(['lines' => [], 'withCost' => false, 'warehouseField' => null, 'label' => 'Produits'])
{{--
    Saisie de lignes de produits pour un document (transfert, commande, réception directe).
    Un produit se choisit par la recherche instantanée ; chaque ligne envoie lines[i][product_id],
    lines[i][quantity] et, pour les achats, lines[i][unit_cost].
    warehouseField : nom du champ d'emplacement dont le stock est affiché à côté de chaque ligne.
    Les lignes déjà saisies (édition, retour après erreur) sont rendues par le serveur.
--}}
@php
    $lines = old('lines', $lines);
    $productIds = collect($lines)->pluck('product_id')->filter()->all();
    $products = \App\Models\Product::withTrashed()->whereIn('id', $productIds)->get()->keyBy('id');
@endphp
<section class="card mb-4" data-line-items @if ($withCost) data-with-cost @endif
    @if ($warehouseField) data-warehouse-field="{{ $warehouseField }}" @endif>
    <div class="card-header"><h2>{{ $label }}</h2></div>
    <div class="card-body">
        <div class="mb-3" style="max-width: 480px">
            <x-product-search id="line-search" label="Ajouter un produit" placeholder="Rechercher un produit à ajouter" />
            <div class="form-text">Tapez au moins 2 caractères, puis Entrée ou un clic pour ajouter le produit.</div>
        </div>

        @error('lines')<x-alert type="danger" :message="$message" />@enderror

        <div class="table-responsive">
            <table class="table data-table mb-0">
                <thead>
                    <tr>
                        <th scope="col">Produit</th>
                        @if ($warehouseField)<th scope="col" class="num col-p2">Disponible</th>@endif
                        <th scope="col" class="num">Quantité</th>
                        @if ($withCost)<th scope="col" class="num">Prix d'achat unitaire</th>@endif
                        <th scope="col" class="actions"><span class="visually-hidden">Retirer</span></th>
                    </tr>
                </thead>
                <tbody data-line-rows>
                    @foreach ($lines as $i => $line)
                        @php $product = $products->get($line['product_id'] ?? 0); @endphp
                        @continue(! $product)
                        <tr data-line data-product-id="{{ $product->id }}">
                            <td>
                                <input type="hidden" name="lines[{{ $i }}][product_id]" value="{{ $product->id }}">
                                <span class="fw-semibold">{{ $product->name }}</span>
                                <span class="row-sub code">{{ $product->sku }}</span>
                            </td>
                            @if ($warehouseField)<td class="num col-p2" data-available>...</td>@endif
                            <td class="num">
                                <label class="visually-hidden" for="lines-{{ $i }}-quantity">Quantité de {{ $product->name }}</label>
                                <input type="number" min="1" step="1" inputmode="numeric" required id="lines-{{ $i }}-quantity"
                                    class="form-control input-qty ms-auto @error("lines.$i.quantity") is-invalid @enderror"
                                    name="lines[{{ $i }}][quantity]" value="{{ $line['quantity'] ?? 1 }}">
                                @error("lines.$i.quantity")<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </td>
                            @if ($withCost)
                                <td class="num">
                                    <label class="visually-hidden" for="lines-{{ $i }}-cost">Prix d'achat de {{ $product->name }}</label>
                                    <div class="input-group ms-auto" style="max-width: 12rem">
                                        <input type="number" min="0" step="1" inputmode="numeric" required id="lines-{{ $i }}-cost"
                                            class="form-control text-end tabular @error("lines.$i.unit_cost") is-invalid @enderror"
                                            name="lines[{{ $i }}][unit_cost]" value="{{ $line['unit_cost'] ?? $product->purchase_price }}">
                                        <span class="input-group-text">F CFA</span>
                                    </div>
                                </td>
                            @endif
                            <td class="actions">
                                <button type="button" class="btn btn-sm btn-outline-danger" data-line-remove aria-label="Retirer {{ $product->name }}"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="text-secondary small mt-2 mb-0" data-line-empty @if (count($lines)) hidden @endif>Aucun produit pour l'instant.</p>
    </div>
</section>
