@props(['title', 'breadcrumbs' => []])
{{-- breadcrumbs : tableau [libellé => url]. Le dernier niveau est la page courante et n'y figure pas. --}}
<div class="page-header">
    <div>
        @if ($breadcrumbs)
            <nav aria-label="Fil d'Ariane">
                <ol class="breadcrumb">
                    @foreach ($breadcrumbs as $label => $url)
                        <li class="breadcrumb-item"><a href="{{ $url }}">{{ $label }}</a></li>
                    @endforeach
                    <li class="breadcrumb-item active" aria-current="page">{{ $title }}</li>
                </ol>
            </nav>
        @endif
        <h1>{{ $title }}</h1>
        @isset($context)
            <div class="page-context">{{ $context }}</div>
        @endisset
    </div>
    @isset($actions)
        <div class="page-actions">{{ $actions }}</div>
    @endisset
</div>
