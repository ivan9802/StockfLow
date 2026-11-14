<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Contrôle de cohérence entre les niveaux de stock et l'historique des mouvements.
 *
 * Deux vérifications, en lecture seule :
 *  1. pour chaque couple produit et emplacement, la somme signée des mouvements
 *     égale la quantité de stock_levels ;
 *  2. chaque mouvement part du stock_after du mouvement précédent sur le même couple,
 *     et stock_after = stock_before ± quantité.
 *
 * Code de sortie 0 si tout concorde, 1 sinon : utilisable dans une tâche planifiée.
 */
class VerifyStockIntegrity extends Command
{
    protected $signature = 'stock:verify {--limit=20 : Nombre maximal d\'écarts affichés}';

    protected $description = 'Vérifie que l\'historique des mouvements redonne exactement les niveaux de stock';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        // 1. Somme des mouvements contre niveau de stock.
        $mismatches = DB::table('stock_levels as l')
            ->leftJoinSub(
                DB::table('stock_movements')
                    ->selectRaw("product_id, warehouse_id, SUM(CASE WHEN direction = 'in' THEN quantity ELSE -CAST(quantity AS SIGNED) END) AS replayed")
                    ->groupBy('product_id', 'warehouse_id'),
                'm', fn ($j) => $j->on('m.product_id', '=', 'l.product_id')->on('m.warehouse_id', '=', 'l.warehouse_id')
            )
            ->whereRaw('l.quantity <> COALESCE(m.replayed, 0)')
            ->select('l.product_id', 'l.warehouse_id', 'l.quantity', DB::raw('COALESCE(m.replayed, 0) AS replayed'))
            ->limit($limit + 1)
            ->get();

        // 2. Enchaînement des mouvements, couple par couple, dans l'ordre d'écriture.
        $breaks = [];
        $last = [];
        foreach (DB::table('stock_movements')->orderBy('id')->select('id', 'product_id', 'warehouse_id', 'direction', 'quantity', 'stock_before', 'stock_after')->lazyById(1000) as $m) {
            $key = $m->product_id.':'.$m->warehouse_id;
            $expectedAfter = $m->stock_before + ($m->direction === 'in' ? $m->quantity : -$m->quantity);

            if ($m->stock_after !== $expectedAfter || $m->stock_before !== ($last[$key] ?? 0)) {
                $breaks[] = [$m->id, $m->product_id, $m->warehouse_id, $last[$key] ?? 0, $m->stock_before, $m->stock_after];
                if (count($breaks) > $limit) {
                    break;
                }
            }
            $last[$key] = $m->stock_after;
        }

        $levels = DB::table('stock_levels')->count();
        $movements = DB::table('stock_movements')->count();

        if ($mismatches->isEmpty() && $breaks === []) {
            $this->info("Stock cohérent : $movements mouvements rejoués sur $levels lignes de stock, aucun écart.");

            return self::SUCCESS;
        }

        if ($mismatches->isNotEmpty()) {
            $this->error('Niveaux de stock différents de la somme des mouvements :');
            $this->table(['Produit', 'Emplacement', 'Stock enregistré', 'Somme des mouvements'],
                $mismatches->take($limit)->map(fn ($r) => [$r->product_id, $r->warehouse_id, $r->quantity, $r->replayed])->all());
        }

        if ($breaks !== []) {
            $this->error('Mouvements qui ne s\'enchaînent pas sur le précédent :');
            $this->table(['Mouvement', 'Produit', 'Emplacement', 'Après précédent', 'Avant', 'Après'], array_slice($breaks, 0, $limit));
        }

        return self::FAILURE;
    }
}
