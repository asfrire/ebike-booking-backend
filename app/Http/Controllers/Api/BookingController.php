<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\User;
use App\Models\CustomerAddress;
use App\Models\Subdivision;
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
            'pickup_location' => 'required|string|max:255',
            'dropoff_location' => 'required|string|max:255',
            'pax' => 'required|integer|min:1|max:20', // Allow up to 20 passengers for multiple bikes
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Parse pickup_location to get subdivision, block, lot
        if (str_contains($request->pickup_location, 'Main Gate')) {
            $subdivision = Subdivision::where('name', 'Primera')->first();
            $block_number = '1';
            $lot_number = '1';
        } else {
            // Updated pattern to handle both "primera Subdivision" and just "primera"
            $pattern = '/^(\w+)(?: Subdivision)?, (.+), Block (\w+), Lot (\w+)$/';
            if (preg_match($pattern, $request->pickup_location, $matches)) {
                $subdivision_name = ucfirst(strtolower($matches[1]));
                $subdivision = Subdivision::where('name', $subdivision_name)->first();
                $block_number = $matches[3];
                $lot_number = $matches[4];
            } else {
                return response()->json(['message' => 'Invalid pickup location format: ' . $request->pickup_location], 422);
            }
        }

        if (!$subdivision) {
            return response()->json(['message' => 'Invalid subdivision'], 422);
        }

        $subdivision_id = $subdivision->id;

        // Process booking with fare calculation
        $fareResult = $this->bookingService->processBookingWithFare([
            'subdivision_id' => $subdivision_id,
            'block_number' => $block_number,
            'lot_number' => $lot_number,
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
            'block_number' => $block_number,
            'lot_number' => $lot_number,
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
        if ($booking->status === 'waiting' || $booking->status === 'on_ride') {
            return response()->json(['message' => 'Cannot cancel waiting or in-progress booking'], 422);
        }

        $booking->status = 'cancelled';
        $booking->save();

        // Cancel all rider assignments
        $booking->bookingRiders()->update(['status' => 'rejected']);

        return response()->json(['message' => 'Booking cancelled successfully']);
    }

    public function startRide(Request $request, Booking $booking)
    {
        // Only customer can start their own booking
        if ($booking->customer_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Only allow starting ride if status is waiting (rider assigned)
        if ($booking->status !== 'waiting') {
            return response()->json(['message' => 'Booking is not ready to start'], 422);
        }

        $booking->status = 'on_ride';
        $booking->save();

        return response()->json([
            'message' => 'Ride started successfully',
            'booking' => $booking->load(['bookingRiders.rider.user'])
        ]);
    }

    public function completeRide(Request $request, Booking $booking)
    {
        // Only customer can complete their own booking
        if ($booking->customer_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Only allow completion if status is on_ride
        if ($booking->status !== 'on_ride') {
            return response()->json(['message' => 'Booking is not on ride'], 422);
        }

        // Mark booking as completed
        $booking->status = 'completed';
        $booking->save();

        // Calculate and update earnings
        $this->bookingService->calculateEarnings($booking);

        return response()->json([
            'message' => 'Ride completed successfully',
            'booking' => $booking->load(['bookingRiders.rider.user'])
        ]);
    }

    // Admin methods
    public function adminIndex(Request $request)
    {
        $bookings = Booking::with(['customer', 'bookingRiders.rider.user'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($bookings);
    }

    public function adminStats(Request $request)
    {
        $totalUsers = User::count();
        $totalRiders = User::where('role', 'rider')->count();
        $totalBookings = Booking::count();
        $activeBookings = Booking::whereIn('status', ['pending', 'accepted', 'on_ride'])->count();
        $totalRevenue = Booking::where('status', 'completed')->sum('total_fare');

        return response()->json([
            'totalUsers' => $totalUsers,
            'totalRiders' => $totalRiders,
            'totalBookings' => $totalBookings,
            'activeBookings' => $activeBookings,
            'totalRevenue' => $totalRevenue,
        ]);
    }

    public function adminUsers(Request $request)
    {
        $users = User::with('riderQueue')->get();

        return response()->json($users);
    }

    public function getCustomerAddresses(Request $request)
    {
        $addresses = $request->user()->customerAddresses;

        return response()->json($addresses);
    }

    public function createCustomerAddress(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'subdivision' => 'required|in:primera,sonera',
            'street' => 'required|string|max:255',
            'block' => 'required|string|max:255',
            'lot' => 'required|string|max:255',
        ]);

        $validated['user_id'] = $user->id;

        $address = CustomerAddress::updateOrCreate(
            ['user_id' => $user->id],
            $validated
        );

        return response()->json([
            'success' => true,
            'data' => $address,
        ]);
    }

    public function adminShow(Request $request, Booking $booking)
    {
        $booking->load(['customer', 'bookingRiders.rider.user']);

        return response()->json($booking);
    }

    public function adminCancel(Request $request, Booking $booking)
    {
        if ($booking->status === 'done') {
            return response()->json(['message' => 'Cannot cancel done booking'], 422);
        }

        $booking->status = 'cancelled';
        $booking->save();

        // Cancel all rider assignments
        $booking->bookingRiders()->update(['status' => 'rejected']);

        return response()->json(['message' => 'Booking cancelled by admin']);
    }

    public function complete(Request $request, Booking $booking)
    {
        if ($booking->status === 'done') {
            return response()->json(['message' => 'Booking already done'], 422);
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
        $booking->status = 'done';
        $booking->save();

        // Calculate and update earnings
        $this->bookingService->calculateEarnings($booking);

        return response()->json([
            'message' => 'Booking completed successfully',
            'booking' => $booking->load(['bookingRiders.rider.user'])
        ]);
    }
}
