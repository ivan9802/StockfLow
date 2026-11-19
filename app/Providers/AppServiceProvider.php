<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\Category;
use App\Models\GoodsReceipt;
use App\Models\InventorySession;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\Format;
use App\View\Composers\NavigationComposer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Noms courts et stables dans les colonnes *_type (mouvements, audit,
        // notifications) : un renommage de classe ne casse pas l'historique.
        Relation::enforceMorphMap([
            'user' => User::class,
            'warehouse' => Warehouse::class,
            'category' => Category::class,
            'supplier' => Supplier::class,
            'product' => Product::class,
            'purchase_order' => PurchaseOrder::class,
            'goods_receipt' => GoodsReceipt::class,
            'sale' => Sale::class,
            'stock_transfer' => StockTransfer::class,
            'inventory_session' => InventorySession::class,
            'stock_movement' => StockMovement::class,
        ]);

        // Hors production, une affectation de masse sur un champ non autorisé
        // lève une erreur au lieu d'être ignorée en silence.
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        // En production, migrate:fresh, migrate:refresh, migrate:reset, migrate:rollback
        // et db:wipe refusent de s'exécuter, même avec --force : ils videraient la base
        // avant que DemoSeeder ne refuse de tourner. Une restauration passe par
        // scripts/restore.sh. Appelé avec false hors production, l'appel remet aussi
        // l'interdiction à zéro (état statique partagé par les tests d'un même processus).
        DB::prohibitDestructiveCommands($this->app->isProduction());

        // Règle unique de mot de passe (D-010) : la longueur, pas la composition.
        Password::defaults(fn () => Password::min(12));

        Paginator::useBootstrapFive();

        // Une Gate par droit de la matrice des rôles.
        foreach (array_keys(Role::permissions()) as $ability) {
            Gate::define($ability, fn (User $user) => $user->role->allows($ability));
        }

        // Compteurs de la barre latérale et de la cloche.
        View::composer(['layouts.partials.sidebar', 'layouts.partials.topbar'], NavigationComposer::class);

        // Format::money() et Format::date() utilisables tels quels dans les vues.
        AliasLoader::getInstance()->alias('Format', Format::class);

        // Jeton à usage unique repris par la politique de sécurité du contenu (CSP).
        Vite::useCspNonce();
    }
}
