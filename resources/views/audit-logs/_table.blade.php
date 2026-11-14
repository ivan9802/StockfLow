<div class="table-card">
    @if ($logs->isEmpty())
        <x-empty-state icon="journal-text" title="Aucune entrée ne correspond à ces critères." />
    @else
        <div class="table-responsive">
            <table class="table data-table">
                <caption class="visually-hidden">Journal d'audit</caption>
                <thead>
                    <tr>
                        <th scope="col">Date</th>
                        <th scope="col">Utilisateur</th>
                        <th scope="col">Action</th>
                        <th scope="col">Description</th>
                        <th scope="col" class="col-p2">Détail</th>
                        <th scope="col" class="col-p3">Adresse IP</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($logs as $log)
                        <tr>
                            <td class="text-nowrap">{{ Format::dateTime($log->created_at) }}</td>
                            <td>{{ $log->user?->name ?? 'Inconnu' }}</td>
                            <td><span @class(['sf-badge', 'is-danger' => in_array($log->action->value, ['deleted', 'cancelled', 'login_failed']), 'is-neutral' => ! in_array($log->action->value, ['deleted', 'cancelled', 'login_failed'])])>{{ $log->action->label() }}</span></td>
                            <td>{{ $log->description }}</td>
                            <td class="col-p2 small">
                                @if ($log->old_values || $log->new_values)
                                    <details>
                                        <summary>Valeurs</summary>
                                        <dl class="mb-0">
                                            @foreach (array_unique(array_merge(array_keys($log->old_values ?? []), array_keys($log->new_values ?? []))) as $field)
                                                <dt class="fw-normal text-secondary">{{ $field }}</dt>
                                                <dd class="mb-1">
                                                    @if (array_key_exists($field, $log->old_values ?? []))<s>{{ json_encode($log->old_values[$field], JSON_UNESCAPED_UNICODE) }}</s>@endif
                                                    @if (array_key_exists($field, $log->new_values ?? [])) {{ json_encode($log->new_values[$field], JSON_UNESCAPED_UNICODE) }}@endif
                                                </dd>
                                            @endforeach
                                        </dl>
                                    </details>
                                @endif
                            </td>
                            <td class="col-p3"><span class="code">{{ $log->ip_address }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <x-table-foot :paginator="$logs" noun="entrée" />
    @endif
</div>
