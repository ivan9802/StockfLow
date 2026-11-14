<div class="table-card">
    @if ($movements->isEmpty())
        <x-empty-state icon="arrow-left-right" title="Aucun mouvement ne correspond à ces critères." />
    @else
        @include('movements._rows', ['movements' => $movements, 'showProduct' => ! $product])
        <x-table-foot :paginator="$movements" noun="mouvement" />
    @endif
</div>
