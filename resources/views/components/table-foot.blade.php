@props(['paginator', 'noun' => 'ligne'])
{{-- Pied de tableau : nombre de résultats et pagination. --}}
<div class="table-foot">
    <span>
        @if ($paginator->total() > 0)
            {{ $paginator->firstItem() }} à {{ $paginator->lastItem() }} sur {{ Format::number($paginator->total()) }} {{ $noun }}{{ $paginator->total() > 1 ? 's' : '' }}
        @endif
    </span>
    {{ $paginator->onEachSide(1)->links() }}
</div>
