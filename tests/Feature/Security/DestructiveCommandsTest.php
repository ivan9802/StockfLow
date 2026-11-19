<?php

namespace Tests\Feature\Security;

use App\Providers\AppServiceProvider;
use Illuminate\Database\Console\Migrations\FreshCommand;
use Illuminate\Database\Console\Migrations\RefreshCommand;
use Illuminate\Database\Console\Migrations\ResetCommand;
use Illuminate\Database\Console\Migrations\RollbackCommand;
use Illuminate\Database\Console\WipeCommand;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;
use Tests\TestCase;

/**
 * En production, les commandes qui vident la base sont refusées même avec --force.
 *
 * Sécurité du test lui-même : aucune commande n'est lancée si l'interdiction n'est pas
 * active (le test échoue avant), et chaque commande vise une connexion qui n'existe pas.
 * Si le garde-fou venait à manquer, la commande échouerait sur cette connexion au lieu
 * de vider la base de test.
 */
class DestructiveCommandsTest extends TestCase
{
    private const MISSING_CONNECTION = 'garde_fou_connexion_inexistante';

    protected function tearDown(): void
    {
        DB::prohibitDestructiveCommands(false);
        $this->app->detectEnvironment(fn () => 'testing');

        parent::tearDown();
    }

    public static function destructiveCommands(): array
    {
        return [
            'migrate:fresh' => ['migrate:fresh', FreshCommand::class],
            'migrate:refresh' => ['migrate:refresh', RefreshCommand::class],
            'migrate:reset' => ['migrate:reset', ResetCommand::class],
            'migrate:rollback' => ['migrate:rollback', RollbackCommand::class],
            'db:wipe' => ['db:wipe', WipeCommand::class],
        ];
    }

    public function test_hors_production_les_commandes_restent_permises(): void
    {
        foreach (self::destructiveCommands() as [, $class]) {
            $this->assertFalse($this->isProhibited($class), $class);
        }
    }

    #[DataProvider('destructiveCommands')]
    public function test_en_production_la_commande_est_refusee_sans_rien_supprimer(string $command, string $class): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $this->app->getProvider(AppServiceProvider::class)->boot();

        if (! $this->isProhibited($class)) {
            $this->fail("Garde-fou inactif pour $command : la commande n'est pas lancée.");
        }

        $tables = Schema::getTableListing();

        $this->artisan($command, ['--force' => true, '--database' => self::MISSING_CONNECTION])
            ->expectsOutputToContain('prohibited')
            ->assertExitCode(1)
            ->run();

        $this->assertSame($tables, Schema::getTableListing());
    }

    private function isProhibited(string $class): bool
    {
        return (bool) (new ReflectionProperty($class, 'prohibitedFromRunning'))->getValue();
    }
}
