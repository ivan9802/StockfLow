<?php

namespace App\Models\Concerns;

use App\Enums\AuditAction;
use App\Services\AuditService;

/**
 * Trace dans le journal d'audit la création, la modification, la suppression
 * et la restauration d'un modèle. L'écriture se fait dans la même transaction
 * que l'opération : si l'une échoue, l'autre aussi.
 */
trait Auditable
{
    /** Champs jamais écrits dans le journal. */
    protected static array $auditExcluded = ['password', 'remember_token', 'created_at', 'updated_at', 'last_login_at'];

    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            app(AuditService::class)->record(
                AuditAction::Created, $model, null, [], static::auditFilter($model->getAttributes())
            );
        });

        static::updated(function ($model) {
            $changes = static::auditFilter($model->getChanges());
            if ($changes === []) {
                return;
            }
            $old = array_intersect_key($model->getOriginal(), $changes);
            app(AuditService::class)->record(
                AuditAction::Updated, $model, null, static::auditNormalize($old), $changes
            );
        });

        static::deleted(function ($model) {
            app(AuditService::class)->record(
                AuditAction::Deleted, $model, null, static::auditFilter($model->getOriginal()), []
            );
        });

        if (method_exists(static::class, 'restored')) {
            static::restored(function ($model) {
                app(AuditService::class)->record(AuditAction::Restored, $model);
            });
        }
    }

    /** @return array<string, mixed> */
    protected static function auditFilter(array $attributes): array
    {
        return static::auditNormalize(array_diff_key($attributes, array_flip(static::$auditExcluded)));
    }

    /** Ramène les enums et les dates à des valeurs simples, lisibles dans le journal. */
    protected static function auditNormalize(array $attributes): array
    {
        return array_map(function ($value) {
            if ($value instanceof \BackedEnum) {
                return $value->value;
            }
            if ($value instanceof \DateTimeInterface) {
                return $value->format('Y-m-d H:i:s');
            }

            return $value;
        }, $attributes);
    }

    /** Libellé affiché dans le journal, par exemple « Produit IMP-000042 ». */
    public function auditLabel(): string
    {
        return class_basename($this).' n° '.$this->getKey();
    }
}
