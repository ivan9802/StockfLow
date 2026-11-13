@php
    // Le vendeur n'a que cinq entrées : les surtitres de groupe sont alors inutiles.
    $grouped = auth()->user()->managesStock();
@endphp
<aside class="app-sidebar" id="sidebar">
    <a class="sidebar-brand" href="{{ route('home') }}">Stock<span class="brand-mark">Flow</span></a>

    <nav class="sidebar-nav" aria-label="Navigation principale">
        <x-nav-link route="dashboard" icon="speedometer2">Tableau de bord</x-nav-link>

        @if ($grouped)<div class="nav-group">Catalogue</div>@endif
        <x-nav-link route="products.index" active="products.*" icon="box-seam">Produits</x-nav-link>
        @can('catalog.manage')
            <x-nav-link route="categories.index" active="categories.*" icon="tags">Catégories</x-nav-link>
        @endcan
        @can('suppliers.manage')
            <x-nav-link route="suppliers.index" active="suppliers.*" icon="truck">Fournisseurs</x-nav-link>
        @endcan

        @if ($grouped)<div class="nav-group">Stock</div>@endif
        <x-nav-link route="stock.index" active="stock.*" icon="boxes">État des stocks</x-nav-link>
        @can('movements.view')
            <x-nav-link route="movements.index" active="movements.*" icon="arrow-left-right">Mouvements</x-nav-link>
        @endcan
        @can('transfers.manage')
            <x-nav-link route="transfers.index" active="transfers.*" icon="shuffle">Transferts</x-nav-link>
        @endcan
        @can('inventories.manage')
            <x-nav-link route="inventories.index" active="inventories.*" icon="clipboard-check"
                :count="$navCounts['inventories'] ?? 0" count-label="à valider">Inventaires</x-nav-link>
        @endcan

        @can('purchases.manage')
            <div class="nav-group">Achats</div>
            <x-nav-link route="purchase-orders.index" active="purchase-orders.*" icon="cart-plus"
                :count="$navCounts['purchases'] ?? 0" count-label="en attente de réception">Commandes</x-nav-link>
            <x-nav-link route="goods-receipts.index" active="goods-receipts.*" icon="box-arrow-in-down">Réceptions</x-nav-link>
        @endcan

        @if ($grouped)<div class="nav-group">Ventes</div>@endif
        @can('sales.create')
            <x-nav-link route="sales.create" icon="cash-coin">Point de vente</x-nav-link>
        @endcan
        <x-nav-link route="sales.index" :active="['sales.index', 'sales.show']" icon="receipt">Historique des ventes</x-nav-link>

        @can('alerts.view')
            <div class="nav-group">Pilotage</div>
            <x-nav-link route="alerts.index" active="alerts.*" icon="bell"
                :count="$navCounts['alerts'] ?? 0" count-label="non lues">Alertes</x-nav-link>
            <x-nav-link route="reports.index" active="reports.*" icon="file-earmark-bar-graph">Rapports</x-nav-link>
        @endcan

        @can('users.manage')
            <div class="nav-group">Administration</div>
            <x-nav-link route="users.index" active="users.*" icon="people">Utilisateurs</x-nav-link>
            <x-nav-link route="warehouses.index" active="warehouses.*" icon="building">Emplacements</x-nav-link>
            <x-nav-link route="audit-logs.index" active="audit-logs.*" icon="journal-text">Journal d'audit</x-nav-link>
        @endcan
    </nav>

    @if (auth()->user()->warehouse)
        <div class="sidebar-foot">
            <i class="bi bi-geo-alt" aria-hidden="true"></i> {{ auth()->user()->warehouse->name }}
        </div>
    @endif
</aside>
