<?php

/*
| Processus de vente lancé en parallèle par les tests de concurrence.
|
| Arguments : un JSON {"warehouse_id", "user_id", "lines": [...], "start_at"}.
| Le processus démarre l'application, attend l'instant start_at (pour que tous les
| processus frappent la base en même temps), tente la vente et écrit sur la sortie
| standard "ok <référence>", "refused" (stock insuffisant) ou "error <message>".
*/

use App\Exceptions\InsufficientStockException;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\SalesService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../../vendor/autoload.php';

$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$job = json_decode($argv[1], true, flags: JSON_THROW_ON_ERROR);

$warehouse = Warehouse::findOrFail($job['warehouse_id']);
$user = User::findOrFail($job['user_id']);

// Point de départ commun à tous les processus.
$wait = $job['start_at'] - microtime(true);
if ($wait > 0) {
    usleep((int) ($wait * 1_000_000));
}

try {
    $sale = app(SalesService::class)->create($warehouse, $job['lines'], null, $user);
    echo 'ok '.$sale->reference;
} catch (InsufficientStockException) {
    echo 'refused';
} catch (Throwable $e) {
    echo 'error '.get_class($e).': '.$e->getMessage();
}
