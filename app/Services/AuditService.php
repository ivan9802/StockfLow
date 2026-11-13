<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Seul point d'écriture du journal d'audit.
 *
 * L'appel se fait dans la transaction de l'opération tracée (décision D-019) :
 * une opération validée ne peut pas exister sans sa ligne d'audit.
 */
final class AuditService
{
    /** Champs qui ne doivent jamais apparaître dans le journal. */
    private const SECRET_KEYS = ['password', 'remember_token', 'current_password', 'password_confirmation'];

    public function record(
        AuditAction $action,
        ?Model $subject = null,
        ?string $description = null,
        array $oldValues = [],
        array $newValues = [],
        ?int $userId = null,
    ): AuditLog {
        $request = app()->bound('request') ? request() : null;
        $fromHttp = $request !== null && ! app()->runningInConsole();

        return AuditLog::create([
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'description' => Str::limit($description ?? $this->defaultDescription($action, $subject), 250, '...'),
            'old_values' => $this->clean($oldValues) ?: null,
            'new_values' => $this->clean($newValues) ?: null,
            'ip_address' => $fromHttp ? $request->ip() : null,
            'user_agent' => $fromHttp ? Str::limit((string) $request->userAgent(), 250, '') : null,
        ]);
    }

    private function defaultDescription(AuditAction $action, ?Model $subject): string
    {
        if ($subject === null) {
            return $action->label();
        }

        $label = method_exists($subject, 'auditLabel')
            ? $subject->auditLabel()
            : class_basename($subject).' n° '.$subject->getKey();

        return $action->label().' : '.$label;
    }

    private function clean(array $values): array
    {
        return array_diff_key($values, array_flip(self::SECRET_KEYS));
    }
}
