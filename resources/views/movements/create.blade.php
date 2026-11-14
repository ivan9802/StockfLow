@extends('layouts.app')

@section('title', 'Sortie ou ajustement')

@section('content')
    <x-page-header title="Sortie interne ou ajustement" :breadcrumbs="['Mouvements' => route('movements.index')]" />

    <form method="POST" action="{{ route('movements.store') }}" class="form-narrow card card-body" data-once novalidate data-single-product>
        @csrf

        <fieldset class="mb-3">
            <legend class="form-label">Type de mouvement</legend>
            @foreach (['internal_use' => ['Sortie interne', 'Usage du bureau, casse, don, échantillon. Le stock diminue.'],
                       'adjustment_out' => ['Ajustement à la baisse', 'Correction d\'une erreur de saisie : le stock réel est inférieur.'],
                       'adjustment_in' => ['Ajustement à la hausse', 'Correction d\'une erreur de saisie : le stock réel est supérieur.']] as $value => [$label, $hint])
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="kind" id="kind-{{ $value }}" value="{{ $value }}" @checked(old('kind', 'internal_use') === $value) required>
                    <label class="form-check-label" for="kind-{{ $value }}">{{ $label }}</label>
                    <div class="form-text mt-0">{{ $hint }}</div>
                </div>
            @endforeach
            @error('kind')<div class="text-danger small">{{ $message }}</div>@enderror
        </fieldset>

        <div class="mb-3">
            <span class="form-label d-block">Produit</span>
            <x-product-search id="movement-product" label="Rechercher le produit" />
            <input type="hidden" name="product_id" value="{{ old('product_id', $product?->id) }}" data-picked-id>
            <div class="mt-2 fw-semibold" data-picked-label aria-live="polite">
                @if ($product)
                    {{ $product->name }} <span class="code text-secondary">{{ $product->sku }}</span>
                @endif
            </div>
            @error('product_id')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>

        <x-form.select name="warehouse_id" label="Emplacement" :options="$warehouses" placeholder="Choisir l'emplacement" />
        <x-form.input name="quantity" label="Quantité" type="number" min="1" step="1" inputmode="numeric" class="input-qty tabular" />
        <x-form.textarea name="reason" label="Motif" maxlength="500"
            help="Obligatoire. Il est conservé dans l'historique et le journal d'audit. Exemple : « Toner défectueux renvoyé au fournisseur »." />

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">Enregistrer le mouvement</button>
            <a class="btn btn-outline-secondary" href="{{ route('movements.index') }}">Annuler</a>
        </div>
    </form>
@endsection
