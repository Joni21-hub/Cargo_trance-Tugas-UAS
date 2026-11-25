<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DemoTimeTravelController;
use App\Http\Controllers\Api\EscrowWalletController;
use App\Http\Controllers\Api\ExportProductController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PickupRequestController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ShipmentController;
use App\Http\Controllers\Api\ShipmentExceptionController;
use App\Http\Controllers\Api\SupportTicketController;
use App\Http\Controllers\Api\TrackingEventController;
use App\Http\Controllers\Api\WeatherController;
use App\Models\Carrier;
use App\Models\Location;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // ---------------------------------------------------------
    // 0. AUTHENTICATION, PUBLIC DIRECTORY & BANTUAN ADMIN
    // ---------------------------------------------------------
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Platform Bootstrap & Live Metrics
    Route::get('/bootstrap', [DashboardController::class, 'bootstrap']);

    // Sanitized Public Tracking Lookup
    Route::get('/public/track/{tracking_number}', [ShipmentController::class, 'publicTrack']);

    // Nusantara Coffee Directory (Non-Marketplace, No Price) & Branch Operator Directory
    Route::get('/export-products', [ExportProductController::class, 'index']);
    Route::get('/operators', [ProfileController::class, 'operators']);
    Route::get('/users/{id}/public-profile', [ProfileController::class, 'publicProfile']);

    // Bantuan Admin (Accessible by public guests AND logged-in users)
    Route::post('/support-tickets', [SupportTicketController::class, 'store']);

    // ---------------------------------------------------------
    // 1. SHIPMENT & WORKFLOW ENDPOINTS
    // ---------------------------------------------------------
    Route::get('/shipments', [ShipmentController::class, 'index']);
    Route::get('/shipments/{tracking_number}', [ShipmentController::class, 'show']);
    Route::get('/shipments/{id}/tracking', [ShipmentController::class, 'tracking']);
    Route::get('/shipments/{id}/exceptions', [ShipmentExceptionController::class, 'index']);

    // Operator: Register official shipment
    Route::post('/shipments', [ShipmentController::class, 'store']);

    // Shipper & Buyer Workflow Confirmations
    Route::post('/shipments/{id}/confirm-shipper', [ShipmentController::class, 'confirmByShipper']);
    Route::post('/shipments/{id}/confirm-buyer', [ShipmentController::class, 'confirmReceiptByBuyer']);

    // Master data lookups
    Route::get('/locations', fn() => response()->json(['success' => true, 'data' => Location::orderBy('island')->orderBy('name')->get()]));
    Route::get('/carriers', fn() => response()->json(['success' => true, 'data' => Carrier::where('is_active', true)->orderBy('name')->get()]));

    // Public / Live Weather API (Open-Meteo)
    Route::get('/weather', [WeatherController::class, 'show']);

    // ---------------------------------------------------------
    // 2. AUTHENTICATED USER ENDPOINTS (PROFILE, CHAT, PICKUP, PRODUCTS, NOTIF, WALLET)
    // ---------------------------------------------------------
    Route::middleware(['auth'])->group(function () {
        // User Complete Identity Profile
        Route::get('/profile', [ProfileController::class, 'show']);
        Route::match(['put', 'patch'], '/profile', [ProfileController::class, 'update']);

        // Export Coffee Directory Management (Shipper & Admin)
        Route::post('/export-products', [ExportProductController::class, 'store']);
        Route::match(['put', 'patch'], '/export-products/{id}', [ExportProductController::class, 'update']);
        Route::delete('/export-products/{id}', [ExportProductController::class, 'destroy']);

        // Shipper "Buat Pengiriman" -> Call Branch Operator for physical inspection
        Route::get('/pickup-requests', [PickupRequestController::class, 'index']);
        Route::post('/pickup-requests', [PickupRequestController::class, 'store']);
        Route::patch('/pickup-requests/{id}/cancel', [PickupRequestController::class, 'cancel']);

        // Digital Release Document (Operator, Admin, or Shipper owner)
        Route::post('/shipments/{id}/release-document', [ShipmentController::class, 'issueReleaseDocument']);

        // Direct Chat (Buyer <-> Shipper <-> Operator)
        Route::get('/chats/threads', [ChatController::class, 'threads']);
        Route::get('/chats/messages', [ChatController::class, 'messages']);
        Route::post('/chats/messages', [ChatController::class, 'store']);

        // Support Tickets History
        Route::get('/support-tickets', [SupportTicketController::class, 'index']);

        // In-App Notifications
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);

        // Virtual Wallet & Escrow (Rekening Jaminan)
        Route::get('/wallet', [EscrowWalletController::class, 'index']);
        Route::post('/wallet/topup', [EscrowWalletController::class, 'topUp']);
        Route::post('/wallet/withdraw', [EscrowWalletController::class, 'withdraw']);
        Route::post('/shipments/{id}/escrow/deposit', [EscrowWalletController::class, 'deposit']);
        Route::post('/shipments/{id}/escrow/dispute', [EscrowWalletController::class, 'dispute']);
    });

    // ---------------------------------------------------------
    // 3. OPERATOR & ADMIN PROTECTED ENDPOINTS (RBAC)
    // ---------------------------------------------------------
    Route::middleware(['auth', 'role:admin,operator'])->group(function () {
        Route::post('/admin/shipments', [ShipmentController::class, 'store']);
        Route::match(['put', 'patch'], '/shipments/{id}', [ShipmentController::class, 'update']);
        Route::delete('/shipments/{id}', [ShipmentController::class, 'destroy']);

        // Dispatch internal branch land truck to Shipper's location
        Route::patch('/pickup-requests/{id}/dispatch', [PickupRequestController::class, 'dispatchTruck']);

        // Checkpoint & Exception Operations
        Route::post('/shipments/{id}/tracking', [TrackingEventController::class, 'store']);
        Route::post('/shipments/{id}/exceptions', [ShipmentExceptionController::class, 'store']);
        Route::post('/exceptions/{id}/investigate', [ShipmentExceptionController::class, 'investigate']);
        Route::post('/exceptions/{id}/resolve', [ShipmentExceptionController::class, 'resolve']);

        // Demo Time Travel Utility (+1 Hari / Besok)
        Route::post('/demo/time-travel', [DemoTimeTravelController::class, 'advance']);

        // Audit Trail Oversight
        Route::get('/audit-logs', [DashboardController::class, 'auditLogs']);
    });

    // ---------------------------------------------------------
    // 4. ADMINISTRATOR EXCLUSIVE MANAGEMENT ENDPOINTS
    // ---------------------------------------------------------
    Route::middleware(['auth', 'role:admin'])->prefix('admin')->group(function () {
        Route::get('/overview', [AdminController::class, 'overview']);
        Route::get('/users', [AdminController::class, 'users']);
        Route::post('/users', [AdminController::class, 'storeUser']);
        Route::match(['put', 'patch'], '/users/{id}', [AdminController::class, 'updateUser']);
        Route::patch('/users/{id}/role', [AdminController::class, 'updateUserRole']);
        Route::patch('/users/{id}/verify', [AdminController::class, 'toggleVerifyUser']);
        Route::get('/carriers', [AdminController::class, 'carriers']);
        Route::post('/carriers', [AdminController::class, 'storeCarrier']);
        Route::match(['put', 'patch'], '/carriers/{id}', [AdminController::class, 'updateCarrier']);
        Route::patch('/carriers/{id}/toggle', [AdminController::class, 'toggleCarrier']);
        Route::post('/locations', [AdminController::class, 'storeLocation']);
        Route::match(['put', 'patch'], '/locations/{id}', [AdminController::class, 'updateLocation']);
        Route::post('/support-tickets/{id}/reply', [SupportTicketController::class, 'reply']);
    });
});
