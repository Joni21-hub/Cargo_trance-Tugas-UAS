<?php

use App\Http\Controllers\Api\ShipmentController;
use App\Http\Controllers\Api\ShipmentExceptionController;
use App\Http\Controllers\Api\TrackingEventController;
use App\Http\Controllers\Api\WeatherController;
use App\Models\Carrier;
use App\Models\Location;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // ---------------------------------------------------------
    // 1. PUBLIC & WORKFLOW ENDPOINTS (Operator, Shipper, Buyer)
    // ---------------------------------------------------------
    Route::get('/shipments', [ShipmentController::class, 'index']);
    Route::get('/shipments/{tracking_number}', [ShipmentController::class, 'show']);
    Route::get('/shipments/{id}/tracking', [ShipmentController::class, 'tracking']);
    Route::get('/shipments/{id}/exceptions', [ShipmentExceptionController::class, 'index']);

    // Operator: Input barang/kargo baru
    Route::post('/shipments', [ShipmentController::class, 'store']);

    // Shipper: Konfirmasi & setujui detail barang yang diisi oleh Operator
    Route::post('/shipments/{id}/confirm-shipper', [ShipmentController::class, 'confirmByShipper']);

    // Buyer: Setujui penerimaan pesanan (barang tiba)
    Route::post('/shipments/{id}/confirm-buyer', [ShipmentController::class, 'confirmReceiptByBuyer']);

    // Master data lookups
    Route::get('/locations', fn() => response()->json(['success' => true, 'data' => Location::all()]));
    Route::get('/carriers', fn() => response()->json(['success' => true, 'data' => Carrier::where('is_active', true)->get()]));

    // Public / Live Weather API (Open-Meteo)
    Route::get('/weather', [WeatherController::class, 'show']);

    // ---------------------------------------------------------
    // 2. OPERATOR & ADMIN PROTECTED ENDPOINTS
    // ---------------------------------------------------------
    Route::middleware(['auth', 'role:admin,operator'])->group(function () {
        Route::post('/admin/shipments', [ShipmentController::class, 'store']);
        Route::match(['put', 'patch'], '/shipments/{id}', [ShipmentController::class, 'update']);
        Route::delete('/shipments/{id}', [ShipmentController::class, 'destroy']);

        // Checkpoint & Exception Operations
        Route::post('/shipments/{id}/tracking', [TrackingEventController::class, 'store']);
        Route::post('/shipments/{id}/exceptions', [ShipmentExceptionController::class, 'store']);
        Route::post('/exceptions/{id}/resolve', [ShipmentExceptionController::class, 'resolve']);
    });
});
