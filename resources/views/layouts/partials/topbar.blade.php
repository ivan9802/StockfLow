@php
    $user = auth()->user();
    $initials = collect(preg_split('/\s+/', trim($user->name)))->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
@endphp
<header class="app-topbar">
    <button type="button" class="btn-icon sidebar-toggle" data-sidebar-toggle aria-controls="sidebar" aria-expanded="false" aria-label="Ouvrir le menu">
        <i class="bi bi-list" aria-hidden="true"></i>
    </button>

    @if (Route::has('internal.products.search'))
        <div class="topbar-search" role="search">
            <x-product-search variant="global" />
        </div>
    @endif

    <div class="topbar-actions">
        @can('alerts.view')
            @if (Route::has('alerts.index'))
                <a class="btn-icon d-inline-flex align-items-center justify-content-center" href="{{ route('alerts.index') }}"
                   aria-label="Alertes de stock{{ ($navCounts['alerts'] ?? 0) > 0 ? ' : '.$navCounts['alerts'].' non lues' : '' }}">
                    <i class="bi bi-bell" aria-hidden="true"></i>
                    @if (($navCounts['alerts'] ?? 0) > 0)
                        <span class="count" data-alert-count>{{ $navCounts['alerts'] > 99 ? '99+' : $navCounts['alerts'] }}</span>
                    @endif
                </a>
            @endif
        @endcan

        <div class="dropdown">
            <button type="button" class="user-menu-toggle" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Menu de {{ $user->name }}">
                <span class="initials" aria-hidden="true">{{ $initials }}</span>
                <span class="identity">
                    <span class="d-block fw-semibold">{{ $user->name }}</span>
                    <span class="role">{{ $user->role->label() }}</span>
                </span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li class="px-3 py-2 d-md-none">
                    <span class="d-block fw-semibold">{{ $user->name }}</span>
                    <span class="text-secondary small">{{ $user->role->label() }}</span>
                </li>
                <li><a class="dropdown-item" href="{{ route('password.edit') }}"><i class="bi bi-key" aria-hidden="true"></i> Changer mon mot de passe</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item"><i class="bi bi-box-arrow-right" aria-hidden="true"></i> Se déconnecter</button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>
