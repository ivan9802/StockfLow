@props(['id', 'title', 'subtitle' => null, 'tall' => false])
{{--
    Bloc de graphique. Le canvas est dessiné par resources/js/modules/dashboard.js.
    Le contenu du slot sert de repli : il reste lu par les lecteurs d'écran et s'affiche
    si le JavaScript ne charge pas.
--}}
<section {{ $attributes->class(['card', 'chart-card', 'h-100']) }} aria-labelledby="{{ $id }}-title">
    <div class="card-header">
        <div>
            <h2 id="{{ $id }}-title">{{ $title }}</h2>
            @if ($subtitle)<p class="chart-subtitle mb-0" data-chart-subtitle="{{ $id }}">{{ $subtitle }}</p>@endif
        </div>
    </div>
    <div class="card-body">
        <div @class(['chart-box', 'is-tall' => $tall])>
            <canvas id="{{ $id }}" role="img" aria-labelledby="{{ $id }}-title"></canvas>
            <div class="chart-empty" data-chart-empty="{{ $id }}" hidden>
                <i class="bi bi-bar-chart" aria-hidden="true"></i>
                <span>Aucune donnée sur cette période.</span>
            </div>
        </div>
        <div class="chart-fallback" data-chart-fallback="{{ $id }}">{{ $slot }}</div>
    </div>
</section>
