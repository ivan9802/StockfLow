@props(['key', 'label', 'icon', 'value', 'money' => false, 'kpi' => null, 'hint' => null, 'href' => null, 'tone' => null])
{{--
    Carte d'indicateur du tableau de bord.
    $kpi (facultatif) : ['value', 'previous', 'change', 'trend', 'tone'] venant de DashboardService::compare().
    Les attributs data-* permettent au JavaScript de mettre à jour la valeur et l'évolution.
--}}
@php
    $tag = $href ? 'a' : 'div';
    $display = $money ? Format::money($value) : Format::number($value);
@endphp
<{{ $tag }} @if ($href) href="{{ $href }}" @endif @class(['kpi-card', 'dash-kpi', 'is-'.$tone => $tone])
    data-kpi-card="{{ $key }}" @if ($money) data-money @endif>
    <div class="dash-kpi-head">
        <span class="overline">{{ $label }}</span>
        <span class="dash-kpi-icon" aria-hidden="true"><i class="bi bi-{{ $icon }}"></i></span>
    </div>
    <span class="kpi-value tabular" data-kpi="{{ $key }}" data-raw="{{ $value }}">{{ $display }}</span>
    @if ($kpi !== null)
        @include('dashboard._delta', ['kpi' => $kpi, 'money' => $money])
    @elseif ($hint)
        <span class="kpi-hint">{{ $hint }}</span>
    @endif
</{{ $tag }}>
