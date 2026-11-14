<?php

// Routes des modules métier. Fichier inclus par web.php dans le groupe authentifié.
// Chaque groupe porte le droit exigé (middleware "can:"), vérifié côté serveur.

use App\Http\Controllers\AlertController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\GoodsReceiptController;
use App\Http\Controllers\Internal\DashboardDataController;
use App\Http\Controllers\Internal\ProductSearchController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\MovementController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WarehouseController;
use Illuminate\Support\Facades\Route;

// ---------------------------------------------------------------- Catalogue

// La création est déclarée avant la fiche pour que /products/create ne soit pas lu comme un identifiant.
Route::middleware('can:catalog.manage')->group(function () {
    Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
    Route::post('/products', [ProductController::class, 'store'])->name('products.store');
    Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
    Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');

    Route::resource('categories', CategoryController::class)->except('show');
});

Route::middleware('can:catalog.view')->group(function () {
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');
    Route::get('/products/{product}/image', [ProductController::class, 'image'])->name('products.image');
});

Route::resource('suppliers', SupplierController::class)->except('show')->middleware('can:suppliers.manage');

// ---------------------------------------------------------------- Stock

Route::get('/stock', [StockController::class, 'index'])->middleware('can:stock.view')->name('stock.index');

Route::middleware('can:movements.view')->group(function () {
    Route::get('/movements', [MovementController::class, 'index'])->name('movements.index');
});
Route::middleware('can:movements.manage')->group(function () {
    Route::get('/movements/create', [MovementController::class, 'create'])->name('movements.create');
    Route::post('/movements', [MovementController::class, 'store'])->name('movements.store');
});

Route::middleware('can:transfers.manage')->group(function () {
    Route::get('/transfers', [TransferController::class, 'index'])->name('transfers.index');
    Route::get('/transfers/create', [TransferController::class, 'create'])->name('transfers.create');
    Route::post('/transfers', [TransferController::class, 'store'])->name('transfers.store');
    Route::get('/transfers/{transfer}', [TransferController::class, 'show'])->name('transfers.show');
    Route::get('/transfers/{transfer}/print', [TransferController::class, 'print'])->name('transfers.print');
    Route::post('/transfers/{transfer}/cancel', [TransferController::class, 'cancel'])->name('transfers.cancel');
});

Route::middleware('can:inventories.manage')->group(function () {
    Route::get('/inventories', [InventoryController::class, 'index'])->name('inventories.index');
    Route::get('/inventories/create', [InventoryController::class, 'create'])->name('inventories.create');
    Route::post('/inventories', [InventoryController::class, 'store'])->name('inventories.store');
    Route::get('/inventories/{inventory}', [InventoryController::class, 'show'])->name('inventories.show');
    Route::get('/inventories/{inventory}/print', [InventoryController::class, 'print'])->name('inventories.print');
    Route::put('/inventories/{inventory}/counts', [InventoryController::class, 'counts'])->name('inventories.counts');
    Route::post('/inventories/{inventory}/submit', [InventoryController::class, 'submit'])->name('inventories.submit');
    Route::post('/inventories/{inventory}/cancel', [InventoryController::class, 'cancel'])->name('inventories.cancel');
});
Route::middleware('can:inventories.validate')->group(function () {
    Route::post('/inventories/{inventory}/validate', [InventoryController::class, 'validateSession'])->name('inventories.validate');
    Route::post('/inventories/{inventory}/send-back', [InventoryController::class, 'sendBack'])->name('inventories.send-back');
});

// ---------------------------------------------------------------- Achats

Route::middleware('can:purchases.manage')->group(function () {
    Route::get('/purchase-orders', [PurchaseOrderController::class, 'index'])->name('purchase-orders.index');
    Route::get('/purchase-orders/create', [PurchaseOrderController::class, 'create'])->name('purchase-orders.create');
    Route::post('/purchase-orders', [PurchaseOrderController::class, 'store'])->name('purchase-orders.store');
    Route::get('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->name('purchase-orders.show');
    Route::get('/purchase-orders/{purchaseOrder}/edit', [PurchaseOrderController::class, 'edit'])->name('purchase-orders.edit');
    Route::put('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'update'])->name('purchase-orders.update');
    Route::post('/purchase-orders/{purchaseOrder}/confirm', [PurchaseOrderController::class, 'confirm'])->name('purchase-orders.confirm');
    Route::post('/purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])->name('purchase-orders.cancel');
    Route::post('/purchase-orders/{purchaseOrder}/close', [PurchaseOrderController::class, 'close'])->name('purchase-orders.close');
    Route::get('/purchase-orders/{purchaseOrder}/receive', [GoodsReceiptController::class, 'createFromOrder'])->name('purchase-orders.receive');
    Route::post('/purchase-orders/{purchaseOrder}/receive', [GoodsReceiptController::class, 'storeFromOrder'])->name('purchase-orders.receive.store');

    Route::get('/goods-receipts', [GoodsReceiptController::class, 'index'])->name('goods-receipts.index');
    Route::get('/goods-receipts/create', [GoodsReceiptController::class, 'create'])->name('goods-receipts.create');
    Route::post('/goods-receipts', [GoodsReceiptController::class, 'store'])->name('goods-receipts.store');
    Route::get('/goods-receipts/{goodsReceipt}', [GoodsReceiptController::class, 'show'])->name('goods-receipts.show');
    Route::get('/goods-receipts/{goodsReceipt}/print', [GoodsReceiptController::class, 'print'])->name('goods-receipts.print');
});

// ---------------------------------------------------------------- Ventes

Route::middleware('can:sales.create')->group(function () {
    Route::get('/sales/create', [SaleController::class, 'create'])->name('sales.create');
    Route::post('/sales', [SaleController::class, 'store'])->name('sales.store');
});
// Le vendeur ne voit que ses ventes : contrôle fin dans SalePolicy.
Route::middleware('can:sales.view')->group(function () {
    Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
    Route::get('/sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
    Route::get('/sales/{sale}/print', [SaleController::class, 'print'])->name('sales.print');
});
Route::post('/sales/{sale}/cancel', [SaleController::class, 'cancel'])->middleware('can:sales.cancel')->name('sales.cancel');

// ---------------------------------------------------------------- Pilotage

Route::middleware('can:alerts.view')->group(function () {
    Route::get('/alerts', [AlertController::class, 'index'])->name('alerts.index');
    Route::post('/alerts/read', [AlertController::class, 'markAllRead'])->name('alerts.read');
});

Route::middleware('can:reports.view')->group(function () {
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/stock.{format}', [ReportController::class, 'stock'])->whereIn('format', ['csv', 'pdf'])->name('reports.stock');
    Route::get('/reports/movements.{format}', [ReportController::class, 'movements'])->whereIn('format', ['csv', 'pdf'])->name('reports.movements');
    Route::get('/reports/sales.csv', [ReportController::class, 'sales'])->name('reports.sales');
});

// ---------------------------------------------------------------- Administration

Route::middleware('can:users.manage')->group(function () {
    Route::resource('users', UserController::class)->except(['show', 'destroy']);
    Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
});
Route::middleware('can:warehouses.manage')->group(function () {
    Route::get('/warehouses', [WarehouseController::class, 'index'])->name('warehouses.index');
    Route::get('/warehouses/{warehouse}/edit', [WarehouseController::class, 'edit'])->name('warehouses.edit');
    Route::put('/warehouses/{warehouse}', [WarehouseController::class, 'update'])->name('warehouses.update');
});
Route::get('/audit-logs', [AuditLogController::class, 'index'])->middleware('can:audit.view')->name('audit-logs.index');

// ---------------------------------------------------------------- Points JSON appelés par Fetch

Route::prefix('internal')->name('internal.')->middleware('throttle:120,1')->group(function () {
    Route::get('/products/search', ProductSearchController::class)->middleware('can:catalog.view')->name('products.search');
    Route::get('/dashboard', DashboardDataController::class)->name('dashboard');
});
