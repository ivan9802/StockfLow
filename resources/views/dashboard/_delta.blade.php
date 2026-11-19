{{--
    Évolution d'un indicateur par rapport à la période précédente.
    La couleur n'est jamais le seul signal : flèche, signe et phrase complète pour les lecteurs d'écran.
--}}
@php
    $previous = $kpi['previous'];
    $format = fn ($v) => $money ? Format::money($v) : Format::number($v);
@endphp
<span class="dash-delta is-{{ $kpi['tone'] }}" data-delta>
    @if ($previous === null)
        <span class="text-secondary">Sans comparaison</span>
    @else
        <span class="dash-delta-badge">
            <i class="bi bi-{{ ['up' => 'arrow-up-right', 'down' => 'arrow-down-right', 'flat' => 'arrow-right'][$kpi['trend']] }}" aria-hidden="true"></i>
            {{ $kpi['change'] === null ? ($kpi['value'] > 0 ? 'nouveau' : '0 %') : Format::percent($kpi['change']) }}
        </span>
        <span class="dash-delta-ref">contre {{ $format($previous) }}</span>
        <span class="visually-hidden">
            {{ ['up' => 'En hausse', 'down' => 'En baisse', 'flat' => 'Stable'][$kpi['trend']] }} par rapport à la période précédente,
            {{ ['good' => 'évolution favorable', 'bad' => 'évolution défavorable', 'neutral' => 'évolution neutre'][$kpi['tone']] }}.
        </span>
    @endif
</span>
