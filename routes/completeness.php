<?php

use App\Http\Controllers\Admin\OrderCompletionController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->middleware('auth')->group(function (): void {
    Route::get('/orders-kanban', [OrderCompletionController::class, 'kanban'])->middleware('permission:orders.view')->name('orders.kanban');
    Route::post('/orders/phone', [OrderCompletionController::class, 'phone'])->middleware('permission:orders.manage')->name('orders.phone');
    Route::patch('/orders/bulk', [OrderCompletionController::class, 'bulk'])->middleware('permission:orders.manage')->name('orders.bulk');
    Route::get('/orders/{order}/packing-slip', [OrderCompletionController::class, 'packing'])->middleware('permission:orders.view')->name('orders.packing');
    Route::get('/orders/{order}/thermal-receipt', [OrderCompletionController::class, 'thermal'])->middleware('permission:orders.view')->name('orders.thermal');
    Route::get('/fulfillment/shipments/{shipment}/label', [OrderCompletionController::class, 'label'])->middleware('permission:orders.manage')->name('shipping.label');
});
