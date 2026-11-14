<?php

namespace App\Services;

use App\Enums\DocumentType;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Attribue les références de documents (BS-2026-000128) et les numéros de SKU.
 *
 * Le numéro vient d'une ligne de compteur incrémentée dans la transaction de
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
            // Création et incrément en une seule instruction atomique. Une lecture
            // « SELECT ... FOR UPDATE » suivie d'un INSERT poserait, quand la série
            // n'existe pas encore (première vente de l'année), des verrous d'intervalle
            // partagés : deux transactions s'attendraient alors l'une l'autre pour
            // insérer. Ce cas a été reproduit par les tests de concurrence.
            DB::statement(
                'INSERT INTO document_sequences (prefix, year, last_number) VALUES (?, ?, 1)
                 ON DUPLICATE KEY UPDATE last_number = last_number + 1',
                [$prefix, $year]
            );

            // La ligne reste verrouillée par cette transaction jusqu'à sa fin :
            // la valeur lue est bien celle qui vient d'être attribuée.
            return (int) DB::table('document_sequences')
                ->where('prefix', $prefix)
                ->where('year', $year)
                ->value('last_number');
        });
    }
}
