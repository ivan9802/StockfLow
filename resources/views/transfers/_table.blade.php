<div class="table-card">
    @if ($transfers->isEmpty())
        <x-empty-state icon="shuffle" title="Aucun transfert pour l'instant.">
            Un transfert déplace du stock d'un emplacement à l'autre, par exemple pour réassortir la boutique.
        </x-empty-state>
    @else
        <div class="table-responsive">
            <table class="table table-hover data-table">
                <caption class="visually-hidden">Transferts</caption>
                <thead>
                    <tr>
                        <th scope="col">Bon</th>
                        <th scope="col">Date</th>
                        <th scope="col">Trajet</th>
                        <th scope="col" class="num col-p2">Articles</th>
                        <th scope="col" class="col-p3">Par</th>
                        <th scope="col">Statut</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($transfers as $transfer)
                        <tr>
                            <td><a class="row-title code" href="{{ route('transfers.show', $transfer) }}">{{ $transfer->reference }}</a></td>
                            <td class="text-nowrap">{{ Format::dateTime($transfer->transferred_at) }}</td>
                            <td>{{ $transfer->fromWarehouse->name }} <i class="bi bi-arrow-right" aria-label="vers"></i> {{ $transfer->toWarehouse->name }}</td>
                            <td class="num col-p2">{{ Format::number($transfer->units) }} <span class="text-secondary">({{ $transfer->items_count }} réf.)</span></td>
                            <td class="col-p3">{{ $transfer->user->name }}</td>
                            <td><x-status-badge :status="$transfer->status" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <x-table-foot :paginator="$transfers" noun="transfert" />
    @endif
</div>
