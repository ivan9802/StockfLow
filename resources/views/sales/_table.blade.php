<div class="table-card">
    @if ($sales->isEmpty())
        <x-empty-state icon="receipt" title="Aucune vente ne correspond à ces critères." />
    @else
        <div class="table-responsive">
            <table class="table table-hover data-table">
                <caption class="visually-hidden">Ventes</caption>
                <thead>
                    <tr>
                        <th scope="col">Bon de sortie</th>
                        <th scope="col">Date</th>
                        <th scope="col" class="col-p3">Client</th>
                        @if ($showSeller)<th scope="col" class="col-p2">Vendeur</th>@endif
                        <th scope="col" class="num col-p3">Articles</th>
                        <th scope="col" class="num">Montant</th>
                        <th scope="col">Statut</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sales as $sale)
                        <tr>
                            <td><a class="row-title code" href="{{ route('sales.show', $sale) }}">{{ $sale->reference }}</a></td>
                            <td class="text-nowrap">{{ Format::dateTime($sale->sold_at) }}</td>
                            <td class="col-p3">{{ $sale->customer_name ?? 'Client de passage' }}</td>
                            @if ($showSeller)<td class="col-p2">{{ $sale->user->name }}</td>@endif
                            <td class="num col-p3">{{ Format::number($sale->units) }}</td>
                            <td class="num fw-semibold">{{ Format::money($sale->total_amount) }}</td>
                            <td><x-status-badge :status="$sale->status" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <x-table-foot :paginator="$sales" noun="vente" />
    @endif
</div>
