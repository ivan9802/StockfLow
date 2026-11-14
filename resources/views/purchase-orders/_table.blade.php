<div class="table-card">
    @if ($orders->isEmpty())
        <x-empty-state icon="cart-plus" title="Aucune commande ne correspond à ces critères." />
    @else
        <div class="table-responsive">
            <table class="table table-hover data-table">
                <caption class="visually-hidden">Commandes fournisseurs</caption>
                <thead>
                    <tr>
                        <th scope="col">Commande</th>
                        <th scope="col">Fournisseur</th>
                        <th scope="col" class="col-p3">Livraison à</th>
                        <th scope="col" class="col-p2">Prévue le</th>
                        <th scope="col" class="num col-p2">Montant</th>
                        <th scope="col" class="col-p3">Reçu</th>
                        <th scope="col">Statut</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($orders as $order)
                        @php $late = $order->expected_at && $order->expected_at->isPast() && $order->status->canReceive(); @endphp
                        <tr>
                            <td><a class="row-title code" href="{{ route('purchase-orders.show', $order) }}">{{ $order->reference }}</a></td>
                            <td>{{ $order->supplier->name }}</td>
                            <td class="col-p3">{{ $order->warehouse->name }}</td>
                            <td class="col-p2 text-nowrap">
                                {{ Format::date($order->expected_at) }}
                                @if ($late)<span class="sf-badge is-danger ms-1">En retard</span>@endif
                            </td>
                            <td class="num col-p2">{{ Format::money($order->totalAmount()) }}</td>
                            <td class="col-p3" style="min-width: 7rem">
                                <progress max="100" value="{{ $order->receivedPercent() }}" class="w-100" aria-label="Reçu à {{ $order->receivedPercent() }} %"></progress>
                                <span class="row-sub tabular">{{ $order->receivedPercent() }} %</span>
                            </td>
                            <td><x-status-badge :status="$order->status" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <x-table-foot :paginator="$orders" noun="commande" />
    @endif
</div>
