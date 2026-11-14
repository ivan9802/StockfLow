@extends('layouts.app')

@section('title', 'Réception de '.$order->reference)

@section('content')
    <x-page-header :title="'Réceptionner la commande '.$order->reference"
        :breadcrumbs="['Commandes' => route('purchase-orders.index'), $order->reference => route('purchase-orders.show', $order)]">
        <x-slot:context>
            <span>{{ $order->supplier->name }} · livraison à {{ $order->warehouse->name }}</span>
        </x-slot:context>
    </x-page-header>

    <form method="POST" action="{{ route('purchase-orders.receive.store', $order) }}" data-once novalidate class="form-wide">
        @csrf

        <section class="card mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <x-form.input name="delivery_note_number" label="Numéro du bon de livraison" optional maxlength="60" autocomplete="off" />
                    </div>
                    <div class="col-md-6">
                        <x-form.input name="received_at" label="Date de réception" type="date" optional :value="today()->format('Y-m-d')" max="{{ today()->format('Y-m-d') }}" />
                    </div>
                </div>
            </div>
        </section>

        @error('quantities')<x-alert type="danger" :message="$message" />@enderror

        <section class="table-card mb-3">
            <table class="table data-table mb-0">
                <caption class="visually-hidden">Quantités reçues</caption>
                <thead>
                    <tr>
                        <th scope="col">Produit</th>
                        <th scope="col" class="num col-p2">Commandé</th>
                        <th scope="col" class="num col-p2">Déjà reçu</th>
                        <th scope="col" class="num">Attendu</th>
                        <th scope="col" class="num">Reçu aujourd'hui</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($order->items as $item)
                        <tr @class(['text-secondary' => $item->remaining() === 0])>
                            <td>
                                <span class="fw-semibold">{{ $item->product->name }}</span>
                                <span class="row-sub code">{{ $item->product->sku }}</span>
                            </td>
                            <td class="num col-p2">{{ Format::number($item->quantity_ordered) }}</td>
                            <td class="num col-p2">{{ Format::number($item->quantity_received) }}</td>
                            <td class="num fw-semibold">{{ Format::number($item->remaining()) }}</td>
                            <td class="num">
                                @if ($item->remaining() > 0)
                                    <label class="visually-hidden" for="qty-{{ $item->id }}">Quantité reçue de {{ $item->product->name }}</label>
                                    <input type="number" min="0" max="{{ $item->remaining() }}" step="1" inputmode="numeric" id="qty-{{ $item->id }}"
                                        class="form-control input-qty ms-auto @error('quantities.'.$item->id) is-invalid @enderror"
                                        name="quantities[{{ $item->id }}]" value="{{ old('quantities.'.$item->id, 0) }}">
                                    @error('quantities.'.$item->id)<div class="invalid-feedback">{{ $message }}</div>@enderror
                                @else
                                    <span class="sf-badge is-success">Complet</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        <p class="text-secondary small">
            Saisissez ce qui est réellement arrivé. Une livraison partielle laisse la commande ouverte pour la suite.
            Une quantité supérieure à l'attendu est refusée : un excédent se saisit en réception sans commande.
        </p>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">Enregistrer la réception</button>
            <a class="btn btn-outline-secondary" href="{{ route('purchase-orders.show', $order) }}">Annuler</a>
        </div>
    </form>
@endsection
