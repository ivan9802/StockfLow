@extends('layouts.app')

@php $editing = $product->exists; @endphp

@section('title', $editing ? 'Modifier '.$product->name : 'Nouveau produit')

@section('content')
    <x-page-header :title="$editing ? 'Modifier le produit' : 'Nouveau produit'"
        :breadcrumbs="$editing ? ['Produits' => route('products.index'), $product->name => route('products.show', $product)] : ['Produits' => route('products.index')]" />

    <form method="POST" action="{{ $editing ? route('products.update', $product) : route('products.store') }}"
        enctype="multipart/form-data" class="form-narrow" data-once novalidate>
        @csrf
        @if ($editing) @method('PUT') @endif

        <section class="card mb-4">
            <div class="card-header"><h2>Identification</h2></div>
            <div class="card-body">
                <x-form.input name="name" label="Libellé" :value="$product->name" maxlength="180" autocomplete="off" />
                <div class="row">
                    <div class="col-md-6">
                        <x-form.select name="category_id" label="Catégorie" :options="$categories" :value="$product->category_id" placeholder="Choisir une catégorie"
                            :help="$editing ? 'La référence '.$product->sku.' ne change pas si vous changez de catégorie.' : 'Elle fixe le préfixe de la référence, attribuée à l\'enregistrement.'" />
                    </div>
                    <div class="col-md-6">
                        <x-form.select name="supplier_id" label="Fournisseur principal" :options="$suppliers" :value="$product->supplier_id" placeholder="Aucun" optional />
                    </div>
                </div>
                <x-form.input name="barcode" label="Code-barres" :value="$product->barcode" optional inputmode="numeric" maxlength="13" autocomplete="off"
                    :help="$editing ? null : 'Saisissez le code du fabricant. Laissé vide, un code interne est généré.'" />
                <x-form.textarea name="description" label="Description" :value="$product->description" optional maxlength="5000" />
            </div>
        </section>

        <section class="card mb-4">
            <div class="card-header"><h2>Prix et seuils</h2></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <x-form.input name="purchase_price" label="Prix d'achat" :value="$product->purchase_price" suffix="F CFA" inputmode="numeric" class="text-end tabular" autocomplete="off" />
                    </div>
                    <div class="col-md-6">
                        <x-form.input name="selling_price" label="Prix de vente" :value="$product->selling_price" suffix="F CFA" inputmode="numeric" class="text-end tabular" autocomplete="off" />
                    </div>
                </div>
                <x-form.input name="alert_threshold" label="Seuil d'alerte" type="number" :value="$product->alert_threshold" min="0" inputmode="numeric" class="input-qty tabular"
                    help="Une alerte est émise quand le stock total, tous emplacements confondus, atteint ce seuil ou passe dessous." />

                @foreach ($warehouses as $warehouse)
                    @php $level = $editing ? $product->stockLevels->firstWhere('warehouse_id', $warehouse->id) : null; @endphp
                    <x-form.input name="reorder_points[{{ $warehouse->id }}]" :label="'Seuil de réassort : '.$warehouse->name" type="number" optional
                        :value="$level?->reorder_point" min="0" inputmode="numeric" class="input-qty tabular"
                        help="Seuil propre à cet emplacement. Laissez vide pour ne signaler que la rupture." />
                @endforeach
            </div>
        </section>

        <section class="card mb-4">
            <div class="card-header"><h2>Image et état</h2></div>
            <div class="card-body">
                @if ($editing && $product->image_path)
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <img class="product-thumb" src="{{ route('products.image', $product) }}" alt="" width="40" height="40">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="remove_image" name="remove_image" value="1">
                            <label class="form-check-label" for="remove_image">Retirer l'image actuelle</label>
                        </div>
                    </div>
                @endif
                <x-form.input name="image" label="Image" type="file" optional accept="image/jpeg,image/png,image/webp"
                    help="JPEG, PNG ou WebP, 2 Mo au plus." />

                <div class="form-check">
                    <input type="hidden" name="is_active" value="0">
                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $product->is_active))>
                    <label class="form-check-label" for="is_active">Produit actif</label>
                    <div class="form-text">Un produit inactif ne peut plus être vendu ni commandé. Son stock et son historique restent visibles.</div>
                </div>
            </div>
        </section>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">{{ $editing ? 'Enregistrer les modifications' : 'Créer le produit' }}</button>
            <a class="btn btn-outline-secondary" href="{{ $editing ? route('products.show', $product) : route('products.index') }}">Annuler</a>
        </div>
    </form>
@endsection
