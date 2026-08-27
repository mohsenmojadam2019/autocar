<?php

use App\Http\Controllers\Admin\FinancialOperationsController;
use App\Http\Controllers\Admin\OperationsController;
use App\Http\Controllers\Admin\OperationsHealthController;
use App\Http\Controllers\Admin\OrderCompletionController;
use App\Http\Controllers\Admin\ProviderSettingsController;
use App\Http\Controllers\SupportAttachmentController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::post('/account/tickets/{number}/attachments', [SupportAttachmentController::class, 'customer'])->name('account.tickets.attachments');
    Route::get('/support-attachments/{message}/{index}', [SupportAttachmentController::class, 'download'])->whereNumber(['message','index'])->name('support.attachments.download');
});

Route::prefix('admin')->name('admin.')->middleware('auth')->group(function (): void {
    Route::get('/orders-kanban', [OrderCompletionController::class, 'kanban'])->middleware('permission:orders.view')->name('orders.kanban');
    Route::post('/orders/phone', [OrderCompletionController::class, 'phone'])->middleware('permission:orders.manage')->name('orders.phone');
    Route::patch('/orders/bulk', [OrderCompletionController::class, 'bulk'])->middleware('permission:orders.manage')->name('orders.bulk');
    Route::get('/orders/{order}/packing-slip', [OrderCompletionController::class, 'packing'])->middleware('permission:orders.view')->name('orders.packing');
    Route::get('/orders/{order}/thermal-receipt', [OrderCompletionController::class, 'thermal'])->middleware('permission:orders.view')->name('orders.thermal');
    Route::get('/fulfillment/shipments/{shipment}/label', [OrderCompletionController::class, 'label'])->middleware('permission:orders.manage')->name('shipping.label');
    Route::post('/support/{ticket}/attachments', [SupportAttachmentController::class, 'admin'])->middleware('permission:customers.manage')->name('support.attachments');

    Route::prefix('providers')->name('providers.')->middleware('permission:settings.manage')->group(function (): void {
        Route::get('/', [ProviderSettingsController::class, 'index'])->name('index'); Route::post('/payment', [ProviderSettingsController::class, 'payment'])->name('payment'); Route::post('/sms', [ProviderSettingsController::class, 'sms'])->name('sms'); Route::post('/health', [ProviderSettingsController::class, 'health'])->name('health');
    });
    Route::prefix('operations-health')->name('operations-health.')->middleware('permission:security.manage')->group(function (): void {
        Route::get('/', [OperationsHealthController::class, 'index'])->name('index'); Route::post('/backup', [OperationsHealthController::class, 'backup'])->name('backup'); Route::post('/health', [OperationsHealthController::class, 'health'])->name('health');
    });
    Route::post('/payments/{transaction}/reconcile', [FinancialOperationsController::class, 'reconcile'])->middleware('permission:orders.refund')->name('payments.reconcile');
    Route::post('/payments/{transaction}/refund', [FinancialOperationsController::class, 'refund'])->middleware('permission:orders.refund')->name('payments.refund');
    Route::get('/reports/sales.xls', [OperationsController::class, 'salesExcel'])->middleware('permission:reports.export')->name('reports.excel');
    Route::get('/reports/summary.pdf', [OperationsController::class, 'reportPdf'])->middleware('permission:reports.export')->name('reports.pdf');
});
