@extends('layouts.app')

@php
    use App\Enums\InventoryStatus;
    $counting = $session->status === InventoryStatus::InProgress;
    $reviewing = $session->status === InventoryStatus::PendingValidation;
@endphp

@section('title', 'Inventaire '.$session->reference)

@section('content')
    <x-page-header :title="'Inventaire '.$session->reference" :breadcrumbs="['Inventaires' => route('inventories.index')]">
        <x-slot:context>
            <x-status-badge :status="$session->status" />
            <span>{{ $session->warehouse->name }} · {{ $session->category?->name ?? 'tous les produits' }} · ouvert le {{ Format::dateTime($session->started_at) }} par {{ $session->startedBy->name }}</span>
        </x-slot:context>
        <x-slot:actions>
            <a class="btn btn-outline-secondary" href="{{ route('inventories.print', $session) }}"><i class="bi bi-printer" aria-hidden="true"></i> Feuille de comptage</a>
            @if ($counting)
                <form method="POST" action="{{ route('inventories.submit', $session) }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-primary" data-confirm="Terminer le comptage et soumettre l'inventaire à la validation de l'administrateur ?"
                        data-confirm-title="Soumettre l'inventaire" data-confirm-label="Soumettre" data-confirm-tone="primary">
                        <i class="bi bi-send-check" aria-hidden="true"></i> Soumettre à validation
                    </button>
                </form>
            @endif
            @if ($reviewing)
                @can('inventories.validate')
                    <form method="POST" action="{{ route('inventories.validate', $session) }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-primary" data-confirm="Valider l'inventaire ? Les {{ $stats['variances'] }} écart(s) seront régularisés dans le stock. Cette opération ne s'annule pas."
                            data-confirm-title="Valider l'inventaire" data-confirm-label="Valider et régulariser" data-confirm-tone="primary">
                            <i class="bi bi-check2-all" aria-hidden="true"></i> Valider
                        </button>
                    </form>
                    <x-reason-action :action="route('inventories.send-back', $session)" label="Renvoyer en comptage" tone="secondary" icon="arrow-counterclockwise"
                        question="Le gestionnaire pourra recompter les lignes douteuses. Indiquez ce qu'il faut vérifier." />
                @endcan
            @endif
            @if ($counting || $reviewing)
                <x-reason-action :action="route('inventories.cancel', $session)" label="Annuler"
                    question="Annuler l'inventaire {{ $session->reference }} ? Aucun écart ne sera appliqué." title="Annuler l'inventaire" />
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($session->review_comment && $counting)
        <x-alert type="warning" title="Renvoyé en comptage par l'administrateur" :message="$session->review_comment" />
    @endif
    @if ($reviewing && ! auth()->user()->can('inventories.validate'))
        <x-alert type="info">Comptage terminé. L'inventaire attend la validation d'un administrateur.</x-alert>
    @endif
    @if ($session->status === InventoryStatus::Validated)
        <x-alert type="success" title="Inventaire validé">
            Le {{ Format::dateTime($session->validated_at) }} par {{ $session->validatedBy?->name }}. Les écarts ont été régularisés par des mouvements « Régularisation d'inventaire ».
        </x-alert>
    @endif
    @if ($session->status === InventoryStatus::Cancelled)
        <x-alert type="warning" title="Inventaire annulé" :message="$session->cancel_reason" />
    @endif

    <div class="row g-3 mb-3">
        <div class="col-sm-4"><div class="kpi-card"><span class="overline">Lignes comptées</span><span class="kpi-value tabular" data-counted>{{ $stats['counted'] }} / {{ $stats['total'] }}</span></div></div>
        <div class="col-sm-4"><div class="kpi-card @if ($stats['variances']) is-warning @endif"><span class="overline">Écarts</span><span class="kpi-value tabular">{{ $stats['variances'] }}</span></div></div>
        <div class="col-sm-4">
            <form method="GET" class="kpi-card" action="{{ route('inventories.show', $session) }}">
                <label class="overline" for="show">Afficher</label>
                <select class="form-select" id="show" name="show" data-autosubmit>
                    <option value="">Toutes les lignes</option>
                    <option value="uncounted" @selected($show === 'uncounted')>Lignes non comptées</option>
                    <option value="variance" @selected($show === 'variance')>Lignes en écart</option>
                </select>
            </form>
        </div>
    </div>

    <form method="POST" action="{{ route('inventories.counts', $session) }}" @if ($counting) data-inventory-count @endif novalidate>
        @csrf
        @method('PUT')

        <div class="table-card">
            <div class="table-responsive">
                <table class="table data-table mb-0">
                    <caption class="visually-hidden">Lignes de l'inventaire</caption>
                    <thead>
                        <tr>
                            <th scope="col">Produit</th>
                            <th scope="col" class="num">Compté</th>
                            <th scope="col" class="num">Théorique</th>
                            <th scope="col" class="num">Écart</th>
                            <th scope="col">Justification</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($items as $item)
                            @php
                                $moved = $reviewing && $item->isCounted() && ($current[$item->product_id] ?? 0) !== $item->theoretical_quantity;
                            @endphp
                            <tr data-item="{{ $item->id }}" @class(['table-warning' => $item->hasVariance()])>
                                <td>
                                    <span class="fw-semibold">{{ $item->product->name }}</span>
                                    <span class="row-sub"><span class="code">{{ $item->product->sku }}</span>
                                        @if ($item->counted_at) · compté {{ Format::dateTime($item->counted_at) }} par {{ $item->countedBy?->name }}@endif
                                    </span>
                                    @if ($moved)
                                        <span class="row-sub text-warning-emphasis"><i class="bi bi-info-circle" aria-hidden="true"></i> Stock modifié depuis le comptage ({{ $current[$item->product_id] ?? 0 }} aujourd'hui)</span>
                                    @endif
                                </td>
                                <td class="num">
                                    @if ($counting)
                                        <label class="visually-hidden" for="count-{{ $item->id }}">Quantité comptée de {{ $item->product->name }}</label>
                                        <input type="number" min="0" step="1" inputmode="numeric" class="form-control input-qty ms-auto" id="count-{{ $item->id }}"
                                            name="counts[{{ $item->id }}][counted_quantity]" value="{{ $item->counted_quantity }}" data-count>
                                    @else
                                        {{ $item->isCounted() ? Format::number($item->counted_quantity) : '' }}
                                    @endif
                                </td>
                                {{-- Le théorique n'apparaît qu'après la saisie : le comptage reste à l'aveugle. --}}
                                <td class="num" data-theoretical>{{ $item->isCounted() ? Format::number($item->theoretical_quantity) : '' }}</td>
                                <td class="num fw-semibold" data-variance>{{ $item->isCounted() ? Format::signed($item->variance) : '' }}</td>
                                <td style="min-width: 14rem">
                                    @if ($counting)
                                        <label class="visually-hidden" for="why-{{ $item->id }}">Justification de l'écart sur {{ $item->product->name }}</label>
                                        <input type="text" class="form-control" id="why-{{ $item->id }}" maxlength="500"
                                            name="counts[{{ $item->id }}][justification]" value="{{ $item->justification }}" data-why
                                            placeholder="{{ $item->hasVariance() ? 'Obligatoire pour un écart' : '' }}">
                                    @else
                                        {{ $item->justification }}
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><x-empty-state icon="clipboard-check" title="Aucune ligne pour ce filtre." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($counting)
            <div class="d-flex gap-2 align-items-center mt-3">
                <button type="submit" class="btn btn-primary">Enregistrer le comptage</button>
                <span class="small text-secondary" data-save-status aria-live="polite">Chaque ligne est aussi enregistrée dès que vous quittez le champ.</span>
            </div>
        @endif
    </form>
@endsection
