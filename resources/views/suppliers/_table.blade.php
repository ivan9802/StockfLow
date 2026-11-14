<div class="table-card">
    @if ($suppliers->isEmpty())
        <x-empty-state icon="truck" title="Aucun fournisseur ne correspond à ces critères." />
    @else
        <div class="table-responsive">
            <table class="table table-hover data-table">
                <caption class="visually-hidden">Liste des fournisseurs</caption>
                <thead>
                    <tr>
                        <th scope="col">Fournisseur</th>
                        <th scope="col" class="col-p2">Interlocuteur</th>
                        <th scope="col" class="col-p3">Téléphone</th>
                        <th scope="col" class="num col-p2">Délai moyen</th>
                        <th scope="col" class="num col-p3">Produits</th>
                        <th scope="col">État</th>
                        <th scope="col" class="actions"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($suppliers as $supplier)
                        <tr>
                            <td>
                                <a class="row-title" href="{{ route('suppliers.edit', $supplier) }}">{{ $supplier->name }}</a>
                                @if ($supplier->email)<span class="row-sub">{{ $supplier->email }}</span>@endif
                            </td>
                            <td class="col-p2">{{ $supplier->contact_name }}</td>
                            <td class="col-p3 text-nowrap">{{ $supplier->phone }}</td>
                            <td class="num col-p2">{{ $supplier->lead_time_days }} j</td>
                            <td class="num col-p3">{{ Format::number($supplier->products_count) }}</td>
                            <td><x-status-badge :active="$supplier->is_active" /></td>
                            <td class="actions">
                                <form method="POST" action="{{ route('suppliers.destroy', $supplier) }}" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger"
                                        data-confirm="Supprimer le fournisseur {{ $supplier->name }} ? Ses commandes et réceptions passées restent consultables."
                                        data-confirm-title="Supprimer le fournisseur" data-confirm-label="Supprimer">
                                        Supprimer<span class="visually-hidden"> {{ $supplier->name }}</span>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <x-table-foot :paginator="$suppliers" noun="fournisseur" />
    @endif
</div>
