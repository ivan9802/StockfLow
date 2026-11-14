<div class="table-card">
    @if ($receipts->isEmpty())
        <x-empty-state icon="box-arrow-in-down" title="Aucune réception ne correspond à ces critères." />
    @else
        <div class="table-responsive">
            <table class="table table-hover data-table">
                <caption class="visually-hidden">Réceptions</caption>
                <thead>
                    <tr>
                        <th scope="col">Bon de réception</th>
                        <th scope="col">Date</th>
                        <th scope="col">Fournisseur</th>
                        <th scope="col" class="col-p2">Commande</th>
                        <th scope="col" class="col-p3">Emplacement</th>
                        <th scope="col" class="num col-p3">Articles</th>
                        <th scope="col" class="col-p3">Par</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($receipts as $receipt)
                        <tr>
                            <td><a class="row-title code" href="{{ route('goods-receipts.show', $receipt) }}">{{ $receipt->reference }}</a></td>
                            <td class="text-nowrap">{{ Format::dateTime($receipt->received_at) }}</td>
                            <td>{{ $receipt->supplier->name }}</td>
                            <td class="col-p2">
                                @if ($receipt->purchaseOrder)
                                    <a class="code" href="{{ route('purchase-orders.show', $receipt->purchase_order_id) }}">{{ $receipt->purchaseOrder->reference }}</a>
                                @else
                                    <span class="sf-badge is-neutral">Sans commande</span>
                                @endif
                            </td>
                            <td class="col-p3">{{ $receipt->warehouse->name }}</td>
                            <td class="num col-p3">{{ Format::number($receipt->units) }}</td>
                            <td class="col-p3">{{ $receipt->user->name }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <x-table-foot :paginator="$receipts" noun="réception" />
    @endif
</div>
