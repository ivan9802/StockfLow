<?php

// Routes des modules métier. Fichier inclus par web.php dans le groupe authentifié.

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\Internal\ProductSearchController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SupplierController;
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

// ---------------------------------------------------------------- Points JSON appelés par Fetch

Route::prefix('internal')->name('internal.')->middleware('throttle:120,1')->group(function () {
    Route::get('/products/search', ProductSearchController::class)->middleware('can:catalog.view')->name('products.search');
});
