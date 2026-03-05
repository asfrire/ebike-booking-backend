<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\RiderController;
use App\Http\Controllers\Api\FareController;
use Illuminate\Support\Facades\Route;

// Test route for debugging
Route::get('/test', function () {
    return response()->json([
        'message' => 'API is working!',
        'timestamp' => now(),
        'method' => 'GET'
    ]);
});

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    
    // Customer routes
    Route::middleware('role:customer')->group(function () {
        Route::get('/bookings', [BookingController::class, 'index']);
        Route::post('/bookings', [BookingController::class, 'store']);
        Route::get('/bookings/{booking}', [BookingController::class, 'show']);
        Route::put('/bookings/{booking}/cancel', [BookingController::class, 'cancel']);
    });
    
    // Rider routes
    Route::middleware('role:rider')->group(function () {
        Route::get('/rider/bookings', [RiderController::class, 'bookings']);
        Route::post('/rider/bookings/{booking}/accept', [RiderController::class, 'acceptBooking']);
        Route::post('/rider/bookings/{booking}/reject', [RiderController::class, 'rejectBooking']);
        Route::post('/rider/go-online', [RiderController::class, 'goOnline']);
        Route::post('/rider/go-offline', [RiderController::class, 'goOffline']);
        Route::get('/rider/queue', [RiderController::class, 'queuePosition']);
        Route::get('/rider/status', [RiderController::class, 'status']);
        Route::get('/rider/dashboard', [RiderController::class, 'dashboard']);
    });
    
    // Admin routes
    Route::middleware('role:admin')->group(function () {
        Route::get('/admin/bookings', [BookingController::class, 'adminIndex']);
        Route::get('/admin/bookings/{booking}', [BookingController::class, 'adminShow']);
        Route::put('/admin/bookings/{booking}/cancel', [BookingController::class, 'adminCancel']);
        Route::put('/admin/bookings/{booking}/complete', [BookingController::class, 'complete']);
        Route::get('/admin/riders', [RiderController::class, 'adminIndex']);
        Route::get('/admin/riders/{rider}', [RiderController::class, 'adminShow']);
        Route::put('/admin/riders/{rider}/capacity', [RiderController::class, 'updateCapacity']);
        Route::get('/admin/rider/{rider}/daily-report', [RiderController::class, 'adminDailyReport']);
        Route::get('/admin/rider/{rider}/monthly-report', [RiderController::class, 'adminMonthlyReport']);
        Route::get('/admin/all-riders-summary', [RiderController::class, 'adminAllRidersSummary']);
        
        // Fare management routes
        Route::get('/admin/fares', [FareController::class, 'index']);
        Route::post('/admin/fares', [FareController::class, 'store']);
        Route::get('/admin/fares/{fare}', [FareController::class, 'show']);
        Route::put('/admin/fares/{fare}', [FareController::class, 'update']);
        Route::delete('/admin/fares/{fare}', [FareController::class, 'destroy']);
        Route::get('/admin/subdivisions', [FareController::class, 'subdivisions']);
        Route::post('/admin/subdivisions', [FareController::class, 'storeSubdivision']);
        Route::get('/admin/phases', [FareController::class, 'phases']);
        Route::post('/admin/phases', [FareController::class, 'storePhase']);
        Route::post('/admin/preview-fare', [FareController::class, 'previewFare']);
    });
    
    // Common routes (check timeouts on any booking-related call)
    Route::middleware(['auth:sanctum', 'check.timeouts'])->group(function () {
        Route::get('/bookings/{booking}', [BookingController::class, 'show']);
        Route::get('/rider/bookings', [RiderController::class, 'bookings']);
        Route::get('/rider/status', [RiderController::class, 'status']);
    });
});
