<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\CompanySettingsController;
use App\Http\Controllers\Admin\PricingSettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Catalog\CustomerController;
use App\Http\Controllers\Catalog\CuttingDieController;
use App\Http\Controllers\Catalog\PaperGrammageController;
use App\Http\Controllers\Catalog\PaperGrammagePriceController;
use App\Http\Controllers\Catalog\PaperSupplierController;
use App\Http\Controllers\Catalog\PaperTypeController;
use App\Http\Controllers\Catalog\PressController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\Jobs\BoxJobController;
use App\Http\Controllers\Jobs\JobCompletionController;
use App\Http\Controllers\Jobs\JobController;
use App\Http\Controllers\Jobs\JobEditController;
use App\Http\Controllers\Jobs\JobFileController;
use App\Http\Controllers\Jobs\JobPressAssignmentController;
use App\Http\Controllers\Jobs\JobQuoteController;
use App\Http\Controllers\Jobs\JobStageController;
use App\Http\Controllers\Jobs\JobStatusController;
use App\Http\Controllers\Jobs\ManualJobController;
use App\Http\Controllers\Jobs\OdooInvoiceSyncController;
use App\Http\Controllers\Jobs\WorkOrderController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductionBoardController;
use App\Http\Controllers\ReportsController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // --- Jobs --------------------------------------------------------------
    Route::get('jobs', [JobController::class, 'index'])->name('jobs.index');
    Route::get('production', ProductionBoardController::class)->name('production.board');

    // --- Notifications (bell) ----------------------------------------------
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/{id}', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');

    Route::middleware('can:create-jobs')->group(function () {
        Route::get('jobs/create/box', [BoxJobController::class, 'create'])->name('jobs.box.create');
        Route::post('jobs/box', [BoxJobController::class, 'store'])->name('jobs.box.store');
        Route::get('jobs/create/manual', [ManualJobController::class, 'create'])->name('jobs.manual.create');
        Route::post('jobs/manual', [ManualJobController::class, 'store'])->name('jobs.manual.store');
        Route::get('jobs/{job}/edit', [JobEditController::class, 'edit'])->name('jobs.edit');
        Route::put('jobs/{job}/box', [JobEditController::class, 'updateBox'])->name('jobs.box.update');
        Route::put('jobs/{job}/manual', [JobEditController::class, 'updateManual'])->name('jobs.manual.update');
    });

    Route::get('jobs/{job}', [JobController::class, 'show'])->name('jobs.show');
    Route::get('jobs/{job}/quote', [JobQuoteController::class, 'show'])->name('jobs.quote');
    Route::get('jobs/{job}/work-order', [WorkOrderController::class, 'show'])->name('jobs.work-order');
    Route::get('jobs/{job}/scan', [WorkOrderController::class, 'scan'])->name('jobs.scan');
    // Design file archive: everyone on the team can view/upload; delete = uploader or admin.
    Route::post('jobs/{job}/files', [JobFileController::class, 'store'])->name('jobs.files.store');
    Route::get('jobs/{job}/files/{file}', [JobFileController::class, 'download'])->name('jobs.files.download');
    Route::delete('jobs/{job}/files/{file}', [JobFileController::class, 'destroy'])->name('jobs.files.destroy');
    // Per-transition role checks live in JobLifecycleService.
    Route::patch('jobs/{job}/status', [JobStatusController::class, 'update'])->name('jobs.status.update');

    Route::middleware('can:run-production')->group(function () {
        Route::post('jobs/{job}/complete', [JobCompletionController::class, 'store'])->name('jobs.complete');
        Route::patch('jobs/{job}/stages/{stage}', [JobStageController::class, 'update'])->name('jobs.stages.update');
        Route::post('jobs/{job}/press-assignments', [JobPressAssignmentController::class, 'store'])->name('jobs.press-assignments.store');
    });

    Route::middleware('can:manage-invoicing')->group(function () {
        Route::post('jobs/{job}/odoo-syncs', [OdooInvoiceSyncController::class, 'store'])->name('jobs.odoo-syncs.store');
        Route::post('jobs/{job}/odoo-syncs/manual', [OdooInvoiceSyncController::class, 'manual'])->name('jobs.odoo-syncs.manual');
    });

    // --- Leads (الفرص) -------------------------------------------------------
    Route::middleware('can:manage-leads')->group(function () {
        Route::resource('leads', LeadController::class)->except(['show']);
        Route::post('leads/{lead}/convert', [LeadController::class, 'convert'])->name('leads.convert');
    });

    // --- Paper inventory (read: everyone, write: admin + production) ---------
    Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('inventory/{stock}', [InventoryController::class, 'show'])->name('inventory.show');
    Route::middleware('can:manage-inventory')->group(function () {
        Route::post('inventory', [InventoryController::class, 'store'])->name('inventory.store');
        Route::patch('inventory/{stock}', [InventoryController::class, 'update'])->name('inventory.update');
        Route::post('inventory/{stock}/receipts', [InventoryController::class, 'receive'])->name('inventory.receive');
        Route::post('inventory/{stock}/adjustments', [InventoryController::class, 'adjust'])->name('inventory.adjust');
    });

    // --- Customers ---------------------------------------------------------
    Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('customers/{customer}', [CustomerController::class, 'show'])->whereNumber('customer')->name('customers.show');
    Route::middleware('can:manage-invoicing')->group(function () {
        Route::post('customers/{customer}/payments', [PaymentController::class, 'store'])->name('customers.payments.store');
        Route::delete('payments/{payment}', [PaymentController::class, 'destroy'])->name('payments.destroy');
    });
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
        Route::get('admin/company', [CompanySettingsController::class, 'edit'])->name('company-settings.edit');
        Route::put('admin/company', [CompanySettingsController::class, 'update'])->name('company-settings.update');
        Route::get('admin/activity', ActivityLogController::class)->name('activity-log');
        Route::get('reports', ReportsController::class)->name('reports');
    });
});

require __DIR__.'/settings.php';
