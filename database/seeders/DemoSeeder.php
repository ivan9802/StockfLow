<?php

namespace Database\Seeders;

use App\Enums\MovementType;
use App\Enums\Role;
use App\Exceptions\BusinessException;
use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Services\ProductService;
use App\Services\PurchaseService;
use App\Services\SalesService;
use App\Services\StockService;
use App\Services\TransferService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Jeu de démonstration : trois mois d'activité d'une PME d'Abidjan.
 *
 * Tout passe par les services métier, comme en exploitation : aucune quantité
 * n'est écrite directement, chaque variation de stock a son mouvement. La base de
 * démonstration respecte donc la règle « un mouvement par variation ».
 *
 * Usage : php artisan migrate:fresh --seed --seeder=DemoSeeder
 * Les comptes de démonstration sont documentés dans le README. Jamais en production.
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'demo-stockflow-2026';

    private const DAYS = 92;

    /** Vraie date du jour, mémorisée avant que la simulation ne déplace l'horloge. */
    private Carbon $today;

    private Carbon $start;

    private Warehouse $warehouse;

    private Warehouse $store;

    /** @var array<string, User> */
    private array $users = [];

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Le jeu de démonstration ne se charge jamais en production.');
        }

        // Tirages reproductibles : deux chargements donnent la même base.
        mt_srand(2026);
        $this->command?->info('Chargement du jeu de démonstration (quelques minutes)...');

        (new ReferenceDataSeeder)->seedWarehouses();
        $this->warehouse = Warehouse::where('code', 'ENT')->firstOrFail();
        $this->store = Warehouse::where('code', 'PDV')->firstOrFail();

        $this->today = today();
        $start = $this->start = $this->today->copy()->subDays(self::DAYS)->setTime(8, 0);
        Carbon::setTestNow($start);

        try {
            $this->seedUsers();
            $suppliers = $this->seedSuppliers();
            $products = $this->seedCatalog($suppliers);
            $this->seedInitialStock($products, $suppliers);
            $this->simulateActivity($products, $suppliers, $start);
            $this->shapeFinalState($products);
            $this->seedOpenDocuments($products, $suppliers);
        } finally {
            Carbon::setTestNow();
        }

        $this->command?->info(sprintf(
            'Démonstration prête : %d produits, %d mouvements, %d ventes.',
            Product::count(), StockMovement::count(), Sale::count()
        ));
    }

    // ------------------------------------------------------------ Comptes

    private function seedUsers(): void
    {
        $accounts = [
            'admin' => ['Mariam Ouattara', 'admin@stockflow.test', Role::Admin, null],
            'manager' => ['Awa Koné', 'awa.kone@stockflow.test', Role::StockManager, null],
            'manager2' => ['Yao Kouassi', 'yao.kouassi@stockflow.test', Role::StockManager, null],
            'agent' => ['Aminata Diallo', 'aminata.diallo@stockflow.test', Role::SalesAgent, $this->store->id],
            'agent2' => ['Serge Bamba', 'serge.bamba@stockflow.test', Role::SalesAgent, $this->store->id],
        ];

        foreach ($accounts as $key => [$name, $email, $role, $warehouseId]) {
            $this->users[$key] = User::create([
                'name' => $name, 'email' => $email, 'password' => self::PASSWORD, 'role' => $role,
                'warehouse_id' => $warehouseId, 'is_active' => true, 'must_change_password' => false,
            ]);
        }

        // Un ancien vendeur désactivé : son historique reste attaché à son nom.
        $this->users['former'] = User::create([
            'name' => 'Ibrahim Touré', 'email' => 'ibrahim.toure@stockflow.test', 'password' => self::PASSWORD,
            'role' => Role::SalesAgent, 'warehouse_id' => $this->store->id, 'is_active' => true,
        ]);
    }

    // ------------------------------------------------------------ Catalogue

    /** @return array<string, Supplier> */
    private function seedSuppliers(): array
    {
        $rows = [
            'info' => ['Ivoire Informatique Import', 'Koffi N\'Guessan', '+225 07 08 11 22 33', 'Zone 4, Marcory', 10],
            'print' => ['Sotech Distribution', 'Fatou Diabaté', '+225 05 44 21 09 87', 'Rue des Jardins, Cocody Deux-Plateaux', 7],
            'conso' => ['Global Consommables Abidjan', 'Jean-Marc Aké', '+225 01 02 33 44 55', 'Boulevard VGE, Treichville', 4],
            'paper' => ['Papeterie du Plateau', 'Christelle Gnagne', '+225 07 77 65 43 21', 'Avenue Franchet d\'Espèrey, Plateau', 2],
            'net' => ['Réseaux et Câbles CI', 'Moussa Sangaré', '+225 05 06 07 08 09', 'Rue du Commerce, Adjamé', 14],
            'acc' => ['Bureautique Express Treichville', 'Nadia Coulibaly', '+225 01 70 80 90 10', 'Avenue 16, Treichville', 5],
        ];

        $suppliers = [];
        foreach ($rows as $key => [$name, $contact, $phone, $address, $lead]) {
            $suppliers[$key] = Supplier::create([
                'name' => $name, 'contact_name' => $contact, 'phone' => $phone, 'address' => $address.', Abidjan',
                'email' => 'contact@'.str($name)->slug().'.example', 'lead_time_days' => $lead, 'is_active' => true,
            ]);
        }

        return $suppliers;
    }

    /** @return array<int, array{product: Product, supplier: string, initial: int, weekly: int, profile: string}> */
    private function seedCatalog(array $suppliers): array
    {
        $categories = [];
        foreach ([
            'ORD' => ['Ordinateurs', 'Portables, postes fixes et écrans'],
            'IMP' => ['Imprimantes', 'Laser, jet d\'encre et multifonctions'],
            'CON' => ['Consommables', 'Toners, cartouches et tambours'],
            'PAP' => ['Papeterie', 'Papier, classement et petites fournitures'],
            'RES' => ['Réseau', 'Routeurs, commutateurs, câbles et onduleurs'],
            'PER' => ['Périphériques', 'Claviers, souris, casques et webcams'],
            'STO' => ['Stockage', 'Disques, clés USB et cartes mémoire'],
        ] as $code => [$name, $description]) {
            $categories[$code] = Category::create(['code' => $code, 'name' => $name, 'description' => $description]);
        }

        // [catégorie, fournisseur, libellé, prix d'achat, prix de vente, seuil, stock initial, ventes par semaine, profil final]
        // Profil final : ok (stock normal), low (sous le seuil), out (rupture).
        $catalog = [
            ['ORD', 'info', 'Ordinateur portable HP 250 G9 Core i5 8 Go 512 Go', 285000, 349000, 3, 14, 2, 'ok'],
            ['ORD', 'info', 'Ordinateur portable Lenovo IdeaPad 3 Core i3 8 Go', 235000, 289000, 3, 12, 2, 'ok'],
            ['ORD', 'info', 'Ordinateur portable Dell Latitude 3540 Core i7 16 Go', 465000, 559000, 2, 6, 1, 'low'],
            ['ORD', 'info', 'Unité centrale HP ProDesk 400 G7 Core i5', 310000, 379000, 2, 8, 1, 'ok'],
            ['ORD', 'info', 'Écran Samsung 24 pouces Full HD', 68000, 89000, 4, 20, 3, 'ok'],
            ['ORD', 'info', 'Écran Dell 27 pouces P2723D', 145000, 185000, 2, 6, 1, 'out'],
            ['ORD', 'info', 'Tablette Samsung Galaxy Tab A9 64 Go', 105000, 135000, 3, 10, 1, 'ok'],
            ['IMP', 'print', 'Imprimante laser HP LaserJet M111w', 78000, 99000, 3, 12, 2, 'ok'],
            ['IMP', 'print', 'Imprimante multifonction Canon PIXMA G3470', 92000, 119000, 3, 10, 2, 'low'],
            ['IMP', 'print', 'Imprimante multifonction Epson EcoTank L3250', 98000, 125000, 3, 10, 1, 'ok'],
            ['IMP', 'print', 'Imprimante laser couleur HP Color LaserJet M255dw', 235000, 289000, 2, 5, 1, 'ok'],
            ['IMP', 'print', 'Scanner Canon CanoScan LiDE 300', 48000, 62000, 2, 6, 1, 'out'],
            ['IMP', 'print', 'Photocopieur Canon imageRUNNER 2425', 1150000, 1390000, 1, 2, 0, 'ok'],
            ['CON', 'conso', 'Cartouche HP 305 noire', 7500, 10500, 15, 80, 14, 'ok'],
            ['CON', 'conso', 'Cartouche HP 305 couleur', 8500, 11900, 12, 60, 10, 'low'],
            ['CON', 'conso', 'Toner HP 106A noir', 21000, 28500, 10, 50, 8, 'ok'],
            ['CON', 'conso', 'Toner HP 85A noir', 24000, 32000, 8, 40, 6, 'ok'],
            ['CON', 'conso', 'Bouteille d\'encre Epson 103 noire', 4200, 6000, 15, 70, 10, 'ok'],
            ['CON', 'conso', 'Bouteille d\'encre Canon GI-41 cyan', 4300, 6200, 10, 40, 5, 'out'],
            ['CON', 'conso', 'Toner Canon C-EXV 33 pour photocopieur', 26000, 34500, 5, 20, 2, 'ok'],
            ['CON', 'conso', 'Tambour Brother DR-2400', 31000, 41000, 4, 12, 1, 'low'],
            ['PAP', 'paper', 'Ramette papier A4 80 g Double A', 2900, 3800, 50, 300, 60, 'ok'],
            ['PAP', 'paper', 'Ramette papier A3 80 g Navigator', 6200, 8200, 15, 60, 6, 'ok'],
            ['PAP', 'paper', 'Carton de 5 ramettes A4 PaperOne', 14000, 18000, 20, 100, 15, 'low'],
            ['PAP', 'paper', 'Classeur à levier dos 8 cm', 1100, 1700, 40, 200, 30, 'ok'],
            ['PAP', 'paper', 'Boîte de 50 chemises cartonnées', 3500, 4900, 15, 60, 6, 'ok'],
            ['PAP', 'paper', 'Agrafeuse de bureau Rapid', 3800, 5500, 10, 40, 4, 'ok'],
            ['PAP', 'paper', 'Boîte d\'agrafes 24/6 (5 000)', 650, 1000, 30, 150, 20, 'out'],
            ['PAP', 'paper', 'Lot de 10 stylos bille Bic Cristal', 1300, 2000, 40, 200, 30, 'ok'],
            ['PAP', 'paper', 'Cahier de registre 300 pages', 2400, 3500, 15, 60, 6, 'ok'],
            ['PAP', 'paper', 'Calculatrice de bureau Casio', 7500, 10500, 6, 25, 3, 'ok'],
            ['RES', 'net', 'Routeur wifi TP-Link Archer C6', 21000, 28500, 5, 20, 3, 'ok'],
            ['RES', 'net', 'Commutateur TP-Link 8 ports gigabit', 14000, 19500, 5, 20, 2, 'ok'],
            ['RES', 'net', 'Commutateur Cisco CBS110 24 ports', 98000, 128000, 2, 5, 1, 'low'],
            ['RES', 'net', 'Câble réseau RJ45 Cat6 5 m', 1400, 2500, 30, 150, 20, 'ok'],
            ['RES', 'net', 'Bobine câble Cat6 305 m', 52000, 69000, 3, 10, 1, 'ok'],
            ['RES', 'net', 'Onduleur APC Back-UPS 650 VA', 42000, 55000, 5, 25, 4, 'ok'],
            ['RES', 'net', 'Onduleur APC Smart-UPS 1500 VA', 285000, 345000, 2, 4, 1, 'out'],
            ['RES', 'net', 'Point d\'accès Ubiquiti UniFi U6 Lite', 68000, 89000, 3, 8, 1, 'ok'],
            ['RES', 'net', 'Multiprise parafoudre 6 prises', 6500, 9500, 10, 40, 6, 'ok'],
            ['PER', 'acc', 'Clavier sans fil Logitech K270', 11000, 15500, 8, 40, 6, 'ok'],
            ['PER', 'acc', 'Souris sans fil Logitech M185', 5500, 8500, 10, 60, 10, 'ok'],
            ['PER', 'acc', 'Ensemble clavier souris HP 230', 14500, 19900, 6, 25, 3, 'low'],
            ['PER', 'acc', 'Casque USB Jabra Evolve 20', 24000, 32000, 4, 15, 2, 'ok'],
            ['PER', 'acc', 'Webcam Logitech C270 HD', 15000, 21000, 5, 20, 3, 'ok'],
            ['PER', 'acc', 'Haut-parleurs Creative Pebble 2.0', 12000, 16500, 4, 15, 2, 'ok'],
            ['PER', 'acc', 'Tapis de souris ergonomique', 2200, 3500, 10, 50, 6, 'ok'],
            ['PER', 'acc', 'Support d\'ordinateur portable réglable', 9500, 13500, 4, 15, 2, 'out'],
            ['PER', 'acc', 'Hub USB-C 7 en 1', 13500, 18500, 5, 20, 3, 'ok'],
            ['PER', 'acc', 'Vidéoprojecteur Epson CO-W01', 245000, 299000, 1, 4, 1, 'ok'],
            ['STO', 'info', 'Clé USB SanDisk 32 Go', 3200, 5000, 20, 100, 15, 'ok'],
            ['STO', 'info', 'Clé USB SanDisk 64 Go', 4800, 7500, 15, 80, 10, 'ok'],
            ['STO', 'info', 'Disque dur externe Seagate 1 To', 36000, 47000, 5, 20, 3, 'ok'],
            ['STO', 'info', 'Disque dur externe WD Elements 2 To', 52000, 67000, 4, 12, 2, 'low'],
            ['STO', 'info', 'Disque SSD Samsung 870 EVO 500 Go', 39000, 52000, 4, 15, 2, 'ok'],
            ['STO', 'info', 'Disque SSD Kingston A400 240 Go', 17500, 24000, 5, 20, 3, 'ok'],
            ['STO', 'info', 'Carte mémoire microSD 64 Go', 4500, 7000, 10, 40, 5, 'ok'],
            ['STO', 'info', 'Barrette mémoire DDR4 8 Go', 18000, 25000, 5, 20, 2, 'out'],
            ['STO', 'info', 'Boîtier externe pour disque 2,5 pouces', 4500, 6900, 6, 20, 2, 'ok'],
            ['STO', 'info', 'Lecteur graveur DVD externe', 11000, 15500, 3, 8, 1, 'ok'],
        ];

        $service = app(ProductService::class);
        $products = [];

        foreach ($catalog as [$category, $supplier, $name, $buy, $sell, $threshold, $initial, $weekly, $profile]) {
            $product = $service->create([
                'name' => $name,
                'category_id' => $categories[$category]->id,
                'supplier_id' => $suppliers[$supplier]->id,
                'purchase_price' => $buy,
                'selling_price' => $sell,
                'alert_threshold' => $threshold,
                'is_active' => true,
                // Seuil de réassort de la boutique : environ la moitié du seuil global.
                'reorder_points' => [$this->store->id => max(1, intdiv($threshold, 2))],
            ]);

            $products[] = compact('product', 'supplier', 'initial', 'weekly', 'profile');
        }

        // Un produit retiré de la vente : il reste au catalogue avec son historique.
        $service->create([
            'name' => 'Imprimante matricielle Epson LQ-350 (fin de série)', 'category_id' => $categories['IMP']->id,
            'supplier_id' => $suppliers['print']->id, 'purchase_price' => 165000, 'selling_price' => 199000,
            'alert_threshold' => 0, 'is_active' => false,
        ]);

        return $products;
    }

    // ------------------------------------------------------------ Historique

    private function seedInitialStock(array $products, array $suppliers): void
    {
        $purchases = app(PurchaseService::class);

        // Stock de départ : une réception directe par fournisseur à l'entrepôt.
        foreach (collect($products)->groupBy('supplier') as $supplierKey => $group) {
            $purchases->receiveDirect($suppliers[$supplierKey], $this->warehouse, $group->map(fn ($p) => [
                'product_id' => $p['product']->id, 'quantity' => $p['initial'], 'unit_cost' => $p['product']->purchase_price,
            ])->all(), ['delivery_note_number' => 'STOCK-INITIAL', 'notes' => 'Reprise du stock existant au démarrage de StockFlow'], $this->users['manager']);
        }

        $this->advance(2);
        $this->restockStore($products, true);
    }

    private function simulateActivity(array $products, array $suppliers, Carbon $start): void
    {
        $sales = app(SalesService::class);
        $stock = app(StockService::class);
        $purchases = app(PurchaseService::class);
        $inventories = app(InventoryService::class);
        $pendingReceipts = [];

        for ($day = 3; $day < self::DAYS - 2; $day++) {
            $date = $start->copy()->addDays($day);
            if ($date->isSunday()) {
                continue;
            }

            // Le lundi : réassort de la boutique et commandes des produits sous le seuil.
            if ($date->isMonday()) {
                $this->at($date, 8);
                $this->restockStore($products);
                $this->orderLowProducts($products, $suppliers, $pendingReceipts);
            }

            // Livraisons attendues ce jour : parfois partielles.
            foreach ($pendingReceipts as $i => [$order, $dueDay, $partial]) {
                if ($dueDay !== $day) {
                    continue;
                }
                $this->at($date, 10);
                $items = $order->items()->get();
                $purchases->receive($order, $items->map(fn ($item) => [
                    'purchase_order_item_id' => $item->id,
                    'quantity' => $partial ? max(1, intdiv($item->remaining(), 2)) : $item->remaining(),
                ])->all(), ['delivery_note_number' => 'BL-'.mt_rand(10000, 99999)], $this->users[mt_rand(0, 1) ? 'manager' : 'manager2']);

                unset($pendingReceipts[$i]);
                if ($partial) {
                    $pendingReceipts[] = [$order->fresh(), $day + mt_rand(3, 6), false];
                }
            }

            // Ventes du jour en boutique.
            $count = $date->isSaturday() ? mt_rand(4, 7) : mt_rand(2, 5);
            for ($s = 0; $s < $count; $s++) {
                $this->at($date, mt_rand(9, 18), mt_rand(0, 59));
                $lines = $this->randomCart($products);
                if ($lines === []) {
                    continue;
                }
                $seller = $day < 40 && mt_rand(0, 3) === 0 ? 'former' : (mt_rand(0, 1) ? 'agent' : 'agent2');
                try {
                    $sale = $sales->create($this->store, $lines, mt_rand(0, 2) === 0 ? $this->randomCustomer() : null, $this->users[$seller]);
                    // Quelques annulations, toujours motivées.
                    if (mt_rand(0, 40) === 0) {
                        $this->at($date, 19);
                        $sales->cancel($sale, $this->users['manager'], 'Erreur de saisie du vendeur, article non remis au client');
                    }
                } catch (BusinessException) {
                    // Panier refusé (stock insuffisant) : comme au comptoir, la vente n'a pas lieu.
                }
            }

            // De temps en temps, une sortie interne ou un ajustement.
            if (mt_rand(0, 6) === 0) {
                $this->at($date, 17);
                $pick = $products[array_rand($products)]['product'];
                $available = $stock->available($pick->id, $this->warehouse->id);
                if ($available > 2) {
                    $stock->decrease($pick->id, $this->warehouse->id, 1, MovementType::InternalUse, $this->users['manager'],
                        ['Usage du service administratif', 'Article endommagé au déballage', 'Échantillon remis à un client grand compte'][mt_rand(0, 2)]);
                }
            }

            // À mi-parcours : inventaire tournant des consommables en boutique, validé avec écarts.
            if ($day === 55) {
                $this->runValidatedInventory($inventories, $date);
            }

            // L'ancien vendeur quitte l'entreprise : son compte est désactivé.
            if ($day === 40) {
                $this->users['former']->update(['is_active' => false]);
            }
        }
    }

    private function runValidatedInventory(InventoryService $inventories, Carbon $date): void
    {
        $this->at($date, 7, 30);
        $category = Category::where('code', 'CON')->firstOrFail();
        $session = $inventories->open($this->store, $category, $this->users['manager'], 'Inventaire tournant mensuel des consommables');

        $this->at($date, 8, 15);
        $counts = [];
        $variances = 0;
        foreach ($session->items()->get() as $item) {
            $current = app(StockService::class)->available($item->product_id, $this->store->id);
            $delta = 0;
            if ($current > 3 && $variances < 3 && mt_rand(0, 2) === 0) {
                $delta = [-2, -1, 1][mt_rand(0, 2)];
                $variances++;
            }
            $counts[$item->id] = [
                'counted_quantity' => $current + $delta,
                'justification' => match ($delta) {
                    -2 => 'Deux cartouches abîmées retrouvées sous le comptoir',
                    -1 => 'Une unité manquante, probablement non saisie lors d\'une vente',
                    1 => 'Une unité retrouvée dans la réserve de la boutique',
                    default => null,
                },
            ];
        }
        $inventories->recordCounts($session, $counts, $this->users['manager']);
        $inventories->submit($session, $this->users['manager']);

        $this->at($date, 11);
        $inventories->validate($session, $this->users['admin']);
    }

    /** Ajuste les produits désignés pour que la démonstration montre stock normal, seuil et rupture. */
    private function shapeFinalState(array $products): void
    {
        $this->at($this->today->copy()->subDay(), 16);
        $stock = app(StockService::class);

        foreach ($products as $entry) {
            $product = $entry['product'];
            $threshold = $product->alert_threshold;
            $levels = fn () => [
                $stock->available($product->id, $this->warehouse->id),
                $stock->available($product->id, $this->store->id),
            ];
            [$ent, $pdv] = $levels();
            $total = $ent + $pdv;

            $target = match ($entry['profile']) {
                'out' => 0,
                'low' => max(1, $threshold - 1),
                default => null,
            };

            if ($target === null) {
                // Stock normal : on complète si l'activité simulée l'a trop entamé.
                if ($total <= $threshold) {
                    $stock->increase($product->id, $this->warehouse->id, $threshold * 2 + 2, MovementType::ManualAdjustment,
                        $this->users['manager'], 'Régularisation après contrôle du stock de réserve');
                }

                continue;
            }

            // Le surplus part par des ventes en boutique, après transfert depuis l'entrepôt.
            $excess = $total - $target;
            if ($excess > 0) {
                $fromWarehouse = min($ent, $excess);
                if ($fromWarehouse > 0) {
                    app(TransferService::class)->execute($this->warehouse, $this->store,
                        [['product_id' => $product->id, 'quantity' => $fromWarehouse]], 'Réassort de fin de semaine', $this->users['manager']);
                }
                app(SalesService::class)->create($this->store, [['product_id' => $product->id, 'quantity' => $excess]],
                    $this->randomCustomer(), $this->users['agent']);
            } elseif ($excess < 0) {
                $stock->increase($product->id, $this->warehouse->id, -$excess, MovementType::ManualAdjustment,
                    $this->users['manager'], 'Régularisation après contrôle du stock de réserve');
            }
        }
    }

    /** Documents en cours, pour montrer chaque étape le jour de la démonstration. */
    private function seedOpenDocuments(array $products, array $suppliers): void
    {
        $purchases = app(PurchaseService::class);
        $this->at($this->today->copy()->subDay(), 9);

        $byProfile = collect($products)->groupBy('profile');

        // Une commande passée, partiellement reçue, pour les produits sous le seuil.
        $low = $byProfile['low']->take(3);
        $order = $purchases->confirm($purchases->createDraft([
            'supplier_id' => $suppliers[$low->first()['supplier']]->id, 'warehouse_id' => $this->warehouse->id,
            'notes' => 'Réapprovisionnement des articles sous le seuil',
            'lines' => $low->map(fn ($p) => ['product_id' => $p['product']->id, 'quantity' => $p['product']->alert_threshold * 3, 'unit_cost' => $p['product']->purchase_price])->all(),
        ], $this->users['manager']), $this->users['manager']);
        $order->load('items');
        $this->at($this->today->copy()->subDay(), 15);
        $purchases->receive($order, [[
            'purchase_order_item_id' => $order->items->first()->id, 'quantity' => 1,
        ]], ['delivery_note_number' => 'BL-48213'], $this->users['manager2']);

        // Une commande passée, en attente de livraison, pour les ruptures.
        $out = $byProfile['out']->take(2);
        $this->at($this->today, 8, 30);
        $purchases->confirm($purchases->createDraft([
            'supplier_id' => $suppliers[$out->first()['supplier']]->id, 'warehouse_id' => $this->warehouse->id,
            'lines' => $out->map(fn ($p) => ['product_id' => $p['product']->id, 'quantity' => 5, 'unit_cost' => $p['product']->purchase_price])->all(),
        ], $this->users['manager']), $this->users['manager']);

        // Un brouillon.
        $purchases->createDraft([
            'supplier_id' => $suppliers['paper']->id, 'warehouse_id' => $this->warehouse->id,
            'notes' => 'Commande de rentrée, à confirmer avec la direction',
            'lines' => collect($products)->where('supplier', 'paper')->take(4)->map(fn ($p) => [
                'product_id' => $p['product']->id, 'quantity' => 20, 'unit_cost' => $p['product']->purchase_price,
            ])->values()->all(),
        ], $this->users['manager2']);

        // Un inventaire du rayon réseau en attente de validation par l'administrateur.
        $this->at($this->today, 7, 45);
        $inventories = app(InventoryService::class);
        $session = $inventories->open($this->warehouse, Category::where('code', 'RES')->firstOrFail(), $this->users['manager2'], 'Inventaire du rayon réseau');
        $counts = [];
        $first = true;
        foreach ($session->items()->get() as $item) {
            $current = app(StockService::class)->available($item->product_id, $this->warehouse->id);
            $delta = $first && $current > 0 ? -1 : 0;
            $first = $first && $delta === 0;
            $counts[$item->id] = ['counted_quantity' => $current + $delta, 'justification' => $delta ? 'Câble manquant, à vérifier avec le magasinier' : null];
        }
        $inventories->recordCounts($session, $counts, $this->users['manager2']);
        $inventories->submit($session, $this->users['manager2']);
    }

    // ------------------------------------------------------------ Outils

    /** Transfère vers la boutique ce qui manque pour couvrir deux semaines de ventes. */
    private function restockStore(array $products, bool $initial = false): void
    {
        $stock = app(StockService::class);
        $lines = [];

        foreach ($products as $p) {
            $target = max(2, $p['weekly'] * 2);
            $inStore = $stock->available($p['product']->id, $this->store->id);
            $inWarehouse = $stock->available($p['product']->id, $this->warehouse->id);
            $need = min($target - $inStore, $inWarehouse - ($initial ? 1 : 0));
            if ($need > 0) {
                $lines[] = ['product_id' => $p['product']->id, 'quantity' => $need];
            }
        }

        if ($lines !== []) {
            app(TransferService::class)->execute($this->warehouse, $this->store, $lines,
                $initial ? 'Mise en place du stock de la boutique' : 'Réassort hebdomadaire', $this->users['manager']);
        }
    }

    private function orderLowProducts(array $products, array $suppliers, array &$pendingReceipts): void
    {
        $stock = app(StockService::class);
        $purchases = app(PurchaseService::class);

        $toOrder = collect($products)->filter(fn ($p) => $stock->totalFor($p['product']->id) <= $p['product']->alert_threshold + $p['weekly']);

        foreach ($toOrder->groupBy('supplier') as $supplierKey => $group) {
            $order = $purchases->createDraft([
                'supplier_id' => $suppliers[$supplierKey]->id, 'warehouse_id' => $this->warehouse->id,
                'lines' => $group->map(fn ($p) => [
                    'product_id' => $p['product']->id,
                    'quantity' => max($p['product']->alert_threshold, $p['weekly'] * 4),
                    'unit_cost' => $p['product']->purchase_price,
                ])->values()->all(),
            ], $this->users['manager']);
            $purchases->confirm($order, $this->users['manager']);

            $lead = $suppliers[$supplierKey]->lead_time_days;
            $pendingReceipts[] = [$order, $this->dayIndex() + max(1, $lead + mt_rand(-1, 2)), mt_rand(0, 3) === 0];
        }
    }

    /** Panier de 1 à 3 produits, pondéré par les ventes habituelles de chaque produit. */
    private function randomCart(array $products): array
    {
        $stock = app(StockService::class);
        $weighted = [];
        foreach ($products as $p) {
            for ($w = 0; $w < max(1, $p['weekly']); $w++) {
                $weighted[] = $p;
            }
        }

        $lines = [];
        for ($i = 0, $n = mt_rand(1, 3); $i < $n; $i++) {
            $p = $weighted[array_rand($weighted)];
            $available = $stock->available($p['product']->id, $this->store->id);
            if ($available > 0 && ! isset($lines[$p['product']->id])) {
                $lines[$p['product']->id] = ['product_id' => $p['product']->id, 'quantity' => min($available, mt_rand(1, $p['weekly'] > 10 ? 4 : 2))];
            }
        }

        return array_values($lines);
    }

    private function randomCustomer(): string
    {
        return [
            'Cabinet Kouadio et associés', 'École primaire Les Lauriers', 'Mme Adjoua Brou', 'Pharmacie de la Riviera',
            'M. Ousmane Cissé', 'Clinique Sainte-Anne', 'Lycée moderne de Cocody', 'Mme Estelle Gbagbo', 'Garage Central Adjamé',
            'Société Agri-Export', 'M. Didier Konan', 'Hôtel du Lagon',
        ][mt_rand(0, 11)];
    }

    private function at(Carbon $date, int $hour, int $minute = 0): void
    {
        Carbon::setTestNow($date->copy()->setTime($hour, $minute, mt_rand(0, 59)));
    }

    private function advance(int $days): void
    {
        Carbon::setTestNow(now()->addDays($days));
    }

    private function dayIndex(): int
    {
        return (int) $this->start->copy()->startOfDay()->diffInDays(now()->startOfDay());
    }
}
