<?php

namespace App\Models\Concerns;

use App\Exceptions\ImmutableRecordException;

/**
 * Interdit la modification et la suppression d'un enregistrement d'historique.
 * Une erreur se corrige par une nouvelle ligne, jamais par une retouche.
 */
trait AppendOnly
{
    public static function bootAppendOnly(): void
    {
        static::updating(function ($model) {
            throw new ImmutableRecordException(class_basename($model).' ne peut pas être modifié.');
        });

        static::deleting(function ($model) {
            throw new ImmutableRecordException(class_basename($model).' ne peut pas être supprimé.');
        });
    }
}
