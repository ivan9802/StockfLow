@extends('layouts.app')

@section('title', 'Inventaires')

@section('content')
    <x-page-header title="Inventaires physiques">
        <x-slot:actions>
            <a class="btn btn-primary" href="{{ route('inventories.create') }}"><i class="bi bi-clipboard-plus" aria-hidden="true"></i> Ouvrir un inventaire</a>
        </x-slot:actions>
    </x-page-header>

    <form method="GET" action="{{ route('inventories.index') }}" class="filter-bar">
        <div class="filter">
            <label class="form-label" for="status">Statut</label>
            <select class="form-select" id="status" name="status" data-autosubmit>
                <option value="">Tous</option>
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? null) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <noscript><button type="submit" class="btn btn-outline-secondary">Filtrer</button></noscript>
    </form>

    <div class="table-card">
        @if ($sessions->isEmpty())
            <x-empty-state icon="clipboard-check" title="Aucun inventaire pour l'instant.">
                Un inventaire compare le stock compté en rayon au stock enregistré, puis régularise les écarts après validation.
            </x-empty-state>
        @else
            <div class="table-responsive">
                <table class="table table-hover data-table">
                    <caption class="visually-hidden">Sessions d'inventaire</caption>
                    <thead>
                        <tr>
                            <th scope="col">Inventaire</th>
                            <th scope="col">Emplacement</th>
                            <th scope="col" class="col-p3">Périmètre</th>
                            <th scope="col" class="col-p2">Ouvert le</th>
                            <th scope="col" class="num">Comptées</th>
                            <th scope="col" class="num col-p2">Écarts</th>
                            <th scope="col">Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sessions as $session)
                            <tr>
                                <td><a class="row-title code" href="{{ route('inventories.show', $session) }}">{{ $session->reference }}</a></td>
                                <td>{{ $session->warehouse->name }}</td>
                                <td class="col-p3">{{ $session->category?->name ?? 'Tous les produits' }}</td>
                                <td class="col-p2 text-nowrap">{{ Format::date($session->started_at) }} par {{ $session->startedBy->name }}</td>
                                <td class="num">{{ $session->counted_count }} / {{ $session->items_count }}</td>
                                <td class="num col-p2">{{ $session->variance_count }}</td>
                                <td><x-status-badge :status="$session->status" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-table-foot :paginator="$sessions" noun="inventaire" />
        @endif
    </div>
@endsection
