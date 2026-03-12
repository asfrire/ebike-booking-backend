<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\RiderController;
use App\Http\Controllers\Api\FareController;
use Illuminate\Support\Facades\Route;

// Test route for admin bookings without auth
Route::get('/public/bookings', [BookingController::class, 'adminIndex']);

// Get online riders count
Route::get('/rider/status', [RiderController::class, 'getStatus'])->middleware('auth:sanctum');

// Get online riders count for customers
Route::get('/online-riders-count', [RiderController::class, 'getOnlineRidersCount'])->middleware('auth:sanctum');

// Get customer addresses
Route::get('/customer/addresses', [BookingController::class, 'getCustomerAddresses'])->middleware('auth:sanctum');
Route::post('/customer/addresses', [BookingController::class, 'createCustomerAddress'])->middleware('auth:sanctum');

// Get rider vehicles
Route::get('/rider/vehicles', [RiderController::class, 'getVehicles'])->middleware('auth:sanctum');

// Create rider vehicle
Route::post('/rider/vehicles', [RiderController::class, 'createVehicle'])->middleware('auth:sanctum');

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/test', function () {
    return response()->json(['message' => 'API is working']);
});

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
        Route::put('/bookings/{booking}/start-ride', [BookingController::class, 'startRide']);
        Route::put('/bookings/{booking}/complete', [BookingController::class, 'completeRide']);
    });
    
    // Shared routes for riders and customers (complete ride functionality)
    Route::middleware('auth:sanctum')->group(function () {
        Route::put('/rider/bookings/{booking}/status', [RiderController::class, 'updateBookingStatus']);
    });
    
    // Rider routes
    Route::middleware('role:rider')->group(function () {
        Route::get('/rider/bookings', [RiderController::class, 'bookings']);
        Route::put('/rider/bookings/{booking}/accept', [RiderController::class, 'acceptBooking']);
        Route::post('/rider/bookings/{booking}/reject', [RiderController::class, 'rejectBooking']);
        Route::post('/rider/go-online', [RiderController::class, 'goOnline']);
        Route::post('/rider/go-offline', [RiderController::class, 'goOffline']);
        Route::get('/rider/queue', [RiderController::class, 'queuePosition']);
        Route::get('/rider/status', [RiderController::class, 'status']);
        Route::get('/rider/dashboard', [RiderController::class, 'dashboard']);
    });
    
    // Admin routes
    Route::middleware('role:admin')->group(function () {
        Route::get('/admin/stats', [BookingController::class, 'adminStats']);
        Route::get('/admin/users', [BookingController::class, 'adminUsers']);
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
