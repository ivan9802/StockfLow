@props(['label', 'value', 'key' => null, 'hint' => null, 'href' => null, 'tone' => null])
@php $tag = $href ? 'a' : 'div'; @endphp
<{{ $tag }} @if ($href) href="{{ $href }}" @endif @class(['kpi-card', 'is-'.$tone => $tone])>
    <span class="overline">{{ $label }}</span>
    <span class="kpi-value tabular" @if ($key) data-kpi="{{ $key }}" @endif>{{ $value }}</span>
    @if ($hint)<span class="kpi-hint">{{ $hint }}</span>@endif
</{{ $tag }}>
