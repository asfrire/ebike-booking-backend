<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BookingController extends Controller
{
    protected $bookingService;

    public function __construct(BookingService $bookingService)
    {
        $this->bookingService = $bookingService;
    }

    public function index(Request $request)
    {
        $bookings = $request->user()->bookings()
            ->with(['bookingRiders.rider.user'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($bookings);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'subdivision_id' => 'required|exists:subdivisions,id',
            'block_number' => 'required|string|max:10',
            'lot_number' => 'required|string|max:10',
            'pickup_location' => 'required|string|max:255',
            'dropoff_location' => 'required|string|max:255',
            'pax' => 'required|integer|min:1|max:20', // Allow up to 20 passengers for multiple bikes
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Process booking with fare calculation
        $fareResult = $this->bookingService->processBookingWithFare([
            'subdivision_id' => $request->subdivision_id,
            'block_number' => $request->block_number,
            'lot_number' => $request->lot_number,
            'pax' => $request->pax,
        ]);

        if (!$fareResult['success']) {
            return response()->json(['message' => $fareResult['message']], 422);
        }

        // Create booking with fare information
        $booking = Booking::create([
            'customer_id' => $request->user()->id,
            'subdivision_id' => $fareResult['booking_data']['subdivision_id'],
            'phase_id' => $fareResult['booking_data']['phase_id'],
            'block_number' => $request->block_number,
            'lot_number' => $request->lot_number,
            'pickup_location' => $request->pickup_location,
            'dropoff_location' => $request->dropoff_location,
            'pax' => $request->pax,
            'remaining_pax' => $request->pax,
            'status' => 'pending',
            'fare_per_passenger' => $fareResult['fare_per_passenger'],
            'total_fare' => $fareResult['total_fare'],
        ]);

        // Assign riders using the service
        $this->bookingService->assignRiders($booking);

        // Reload with relationships
        $booking->load(['bookingRiders.rider.user']);

        return response()->json($booking, 201);
    }

    public function show(Request $request, Booking $booking)
    {
        // Check if user owns this booking or is admin/rider assigned
        if ($request->user()->role === 'customer' && $booking->customer_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $booking->load(['customer', 'bookingRiders.rider.user']);

        return response()->json($booking);
    }

    public function cancel(Request $request, Booking $booking)
    {
        // Only customer can cancel their own booking
        if ($booking->customer_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Only allow cancellation if not accepted
        if ($booking->status === 'accepted' || $booking->status === 'completed') {
            return response()->json(['message' => 'Cannot cancel accepted or completed booking'], 422);
        }

        $booking->status = 'cancelled';
        $booking->save();

        // Cancel all rider assignments
        $booking->bookingRiders()->update(['status' => 'rejected']);

        return response()->json(['message' => 'Booking cancelled successfully']);
    }

    // Admin methods
    public function adminIndex(Request $request)
    {
        $bookings = Booking::with(['customer', 'bookingRiders.rider.user'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($bookings);
    }

    public function adminShow(Request $request, Booking $booking)
    {
        $booking->load(['customer', 'bookingRiders.rider.user']);

        return response()->json($booking);
    }

    public function adminCancel(Request $request, Booking $booking)
    {
        if ($booking->status === 'completed') {
            return response()->json(['message' => 'Cannot cancel completed booking'], 422);
        }

        $booking->status = 'cancelled';
        $booking->save();

        // Cancel all rider assignments
        $booking->bookingRiders()->update(['status' => 'rejected']);

        return response()->json(['message' => 'Booking cancelled by admin']);
    }

    public function complete(Request $request, Booking $booking)
    {
        if ($booking->status === 'completed') {
            return response()->json(['message' => 'Booking already completed'], 422);
        }

        if ($booking->status === 'cancelled') {
            return response()->json(['message' => 'Cannot complete cancelled booking'], 422);
        }

        // Check if all assigned riders have accepted
        $acceptedRiders = $booking->bookingRiders()
            ->where('status', 'accepted')
            ->count();

        $totalAssigned = $booking->bookingRiders()
            ->whereIn('status', ['assigned', 'accepted'])
            ->count();

        if ($acceptedRiders === 0) {
            return response()->json(['message' => 'No riders have accepted this booking'], 422);
        }

        // Mark booking as completed
        $booking->status = 'completed';
        $booking->save();

        // Calculate and update earnings
        $this->bookingService->calculateEarnings($booking);

        return response()->json([
            'message' => 'Booking completed successfully',
            'booking' => $booking->load(['bookingRiders.rider.user'])
        ]);
    }
}
