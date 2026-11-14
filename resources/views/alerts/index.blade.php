@extends('layouts.app')

@section('title', 'Alertes de stock')

@section('content')
    <x-page-header title="Alertes de stock">
        @if ($unread > 0)
            <x-slot:actions>
                <form method="POST" action="{{ route('alerts.read') }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-secondary"><i class="bi bi-check2-all" aria-hidden="true"></i> Tout marquer comme lu</button>
                </form>
            </x-slot:actions>
        @endif
    </x-page-header>

    <div class="row g-4">
        <div class="col-xl-7">
            <section class="table-card">
                <div class="card-header px-3 py-2"><h2>Produits à réapprovisionner</h2><span class="text-secondary small">{{ $products->count() }} produit(s)</span></div>
                @if ($products->isEmpty())
                    <x-empty-state icon="check-circle" title="Aucun produit sous son seuil d'alerte." />
                @else
                    <div class="table-responsive">
                        <table class="table data-table">
                            <caption class="visually-hidden">Produits dont le stock total est au plus le seuil</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Produit</th>
                                    <th scope="col" class="num">Stock</th>
                                    <th scope="col" class="num col-p2">Seuil</th>
                                    <th scope="col" class="col-p3">Fournisseur</th>
                                    <th scope="col">État</th>
                                    @can('purchases.manage')<th scope="col" class="actions"><span class="visually-hidden">Action</span></th>@endcan
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($products as $product)
                                    <tr>
                                        <td>
                                            <a class="row-title" href="{{ route('products.show', $product) }}">{{ $product->name }}</a>
                                            <span class="row-sub"><span class="code">{{ $product->sku }}</span> ·
                                                @foreach ($product->stockLevels->sortBy('warehouse_id') as $level){{ $loop->first ? '' : ', ' }}{{ $level->quantity }}@endforeach
                                                par emplacement</span>
                                        </td>
                                        <td class="num fw-semibold">{{ Format::number($product->totalStock()) }}</td>
                                        <td class="num col-p2">{{ Format::number($product->alert_threshold) }}</td>
                                        <td class="col-p3">
                                            {{ $product->supplier?->name ?? 'Aucun' }}
                                            @if ($product->supplier)<span class="row-sub">Délai moyen {{ $product->supplier->lead_time_days }} j</span>@endif
                                        </td>
                                        <td><x-stock-badge :quantity="$product->totalStock()" :threshold="$product->alert_threshold" /></td>
                                        @can('purchases.manage')
                                            <td class="actions"><a class="btn btn-sm btn-outline-secondary" href="{{ route('purchase-orders.create') }}">Commander</a></td>
                                        @endcan
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>

        <div class="col-xl-5">
            <section class="card">
                <div class="card-header"><h2>Notifications</h2></div>
                <ul class="list-group list-group-flush">
                    @forelse ($notifications as $notification)
                        <li class="list-group-item d-flex gap-2 @if (! $notification->read_at) fw-semibold @endif">
                            <i class="bi bi-{{ ($notification->data['quantity'] ?? 1) === 0 ? 'x-octagon text-danger' : 'exclamation-triangle text-warning' }}" aria-hidden="true"></i>
                            <div>
                                <a href="{{ route('products.show', $notification->data['product_id']) }}" class="text-reset">{{ $notification->data['message'] }}</a>
                                <div class="small text-secondary fw-normal">{{ Format::dateTime($notification->created_at) }}@if (! $notification->read_at) · non lue @endif</div>
                            </div>
                        </li>
                    @empty
                        <li class="list-group-item"><x-empty-state icon="bell" title="Aucune notification." /></li>
                    @endforelse
                </ul>
            </section>
        </div>
    </div>
@endsection
