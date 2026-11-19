@extends('layouts.app')

@section('title', 'Tableau de bord')

@php
    $snap = $data['snapshot'];
    $kpis = $data['kpis'];
    $period = $data['period'];
@endphp

@section('content')
    <div class="dash-header">
        <div>
            <h1>Tableau de bord</h1>
            <p class="dash-subtitle mb-0">
                {{ ucfirst(now()->locale('fr')->isoFormat('dddd D MMMM YYYY')) }}
                · <span data-period-label>{{ $period['label'] }}</span>
                @if ($data['previous'])
                    <span class="text-secondary" data-previous-label>· comparé {{ lcfirst($data['previous']['label']) }}</span>
                @endif
            </p>
        </div>

        {{-- Sélection de la période : formulaire GET, actualisé par Fetch quand JavaScript est disponible. --}}
        <form method="GET" action="{{ route('dashboard') }}" class="period-picker" data-period-form
            data-endpoint="{{ route('internal.dashboard') }}" data-refresh="{{ $refreshSeconds }}" novalidate>
            <div class="period-field">
                <label class="form-label" for="period">Période</label>
                <select class="form-select" id="period" name="period" data-period-select>
                    @foreach ($presets as $value => $label)
                        <option value="{{ $value }}" @selected($period['preset'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="period-custom" data-period-custom @if ($period['preset'] !== 'custom') hidden @endif>
                <div class="period-field">
                    <label class="form-label" for="from">Du</label>
                    <input type="date" class="form-control" id="from" name="from" value="{{ $period['from'] }}" max="{{ today()->format('Y-m-d') }}">
                </div>
                <div class="period-field">
                    <label class="form-label" for="to">Au</label>
                    <input type="date" class="form-control" id="to" name="to" value="{{ $period['to'] }}" max="{{ today()->format('Y-m-d') }}">
                </div>
            </div>
            <div class="form-check period-compare">
                <input type="hidden" name="compare" value="0">
                <input class="form-check-input" type="checkbox" id="compare" name="compare" value="1" @checked($data['previous'] !== null)>
                <label class="form-check-label" for="compare">Comparer à la période précédente</label>
            </div>
            <div class="period-actions">
                <button type="submit" class="btn btn-primary" data-period-apply>Appliquer</button>
                <a class="btn btn-outline-secondary" href="{{ route('dashboard') }}" data-period-reset>Réinitialiser</a>
            </div>
            <div class="period-error" data-period-error role="alert" hidden></div>
        </form>
    </div>

    <div data-dashboard aria-live="polite">
        {{-- Données initiales des graphiques : lues par le JavaScript, jamais exécutées. --}}
        <script type="application/json" id="dashboard-data">@json($data)</script>

        {{-- État du stock : mesuré à l'instant, indépendant de la période. --}}
        <div class="section-title">
            <h2>État du stock <span class="badge-now">à l'instant</span></h2>
            <a href="{{ route('stock.index') }}">État des stocks</a>
        </div>
        <div class="kpi-grid mb-4">
            @if ($full)
                <x-dash-kpi key="stock_value" label="Valeur du stock" icon="cash-stack" :value="$snap['stock_value']" money hint="Au prix d'achat" :href="route('stock.index')" />
            @endif
            <x-dash-kpi key="total_units" label="Unités en stock" icon="boxes" :value="$snap['total_units']" hint="Tous emplacements" :href="route('stock.index')" />
            <x-dash-kpi key="active_products" label="Références actives" icon="box-seam" :value="$snap['active_products']" :href="route('products.index', ['state' => 'active'])" />
            <x-dash-kpi key="low_count" label="Sous le seuil" icon="exclamation-triangle" :value="$snap['low_count']" hint="À réapprovisionner"
                :href="route('stock.index', ['stock' => 'low'])" :tone="$snap['low_count'] > 0 ? 'warning' : null" />
            <x-dash-kpi key="out_count" label="En rupture" icon="x-octagon" :value="$snap['out_count']" hint="Stock total à zéro"
                :href="route('stock.index', ['stock' => 'out'])" :tone="$snap['out_count'] > 0 ? 'danger' : null" />
            @if ($full)
                <x-dash-kpi key="pending_orders" label="Commandes en attente" icon="truck" :value="$snap['pending_orders']" hint="Passées, non soldées"
                    :href="route('purchase-orders.index', ['status' => 'pending'])" />
            @endif
        </div>

        {{-- Flux de la période, comparés à la période précédente. --}}
        <div class="section-title">
            <h2>Sur la période</h2>
            <a href="{{ route('sales.index', ['from' => $period['from'], 'to' => $period['to']]) }}" data-period-link="sales">{{ $full ? 'Historique des ventes' : 'Mes ventes' }}</a>
        </div>
        <div class="kpi-grid mb-4">
            <x-dash-kpi key="revenue" :label="$full ? 'Chiffre d\'affaires' : 'Mon chiffre d\'affaires'" icon="graph-up-arrow" :value="$kpis['revenue']['value']" money :kpi="$kpis['revenue']" />
            <x-dash-kpi key="sales_count" :label="$full ? 'Ventes' : 'Mes ventes'" icon="receipt" :value="$kpis['sales_count']['value']" :kpi="$kpis['sales_count']" />
            <x-dash-kpi key="average_basket" label="Panier moyen" icon="basket" :value="$kpis['average_basket']['value']" money :kpi="$kpis['average_basket']" />
            @if ($full)
                <x-dash-kpi key="entries_units" label="Unités entrées" icon="box-arrow-in-down" :value="$kpis['entries_units']['value']" :kpi="$kpis['entries_units']" />
                <x-dash-kpi key="exits_units" label="Unités sorties" icon="box-arrow-up" :value="$kpis['exits_units']['value']" :kpi="$kpis['exits_units']" />
                <x-dash-kpi key="transfers_count" label="Transferts" icon="shuffle" :value="$kpis['transfers_count']['value']" :kpi="$kpis['transfers_count']" />
                <x-dash-kpi key="adjustments_count" label="Ajustements" icon="sliders" :value="$kpis['adjustments_count']['value']" :kpi="$kpis['adjustments_count']" />
            @endif
        </div>

        <div class="row g-4 mb-4 dash-row">
            @if ($full)
                <div class="col-xl-6">
                    <x-chart-card id="chart-movements" title="Entrées et sorties de stock" subtitle="Unités, hors transferts entre emplacements">
                        <p class="visually-hidden">Total sur la période : {{ $kpis['entries_units']['value'] }} unités entrées, {{ $kpis['exits_units']['value'] }} unités sorties.</p>
                    </x-chart-card>
                </div>
            @endif
            <div @class(['col-xl-6' => $full, 'col-12' => ! $full])>
                <x-chart-card id="chart-sales" :title="$full ? 'Ventes' : 'Mes ventes'" subtitle="Chiffre d'affaires et nombre de ventes">
                    <p class="visually-hidden">Total sur la période : {{ Format::money($kpis['revenue']['value']) }} pour {{ $kpis['sales_count']['value'] }} ventes.</p>
                </x-chart-card>
            </div>
        </div>

        <div class="row g-4 mb-4 dash-row">
            <div class="col-xl-5">
                <x-chart-card id="chart-top" title="Produits les plus vendus" subtitle="Unités vendues sur la période, ventes annulées exclues">
                    <ol class="top-list" data-top-list>
                        @forelse ($data['top_products'] as $p)
                            <li><a href="{{ $p['url'] }}">{{ $p['name'] }}</a> <span class="tabular">{{ Format::number($p['units']) }} u. · {{ Format::money($p['revenue']) }}</span></li>
                        @empty
                            <li class="text-secondary">Aucune vente sur cette période.</li>
                        @endforelse
                    </ol>
                </x-chart-card>
            </div>
            @if ($full)
                <div class="col-md-6 col-xl-4">
                    <x-chart-card id="chart-warehouses" title="Stock par emplacement" subtitle="Unités, à l'instant">
                        <ul class="list-unstyled mb-0">
                            @foreach ($data['charts']['warehouses'] as $w)
                                <li>{{ $w['name'] }} : {{ Format::number($w['units']) }} unités ({{ $w['percent'] }} %), {{ Format::money($w['value']) }}</li>
                            @endforeach
                        </ul>
                    </x-chart-card>
                </div>
            @endif
            <div @class(['col-md-6 col-xl-3' => $full, 'col-xl-7' => ! $full])>
                <x-chart-card id="chart-status" title="Niveau de stock" subtitle="Produits actifs, à l'instant">
                    <ul class="list-unstyled mb-0">
                        @foreach ($data['charts']['stock_status'] as $s)
                            <li>{{ $s['label'] }} : {{ $s['count'] }} produit(s)</li>
                        @endforeach
                    </ul>
                </x-chart-card>
            </div>
        </div>
    </div>

    {{-- Activité récente : les dernières opérations, quelle que soit la période. --}}
    <div class="row g-4 dash-row">
        @can('alerts.view')
            <div class="col-xl-6">
                <section class="table-card h-100">
                    <div class="card-header"><h2>À réapprovisionner</h2><a href="{{ route('alerts.index') }}">Toutes les alertes</a></div>
                    @if ($toReorder->isEmpty())
                        <x-empty-state icon="check-circle" title="Aucun produit sous son seuil." />
                    @else
                        <table class="table data-table mb-0">
                            <caption class="visually-hidden">Produits sous le seuil d'alerte</caption>
                            <thead><tr><th scope="col">Produit</th><th scope="col" class="num">Stock</th><th scope="col" class="num col-p2">Seuil</th><th scope="col">État</th></tr></thead>
                            <tbody>
                                @foreach ($toReorder as $product)
                                    <tr>
                                        <td><a class="row-title" href="{{ route('products.show', $product) }}">{{ $product->name }}</a><span class="row-sub">{{ $product->supplier?->name }}</span></td>
                                        <td class="num fw-semibold">{{ Format::number($product->totalStock()) }}</td>
                                        <td class="num col-p2">{{ Format::number($product->alert_threshold) }}</td>
                                        <td><x-stock-badge :quantity="$product->totalStock()" :threshold="$product->alert_threshold" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </section>
            </div>
            <div class="col-xl-6">
                <section class="card h-100">
                    <div class="card-header"><h2>Alertes récentes</h2><a href="{{ route('alerts.index') }}">Centre d'alertes</a></div>
                    <ul class="list-group list-group-flush activity-list">
                        @forelse ($notifications as $n)
                            <li class="list-group-item">
                                <i class="bi bi-{{ ($n->data['quantity'] ?? 1) === 0 ? 'x-octagon text-danger' : 'exclamation-triangle text-warning' }}" aria-hidden="true"></i>
                                <div>
                                    <a href="{{ route('products.show', $n->data['product_id']) }}" @class(['text-reset', 'fw-semibold' => ! $n->read_at])>{{ $n->data['message'] }}</a>
                                    <div class="small text-secondary">{{ Format::dateTime($n->created_at) }}</div>
                                </div>
                            </li>
                        @empty
                            <li class="list-group-item"><x-empty-state icon="bell" title="Aucune alerte." /></li>
                        @endforelse
                    </ul>
                </section>
            </div>
        @endcan

        @if ($full)
            <div class="col-xl-7">
                <section class="table-card h-100">
                    <div class="card-header"><h2>Derniers mouvements</h2><a href="{{ route('movements.index') }}">Tout l'historique</a></div>
                    @if ($movements->isEmpty())
                        <x-empty-state icon="arrow-left-right" title="Aucun mouvement enregistré." />
                    @else
                        {{-- Version compacte : l'historique complet, avec stock avant et après, est dans « Mouvements ». --}}
                        <div class="table-responsive">
                            <table class="table data-table mb-0">
                                <caption class="visually-hidden">Derniers mouvements de stock</caption>
                                <thead><tr><th scope="col">Produit</th><th scope="col" class="col-p2">Type</th><th scope="col" class="num">Quantité</th></tr></thead>
                                <tbody>
                                    @foreach ($movements as $movement)
                                        <tr>
                                            <td>
                                                <a class="row-title" href="{{ route('products.show', $movement->product_id) }}">{{ $movement->product->name }}</a>
                                                <span class="row-sub">{{ Format::dateTime($movement->created_at) }} · {{ $movement->warehouse->name }} · {{ $movement->user->name }}</span>
                                            </td>
                                            <td class="col-p2"><x-movement-badge :movement="$movement" /></td>
                                            <td class="num fw-semibold">{{ Format::signed($movement->signedQuantity()) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            </div>
        @endif
        <div @class(['col-xl-5' => $full, 'col-12' => ! $full])>
            <section class="table-card h-100">
                <div class="card-header"><h2>{{ $full ? 'Dernières ventes' : 'Mes dernières ventes' }}</h2><a href="{{ route('sales.index') }}">Historique</a></div>
                @if ($sales->isEmpty())
                    <x-empty-state icon="receipt" title="Aucune vente pour l'instant." />
                @else
                    <div class="table-responsive">
                        <table class="table data-table mb-0">
                            <caption class="visually-hidden">Dernières ventes</caption>
                            <thead><tr><th scope="col">Bon de sortie</th><th scope="col" class="num">Montant</th><th scope="col">Statut</th></tr></thead>
                            <tbody>
                                @foreach ($sales as $sale)
                                    <tr>
                                        <td>
                                            <a class="row-title code" href="{{ route('sales.show', $sale) }}">{{ $sale->reference }}</a>
                                            <span class="row-sub">{{ Format::dateTime($sale->sold_at) }}@if ($full) · {{ $sale->user->name }}@endif</span>
                                        </td>
                                        <td class="num fw-semibold">{{ Format::money($sale->total_amount) }}</td>
                                        <td><x-status-badge :status="$sale->status" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>
    </div>
@endsection
