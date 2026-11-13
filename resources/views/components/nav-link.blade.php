@props(['route', 'icon', 'active' => null, 'count' => 0, 'countLabel' => ''])
@php
    // Une entrée dont la route n'existe pas n'est pas rendue.
    $exists = Route::has($route);
    $isActive = $exists && request()->routeIs($active ?? $route);
@endphp
@if ($exists)
    <a href="{{ route($route) }}" @class(['nav-link', 'active' => $isActive]) @if ($isActive) aria-current="page" @endif>
        <i class="bi bi-{{ $icon }}" aria-hidden="true"></i>
        <span>{{ $slot }}</span>
        @if ($count > 0)
            <span class="nav-count">{{ $count }}<span class="visually-hidden"> {{ $countLabel }}</span></span>
        @endif
    </a>
@endif
