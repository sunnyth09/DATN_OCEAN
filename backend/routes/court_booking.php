<?php

use App\Http\Controllers\Api\Admin\CourtAdminController;
use App\Http\Controllers\Api\Admin\CourtBookingAdminController;
use App\Http\Controllers\Api\Admin\CourtMaintenanceAdminController;
use App\Http\Controllers\Api\Admin\CourtPriceAdminController;
use App\Http\Controllers\Api\Admin\CourtScheduleAdminController;
use App\Http\Controllers\Api\Admin\CourtServiceAdminController;
use App\Http\Controllers\Api\CourtBookingController;
use App\Http\Controllers\Api\CourtController;
use Illuminate\Support\Facades\Route;

// PUBLIC & USER ROUTES
Route::prefix('court-bookings')->group(function () {
    Route::get('/courts', [CourtController::class, 'index']);
    Route::get('/courts/{id}', [CourtController::class, 'show']);
    Route::get('/courts/{id}/availability', [CourtController::class, 'availability']);

    Route::middleware('auth:api,admin')->group(function () {
        Route::post('/bookings/lock', [CourtBookingController::class, 'lock']);
        Route::post('/bookings', [CourtBookingController::class, 'store']);
        Route::get('/bookings', [CourtBookingController::class, 'index']);
        Route::get('/bookings/{id}', [CourtBookingController::class, 'show']);
        Route::post('/bookings/{id}/cancel', [CourtBookingController::class, 'cancel']);
    });
});

// ADMIN ROUTES
Route::middleware(['auth:api,admin', 'role:admin,staff'])->prefix('admin/court-bookings')->group(function () {
    // Courts Management
    Route::post('courts/upload-image', [CourtAdminController::class, 'uploadImage']);
    Route::apiResource('courts', CourtAdminController::class);
    Route::apiResource('court-schedules', CourtScheduleAdminController::class);
    Route::apiResource('court-prices', CourtPriceAdminController::class);
    Route::post('court-services/upload-image', [CourtServiceAdminController::class, 'uploadImage']);
    Route::apiResource('court-services', CourtServiceAdminController::class);
    Route::apiResource('court-maintenances', CourtMaintenanceAdminController::class);

    // Bookings Management
    Route::apiResource('bookings', CourtBookingAdminController::class);
    Route::post('/bookings/{id}/check-in', [CourtBookingAdminController::class, 'checkIn']);
    Route::post('/bookings/{id}/check-out', [CourtBookingAdminController::class, 'checkOut']);
    Route::post('/bookings/{id}/services', [CourtBookingAdminController::class, 'addService']);
    Route::post('/bookings/{id}/extend', [CourtBookingAdminController::class, 'extend']);
});
