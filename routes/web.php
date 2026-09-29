<?php

use App\Http\Controllers\Admin\PricingSettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Catalog\CustomerController;
use App\Http\Controllers\Catalog\CuttingDieController;
use App\Http\Controllers\Catalog\PaperGrammageController;
use App\Http\Controllers\Catalog\PaperGrammagePriceController;
use App\Http\Controllers\Catalog\PaperSupplierController;
use App\Http\Controllers\Catalog\PaperTypeController;
use App\Http\Controllers\Catalog\PressController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    // --- Customers ---------------------------------------------------------
    Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::middleware('can:manage-customers')->group(function () {
        Route::resource('customers', CustomerController::class)->except(['index', 'show']);
    });

    // --- Catalogue (read: everyone, write: per ability) -----------------------
    Route::get('paper-types', [PaperTypeController::class, 'index'])->name('paper-types.index');
    Route::get('paper-suppliers', [PaperSupplierController::class, 'index'])->name('paper-suppliers.index');
    Route::get('dies', [CuttingDieController::class, 'index'])->name('dies.index');
    Route::get('presses', [PressController::class, 'index'])->name('presses.index');

    Route::middleware('can:manage-paper-prices')->group(function () {
        // Sales can maintain supplier prices from the paper type page.
        Route::get('paper-types/{paper_type}/edit', [PaperTypeController::class, 'edit'])->name('paper-types.edit');
        Route::post('grammages/{grammage}/prices', [PaperGrammagePriceController::class, 'store'])->name('grammages.prices.store');
        Route::delete('prices/{price}', [PaperGrammagePriceController::class, 'destroy'])->name('prices.destroy');
    });

    Route::middleware('can:manage-catalog')->group(function () {
        Route::resource('paper-types', PaperTypeController::class)->except(['index', 'show', 'edit']);
        Route::post('paper-types/{paper_type}/grammages', [PaperGrammageController::class, 'store'])->name('paper-types.grammages.store');
        Route::delete('grammages/{grammage}', [PaperGrammageController::class, 'destroy'])->name('grammages.destroy');
        Route::resource('paper-suppliers', PaperSupplierController::class)->except(['index', 'show']);
        Route::resource('dies', CuttingDieController::class)->except(['index', 'show'])->parameters(['dies' => 'die']);
        Route::resource('presses', PressController::class)->except(['index', 'show']);
    });

    Route::patch('presses/{press}/backlog', [PressController::class, 'updateBacklog'])
        ->middleware('can:update-press-backlog')
        ->name('presses.backlog');

    // --- Admin -------------------------------------------------------------
    Route::middleware('can:manage-users')->group(function () {
        Route::resource('users', UserController::class)->except(['show']);
    });

    Route::middleware('can:manage-settings')->group(function () {
        Route::get('admin/pricing', [PricingSettingsController::class, 'edit'])->name('pricing-settings.edit');
        Route::put('admin/pricing', [PricingSettingsController::class, 'update'])->name('pricing-settings.update');
    });
});

require __DIR__.'/settings.php';
