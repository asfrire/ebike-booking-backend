<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\RiderController;
use Illuminate\Support\Facades\Route;

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
    });
    
    // Admin routes
    Route::middleware('role:admin')->group(function () {
        Route::get('/admin/bookings', [BookingController::class, 'adminIndex']);
        Route::get('/admin/bookings/{booking}', [BookingController::class, 'adminShow']);
        Route::put('/admin/bookings/{booking}/cancel', [BookingController::class, 'adminCancel']);
        Route::get('/admin/riders', [RiderController::class, 'adminIndex']);
        Route::get('/admin/riders/{rider}', [RiderController::class, 'adminShow']);
        Route::put('/admin/riders/{rider}/capacity', [RiderController::class, 'updateCapacity']);
    });
    
    // Common routes (check timeouts on any booking-related call)
    Route::middleware(['auth:sanctum', 'check.timeouts'])->group(function () {
        Route::get('/bookings/{booking}', [BookingController::class, 'show']);
        Route::get('/rider/bookings', [RiderController::class, 'bookings']);
        Route::get('/rider/status', [RiderController::class, 'status']);
    });
});
