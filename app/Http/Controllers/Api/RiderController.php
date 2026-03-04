<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Rider;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RiderController extends Controller
{
    protected $bookingService;

    public function __construct(BookingService $bookingService)
    {
        $this->bookingService = $bookingService;
    }

    public function bookings(Request $request)
    {
        $rider = $request->user()->rider;
        
        if (!$rider) {
            return response()->json(['message' => 'Rider profile not found'], 404);
        }

        $bookings = $rider->bookingRiders()
            ->with(['booking.customer', 'booking.bookingRiders.rider.user'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->pluck('booking');

        return response()->json($bookings);
    }

    public function acceptBooking(Request $request, Booking $booking)
    {
        $rider = $request->user()->rider;
        
        if (!$rider) {
            return response()->json(['message' => 'Rider profile not found'], 404);
        }

        $result = $this->bookingService->acceptBooking($rider, $booking);

        if ($result['success']) {
            // Send push notification to customer
            $this->sendCustomerNotification($booking, 'Rider accepted your booking');
            return response()->json(['message' => $result['message']]);
        } else {
            return response()->json(['message' => $result['message']], 422);
        }
    }

    public function rejectBooking(Request $request, Booking $booking)
    {
        $rider = $request->user()->rider;
        
        if (!$rider) {
            return response()->json(['message' => 'Rider profile not found'], 404);
        }

        $assignment = $rider->bookingRiders()
            ->where('booking_id', $booking->id)
            ->where('status', 'assigned')
            ->first();

        if (!$assignment) {
            return response()->json(['message' => 'No active assignment found'], 404);
        }

        // Mark as rejected
        $assignment->status = 'rejected';
        $assignment->save();

        // Return seats to booking and try reassignment
        $booking->remaining_pax += $assignment->allocated_seats;
        $booking->save();

        // Move rider to end of queue
        $this->bookingService->moveRiderToEndOfQueue($rider);

        // Try to reassign the rejected seats
        $this->bookingService->assignRiders($booking);

        return response()->json(['message' => 'Booking rejected']);
    }

    public function goOnline(Request $request)
    {
        $rider = $request->user()->rider;
        
        if (!$rider) {
            return response()->json(['message' => 'Rider profile not found'], 404);
        }

        if ($rider->is_online) {
            return response()->json(['message' => 'Already online'], 422);
        }

        $this->bookingService->goOnline($rider);

        return response()->json([
            'message' => 'Now online',
            'queue_position' => $rider->queue_position
        ]);
    }

    public function goOffline(Request $request)
    {
        $rider = $request->user()->rider;
        
        if (!$rider) {
            return response()->json(['message' => 'Rider profile not found'], 404);
        }

        if (!$rider->is_online) {
            return response()->json(['message' => 'Already offline'], 422);
        }

        // Check if rider has active assignments
        $activeAssignments = $rider->bookingRiders()
            ->whereIn('status', ['assigned', 'accepted'])
            ->count();

        if ($activeAssignments > 0) {
            return response()->json(['message' => 'Cannot go offline with active assignments'], 422);
        }

        $this->bookingService->goOffline($rider);

        return response()->json(['message' => 'Now offline']);
    }

    public function queuePosition(Request $request)
    {
        $rider = $request->user()->rider;
        
        if (!$rider) {
            return response()->json(['message' => 'Rider profile not found'], 404);
        }

        $ridersAhead = Rider::online()
            ->where('queue_position', '<', $rider->queue_position)
            ->count();

        return response()->json([
            'queue_position' => $rider->queue_position,
            'riders_ahead' => $ridersAhead,
            'is_online' => $rider->is_online
        ]);
    }

    public function status(Request $request)
    {
        $rider = $request->user()->rider;
        
        if (!$rider) {
            return response()->json(['message' => 'Rider profile not found'], 404);
        }

        $activeAssignments = $rider->bookingRiders()
            ->with(['booking.customer'])
            ->whereIn('status', ['assigned', 'accepted'])
            ->get();

        return response()->json([
            'is_online' => $rider->is_online,
            'queue_position' => $rider->queue_position,
            'capacity' => $rider->capacity,
            'active_assignments' => $activeAssignments,
            'is_available' => $rider->isAvailable()
        ]);
    }

    // Admin methods
    public function adminIndex(Request $request)
    {
        $riders = Rider::with(['user', 'bookingRiders.booking'])
            ->orderBy('is_online', 'desc')
            ->orderBy('queue_position', 'asc')
            ->get();

        return response()->json($riders);
    }

    public function adminShow(Request $request, Rider $rider)
    {
        $rider->load(['user', 'bookingRiders.booking.customer']);

        return response()->json($rider);
    }

    public function updateCapacity(Request $request, Rider $rider)
    {
        $validator = Validator::make($request->all(), [
            'capacity' => 'required|integer|min:2|max:5',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check if rider has active assignments
        $activeAssignments = $rider->bookingRiders()
            ->whereIn('status', ['assigned', 'accepted'])
            ->count();

        if ($activeAssignments > 0) {
            return response()->json(['message' => 'Cannot update capacity with active assignments'], 422);
        }

        $rider->capacity = $request->capacity;
        $rider->save();

        return response()->json(['message' => 'Capacity updated successfully']);
    }

    private function sendCustomerNotification(Booking $booking, $message)
    {
        // TODO: Implement FCM push notification
        \Log::info("Customer notification: {$message} for booking {$booking->id}");
    }
}
