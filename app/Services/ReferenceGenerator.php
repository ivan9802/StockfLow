<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Models\DocumentSequence;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Attribue les références de documents (BS-2026-000128) et les numéros de SKU.
 *
 * Le numéro vient d'une ligne de compteur verrouillée dans la transaction de
 * l'opération (décision D-017). Deux opérations simultanées du même type
 * attendent donc l'une derrière l'autre et ne reçoivent jamais le même numéro.
 * Si la transaction échoue, l'incrément est annulé avec elle : pas de trou.
 *
 * Ordre de verrouillage : le compteur est toujours pris avant les lignes de stock.
 */
final class ReferenceGenerator
{
    public function next(DocumentType $type, ?CarbonInterface $date = null): string
    {
        $year = (int) ($date ?? now())->format('Y');

        return sprintf('%s-%d-%06d', $type->value, $year, $this->nextNumber($type->value, $year));
    }

    /** Numéro suivant d'une série. L'année 0 désigne une série sans remise à zéro. */
    public function nextNumber(string $prefix, int $year = 0): int
    {
        return DB::transaction(function () use ($prefix, $year) {
            $sequence = $this->lockedSequence($prefix, $year);

            if ($sequence === null) {
                // Première utilisation de la série. insertOrIgnore absorbe le cas
                // où une autre transaction vient de créer la même ligne.
                DocumentSequence::query()->insertOrIgnore([
                    'prefix' => $prefix, 'year' => $year, 'last_number' => 0,
                ]);
                $sequence = $this->lockedSequence($prefix, $year);
            }

            $sequence->last_number++;
            $sequence->save();

            return $sequence->last_number;
        });
    }

    private function lockedSequence(string $prefix, int $year): ?DocumentSequence
    {
        return DocumentSequence::query()
            ->where('prefix', $prefix)
            ->where('year', $year)
            ->lockForUpdate()
            ->first();
    }
}
