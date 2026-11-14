@extends('layouts.app')

@section('title', 'Rapports et exports')

@section('content')
    <x-page-header title="Rapports et exports" />

    <div class="row g-4">
        <div class="col-lg-6">
            <section class="card h-100">
                <div class="card-header"><h2>État des stocks</h2></div>
                <div class="card-body">
                    <p class="text-secondary">Quantité par emplacement, stock total, seuil, état et valeur au prix d'achat de chaque produit, à l'instant de l'export.</p>
                    <a class="btn btn-primary" href="{{ route('reports.stock', 'pdf') }}"><i class="bi bi-filetype-pdf" aria-hidden="true"></i> PDF</a>
                    <a class="btn btn-outline-secondary" href="{{ route('reports.stock', 'csv') }}"><i class="bi bi-filetype-csv" aria-hidden="true"></i> CSV</a>
                </div>
            </section>
        </div>

        <div class="col-lg-6">
            <section class="card h-100">
                <div class="card-header"><h2>Historique des mouvements</h2></div>
                <div class="card-body">
                    <form method="GET" action="{{ route('reports.movements', 'csv') }}" data-report-form>
                        <div class="row">
                            <div class="col-sm-6"><x-form.input name="from" label="Du" type="date" optional :value="today()->startOfMonth()->format('Y-m-d')" /></div>
                            <div class="col-sm-6"><x-form.input name="to" label="Au" type="date" optional :value="today()->format('Y-m-d')" /></div>
                        </div>
                        <x-form.select name="warehouse_id" label="Emplacement" :options="$warehouses" placeholder="Tous" optional />
                        <p class="form-text">Le PDF est limité à {{ Format::number($pdfMax) }} mouvements. Au-delà, utilisez le CSV.</p>
                        <button type="submit" class="btn btn-primary" formaction="{{ route('reports.movements', 'pdf') }}"><i class="bi bi-filetype-pdf" aria-hidden="true"></i> PDF</button>
                        <button type="submit" class="btn btn-outline-secondary"><i class="bi bi-filetype-csv" aria-hidden="true"></i> CSV</button>
                    </form>
                </div>
            </section>
        </div>

        <div class="col-lg-6">
            <section class="card h-100">
                <div class="card-header"><h2>Ventes</h2></div>
                <div class="card-body">
                    <form method="GET" action="{{ route('reports.sales') }}">
                        <div class="row">
                            <div class="col-sm-6"><x-form.input name="from" id="sales-from" label="Du" type="date" optional :value="today()->startOfMonth()->format('Y-m-d')" /></div>
                            <div class="col-sm-6"><x-form.input name="to" id="sales-to" label="Au" type="date" optional :value="today()->format('Y-m-d')" /></div>
                        </div>
                        <p class="form-text">Une ligne par article vendu, avec le prix du jour de la vente.</p>
                        <button type="submit" class="btn btn-outline-secondary"><i class="bi bi-filetype-csv" aria-hidden="true"></i> CSV</button>
                    </form>
                </div>
            </section>
        </div>

        <div class="col-lg-6">
            <section class="card h-100">
                <div class="card-header"><h2>Bons et documents</h2></div>
                <div class="card-body">
                    <p class="text-secondary mb-2">Les bons s'impriment depuis leur fiche :</p>
                    <ul class="mb-0">
                        <li><a href="{{ route('sales.index') }}">bon de sortie</a>, depuis une vente ;</li>
                        <li><a href="{{ route('goods-receipts.index') }}">bon de réception</a>, depuis une réception ;</li>
                        <li><a href="{{ route('transfers.index') }}">bon de transfert</a>, depuis un transfert ;</li>
                        <li><a href="{{ route('inventories.index') }}">feuille de comptage</a>, depuis un inventaire.</li>
                    </ul>
                </div>
            </section>
        </div>
    </div>

    <p class="text-secondary small mt-4">Chaque export est tracé dans le journal d'audit. Les fichiers CSV s'ouvrent directement dans Excel (séparateur point-virgule, accents conservés).</p>
@endsection
