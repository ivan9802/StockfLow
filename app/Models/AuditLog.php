<?php

namespace App\Models;

use App\Enums\AuditAction;
use App\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Ligne du journal d'audit, en ajout seul. Créée uniquement par AuditService. */
class AuditLog extends Model
{
    use AppendOnly;

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'action', 'auditable_type', 'auditable_id', 'description',
        'old_values', 'new_values', 'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'action' => AuditAction::class,
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
