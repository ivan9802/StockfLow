<?php

namespace Tests\Concurrency;

use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Ventes simultanées par des processus PHP réellement séparés, contre MySQL.
 *
 * Un test ordinaire s'exécute dans une seule transaction et ne peut rien prouver
 * sur les verrous : ici, chaque vente part d'un processus distinct avec sa propre
 * connexion, et les données sont réellement validées en base (D-014).
 *
 * Nombre de tours : variable d'environnement CONCURRENCY_ROUNDS (5 par défaut).
 */
class ConcurrentSalesTest extends TestCase
{
    use DatabaseTruncation;

    private Warehouse $store;

    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        [, $this->store] = $this->createWarehouses();
        $this->seller = User::factory()->salesAgent($this->store)->create();
    }

    private function rounds(): int
    {
        return max(1, (int) (getenv('CONCURRENCY_ROUNDS') ?: 5));
    }

    /**
     * Lance un processus par panier, tous calés sur le même instant de départ.
     *
     * @param  array<int, array<int, array{product_id: int, quantity: int}>>  $carts
     * @return array<int, string> sortie de chaque processus
     */
    private function sellConcurrently(array $carts): array
    {
        // Laisse le temps à chaque processus de démarrer Laravel avant le top départ.
        $startAt = microtime(true) + 2.5 + count($carts) * 0.3;
        $processes = [];

        foreach ($carts as $lines) {
            $process = new Process(
                ['php', base_path('tests/Concurrency/workers/sell.php'), json_encode([
                    'warehouse_id' => $this->store->id,
                    'user_id' => $this->seller->id,
                    'lines' => $lines,
                    'start_at' => $startAt,
                ])],
                base_path(),
                [
                    'APP_ENV' => 'testing',
                    'DB_CONNECTION' => 'mysql',
                    'DB_HOST' => env('DB_HOST'),
                    'DB_DATABASE' => env('DB_DATABASE'),
                    'CACHE_STORE' => 'array',
                    'SESSION_DRIVER' => 'array',
                    'QUEUE_CONNECTION' => 'sync',
                ],
                timeout: 180,
            );
            $process->start();
            $processes[] = $process;
        }

        return array_map(function (Process $process) {
            $process->wait();

            return trim($process->getOutput()) ?: 'error '.trim($process->getErrorOutput());
        }, $processes);
    }

    private function assertNoWorkerError(array $outputs): void
    {
        foreach ($outputs as $output) {
            $this->assertMatchesRegularExpression('/^(ok BS-|refused$)/', $output, "Processus en erreur : $output");
        }
    }

    public function test_deux_ventes_simultanees_ne_consomment_pas_plus_que_le_stock(): void
    {
        for ($round = 1; $round <= $this->rounds(); $round++) {
            $product = Product::factory()->create();
            $this->stock($product, $this->store, 10, $this->seller);

            // Deux vendeurs vendent 6 unités du même produit au même instant : 12 > 10.
            $outputs = $this->sellConcurrently([
                [['product_id' => $product->id, 'quantity' => 6]],
                [['product_id' => $product->id, 'quantity' => 6]],
            ]);

            $this->assertNoWorkerError($outputs);
            $succeeded = count(array_filter($outputs, fn ($o) => str_starts_with($o, 'ok')));

            $this->assertSame(1, $succeeded, "Tour $round : une seule vente doit passer. Sorties : ".implode(' | ', $outputs));
            $this->assertSame(4, $this->stockOf($product, $this->store), "Tour $round : stock final attendu 4.");
            $this->assertSame(1, Sale::whereHas('items', fn ($q) => $q->where('product_id', $product->id))->count());
        }
    }

    public function test_huit_ventes_simultanees_sur_un_stock_de_dix(): void
    {
        for ($round = 1; $round <= $this->rounds(); $round++) {
            $product = Product::factory()->create();
            $this->stock($product, $this->store, 10, $this->seller);

            // Huit paniers de 2 unités : 16 demandées, 10 disponibles, donc 5 ventes exactement.
            $outputs = $this->sellConcurrently(array_fill(0, 8, [['product_id' => $product->id, 'quantity' => 2]]));

            $this->assertNoWorkerError($outputs);
            $succeeded = count(array_filter($outputs, fn ($o) => str_starts_with($o, 'ok')));

            $this->assertSame(5, $succeeded, "Tour $round. Sorties : ".implode(' | ', $outputs));
            $this->assertSame(0, $this->stockOf($product, $this->store));

            // Chaque mouvement enchaîne exactement sur le précédent : aucune lecture périmée.
            $movements = StockMovement::where('product_id', $product->id)->where('type', 'sale')->orderBy('id')->get();
            $this->assertCount(5, $movements);
            $expected = 10;
            foreach ($movements as $movement) {
                $this->assertSame($expected, $movement->stock_before);
                $this->assertSame($expected - 2, $movement->stock_after);
                $expected -= 2;
            }
        }
    }

    public function test_des_paniers_croises_ne_se_bloquent_pas_mutuellement(): void
    {
        for ($round = 1; $round <= $this->rounds(); $round++) {
            $a = Product::factory()->create();
            $b = Product::factory()->create();
            $this->stock($a, $this->store, 50, $this->seller);
            $this->stock($b, $this->store, 50, $this->seller);

            // Les paniers listent A et B dans des ordres opposés. Sans ordre de verrouillage
            // commun, deux transactions pourraient s'attendre l'une l'autre indéfiniment.
            $carts = [];
            for ($i = 0; $i < 6; $i++) {
                $carts[] = $i % 2 === 0
                    ? [['product_id' => $a->id, 'quantity' => 1], ['product_id' => $b->id, 'quantity' => 2]]
                    : [['product_id' => $b->id, 'quantity' => 2], ['product_id' => $a->id, 'quantity' => 1]];
            }

            $outputs = $this->sellConcurrently($carts);

            $this->assertNoWorkerError($outputs);
            $this->assertSame(6, count(array_filter($outputs, fn ($o) => str_starts_with($o, 'ok'))), implode(' | ', $outputs));
            $this->assertSame(44, $this->stockOf($a, $this->store));
            $this->assertSame(38, $this->stockOf($b, $this->store));
        }
    }
}
