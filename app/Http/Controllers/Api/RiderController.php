<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RiderQueue;
use App\Models\Vehicle;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;

class RiderController extends Controller
{
    protected $bookingService;

    public function __construct(BookingService $bookingService)
    {
        $this->bookingService = $bookingService;
    }

    public function bookings(Request $request)
    {
        $rider = $request->user()->riderQueue;
        
        if (!$rider) {
            return response()->json(['message' => 'Rider profile not found'], 404);
        }

        // Get all active bookings in the system (pending, waiting, on_ride)
        $bookings = Booking::with(['customer', 'bookingRiders.rider.user'])
            ->whereIn('status', ['pending', 'waiting', 'on_ride'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($bookings);
    }

    public function acceptBooking(Request $request, Booking $booking)
    {
        \Log::info('Accept booking request', [
            'user_id' => $request->user()->id ?? 'no user',
            'booking_id' => $booking->id,
            'booking_status' => $booking->status
        ]);

        $rider = $request->user()->riderQueue;
        
        // Auto-create rider profile if it doesn't exist
        if (!$rider) {
            $rider = RiderQueue::create([
                'rider_id' => $request->user()->id,
            ]);
            \Log::info('Created new riderQueue', ['rider_id' => $rider->id]);
        }

        \Log::info('Rider check', [
            'rider_exists' => $rider ? 'yes' : 'no',
            'rider_id' => $rider->id ?? 'no rider'
        ]);

        $result = $this->bookingService->acceptBooking($rider, $booking);

        \Log::info('Booking service result', [
            'success' => $result['success'],
            'message' => $result['message']
        ]);

        if ($result['success']) {
            return response()->json(['message' => $result['message']]);
        } else {
            \Log::error('Booking acceptance failed', [
                'rider_id' => $rider->id,
                'booking_id' => $booking->id,
                'error' => $result['message']
            ]);
            return response()->json(['message' => $result['message']], 422);
        }
    }

    public function updateBookingStatus(Request $request, Booking $booking)
    {
        $request->validate([
            'status' => 'required|string|in:waiting,on_ride,completed'
        ]);

        $user = $request->user();

        // Check authorization based on user role
        if ($user->isRider()) {
            // Rider authorization: must be assigned to this booking
            $rider = $request->user()->riderQueue;

            if (!$rider) {
                return response()->json(['message' => 'Rider profile not found'], 404);
            }

            if ($booking->rider_id !== $rider->rider_id) {
                return response()->json(['message' => 'You are not assigned to this booking'], 403);
            }
        } elseif ($user->isCustomer()) {
            // Customer authorization: must own this booking
            if ($booking->customer_id !== $user->id) {
                return response()->json(['message' => 'You do not own this booking'], 403);
            }

            // Customer can only complete rides that are on_ride
            if ($request->status === 'completed' && $booking->status !== 'on_ride') {
                return response()->json(['message' => 'Can only complete rides that are in progress'], 403);
            }
        } else {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Update the booking status
        $booking->status = $request->status;
        $booking->save();

        // Set rider status to on duty if starting ride
        if ($request->status === 'on_ride' && $user->isRider()) {
            $rider = $request->user()->riderQueue;
            if ($rider) {
                $rider->status = 'on_duty';
                $rider->save();
            }
        }

        // Calculate earnings for completed rides
        if ($request->status === 'completed') {
            $this->calculateEarnings($booking);
        }

        return response()->json(['message' => 'Booking status updated successfully']);
    }

    private function calculateEarnings(Booking $booking)
    {
        $this->bookingService->calculateEarnings($booking);
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
        $user = $request->user();
        $riderQueue = $user->riderQueue;
        
        if (!$riderQueue) {
            $riderQueue = RiderQueue::create([
                'rider_id' => $user->id,
            ]);
        }

        // Edge case: Auto-close any existing active session
        $this->bookingService->closeActiveSession($riderQueue);

        if ($riderQueue->is_online) {
            return response()->json(['message' => 'Already online'], 422);
        }

        $mode = $request->input('mode', 'stand_by');
        $riderQueue->is_online = true;
        $riderQueue->status = 'open';
        
        // Calculate proper queue position based on existing online riders
        $riderQueue->queue_position = RiderQueue::where('is_online', true)->count() + 1;
        
        $riderQueue->save();
        
        // Start rider session
        $session = $this->bookingService->startRiderSession($riderQueue);

        // Emit WebSocket event for real-time updates
        try {
            Http::post('http://localhost:6004/emit');
        } catch (\Exception $e) {
            // WebSocket server not running, skip
        }

        return response()->json([
            'message' => 'Now online',
            'queue_position' => $riderQueue->queue_position,
            'mode' => $mode,
            'session_id' => $session->id,
            'time_in' => $session->time_in,
        ]);
    }

    public function goOffline(Request $request)
    {
        $user = $request->user();
        $riderQueue = $user->riderQueue;
        
        if (!$riderQueue) {
            return response()->json(['message' => 'Rider profile not found'], 404);
        }

        if (!$riderQueue->is_online) {
            return response()->json(['message' => 'Already offline'], 422);
        }

        // Check if rider has active assignments
        $activeAssignments = $riderQueue->bookingRiders()
            ->whereIn('status', ['assigned', 'accepted'])
            ->count();

        if ($activeAssignments > 0) {
            return response()->json(['message' => 'Cannot go offline with active assignments'], 422);
        }

        $riderQueue->is_online = false;
        $riderQueue->queue_position = null;
        $riderQueue->status = null;
        $riderQueue->save();
        
        // End rider session
        $session = $this->bookingService->endRiderSession($riderQueue);

        // Emit WebSocket event for real-time updates
        try {
            Http::post('http://localhost:6004/emit');
        } catch (\Exception $e) {
            // WebSocket server not running, skip
        }

        return response()->json([
            'message' => 'Now offline',
            'session_id' => $session ? $session->id : null,
            'total_minutes' => $session ? $session->total_minutes : null,
        ]);
    }

    public function queuePosition(Request $request)
    {
        $user = $request->user();
        $riderQueue = $user->riderQueue;
        
        if (!$riderQueue) {
            return response()->json(['message' => 'Rider profile not found'], 404);
        }

        return response()->json([
            'queue_position' => 1, // Simplified - always position 1
        ]);
    }

    public function status(Request $request)
    {
        $user = $request->user();
        $riderQueue = $user->riderQueue;
        
        if (!$riderQueue) {
            return response()->json(['message' => 'Rider profile not found'], 404);
        }

        $activeAssignments = \App\Models\Booking::where('rider_id', $riderQueue->rider_id)
            ->whereIn('status', ['waiting', 'on_ride'])
            ->with('customer')
            ->get();

        return response()->json([
            'is_online' => $riderQueue->is_online,
            'queue_position' => $riderQueue->queue_position,
            'capacity' => $riderQueue->user->vehicles->sum('capacity'),
            'active_assignments' => $activeAssignments,
            'is_available' => $riderQueue->isAvailable()
        ]);
    }

    public function getOnlineRidersCount()
    {
        $count = RiderQueue::where('is_online', true)->count();

        return response()->json([
            'online_riders' => $count
        ]);
    }

    public function getStatus(Request $request)
    {
        $user = $request->user();
        $riderQueue = $user->riderQueue;
        
        if (!$riderQueue) {
            return response()->json(['message' => 'Rider profile not found'], 404);
        }

        return response()->json([
            'is_online' => $riderQueue->is_online,
            'queue_position' => $riderQueue->queue_position,
        ]);
    }

    public function getVehicles(Request $request)
    {
        $user = $request->user();
        $riderQueue = $user->riderQueue;
        
        if (!$riderQueue) {
            return response()->json(['message' => 'Rider profile not found'], 404);
        }

        $vehicles = $riderQueue->user->vehicles;
        return response()->json($vehicles);
    }

    public function createVehicle(Request $request)
    {
        $user = $request->user();
        $riderQueue = $user->riderQueue;
        
        if (!$riderQueue) {
            return response()->json(['message' => 'Rider profile not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'model' => 'required|string|max:255',
            'color' => 'required|string|max:255',
            'plate_number' => 'required|string|max:255',
            'capacity' => 'required|integer|min:1|max:10',
            'appearance_notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $vehicle = Vehicle::create([
            'rider_id' => $riderQueue->rider_id,
            'model' => $request->model,
            'color' => $request->color,
            'plate_number' => $request->plate_number,
            'capacity' => $request->capacity,
            'appearance_notes' => $request->appearance_notes,
        ]);

        return response()->json($vehicle, 201);
    }

    public function dashboard(Request $request)
    {
        $user = $request->user();
        $riderQueue = $user->riderQueue;
        
        if (!$riderQueue) {
            return response()->json(['message' => 'Rider profile not found'], 404);
        }

        // Get today's statistics
        $todayStats = $this->bookingService->getRiderDailyStats($riderQueue);

        // Get current session info
        $activeSession = $riderQueue->activeSession()->first();
        $currentSessionMinutes = 0;
        
        if ($activeSession) {
            $currentSessionMinutes = now()->diffInMinutes($activeSession->time_in);
        }

        return response()->json([
            'today_rides' => $todayStats['rides'],
            'today_earnings' => $todayStats['earnings'],
            'today_online_minutes' => $todayStats['online_minutes'],
            'today_online_hours' => round($todayStats['online_hours'], 2),
            'current_status' => $riderQueue->is_online ? 'online' : 'offline',
            'queue_position' => $riderQueue->queue_position,
            'current_session_minutes' => $currentSessionMinutes,
            'is_available' => $riderQueue->isAvailable(),
            'active_assignments_count' => \App\Models\Booking::where('rider_id', $riderQueue->rider_id)
                ->whereIn('status', ['waiting', 'on_ride'])
                ->count(),
        ]);
    }

    // Admin methods
    public function adminIndex(Request $request)
    {
        $riders = RiderQueue::with(['user', 'bookingRiders.booking'])
            ->orderBy('is_online', 'desc')
            ->orderByRaw("CASE WHEN queue_position = 'stand by' THEN 99999 ELSE CAST(queue_position AS INTEGER) END ASC")
            ->get();

        return response()->json($riders);
    }

    public function adminShow(Request $request, RiderQueue $riderQueue)
    {
        $riderQueue->load(['user', 'bookingRiders.booking.customer']);

        return response()->json($riderQueue);
    }

    public function updateCapacity(Request $request, RiderQueue $riderQueue)
    {
        $validator = Validator::make($request->all(), [
            'capacity' => 'required|integer|min:2|max:5',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check if rider has active assignments
        $activeAssignments = $riderQueue->bookingRiders()
            ->whereIn('status', ['assigned', 'accepted'])
            ->count();

        if ($activeAssignments > 0) {
            return response()->json(['message' => 'Cannot update capacity with active assignments'], 422);
        }

        // Note: Capacity is now on vehicles, not on rider
        // Perhaps update the first vehicle or something, but for now, skip
        // $riderQueue->capacity = $request->capacity;
        // $riderQueue->save();

        return response()->json(['message' => 'Capacity update not implemented, use vehicle management']);
    }

    public function adminDailyReport(Request $request, RiderQueue $riderQueue)
    {
        $date = $request->get('date', now()->toDateString());
        
        $stats = $this->bookingService->getRiderDailyStats($riderQueue, $date);
        
        return response()->json([
            'rider' => [
                'id' => $riderQueue->id,
                'name' => $riderQueue->user->name,
                'email' => $riderQueue->user->email,
            ],
            'date' => $date,
            'rides' => $stats['rides'],
            'earnings' => $stats['earnings'],
            'online_minutes' => $stats['online_minutes'],
            'online_hours' => round($stats['online_hours'], 2),
            'sessions' => $riderQueue->riderSessions()
                ->whereDate('time_in', $date)
                ->get()
                ->map(function ($session) {
                    return [
                        'time_in' => $session->time_in,
                        'time_out' => $session->time_out,
                        'total_minutes' => $session->total_minutes,
                        'formatted_duration' => $session->formatted_duration,
                    ];
                }),
        ]);
    }

    public function adminMonthlyReport(Request $request, RiderQueue $riderQueue)
    {
        $month = $request->get('month', now()->format('Y-m'));
        $startDate = $month . '-01';
        $endDate = now()->parse($startDate)->endOfMonth()->toDateString();
        
        // Get monthly stats
        $monthlyRides = $riderQueue->bookingRiders()
            ->where('status', 'completed')
            ->whereDate('updated_at', '>=', $startDate)
            ->whereDate('updated_at', '<=', $endDate)
            ->count();

        $monthlyEarnings = $riderQueue->bookingRiders()
            ->where('status', 'completed')
            ->whereDate('updated_at', '>=', $startDate)
            ->whereDate('updated_at', '<=', $endDate)
            ->sum('earning_amount');

        $monthlyOnlineMinutes = $riderQueue->riderSessions()
            ->whereDate('time_in', '>=', $startDate)
            ->whereDate('time_in', '<=', $endDate)
            ->sum('total_minutes');

        // Daily breakdown
        $dailyStats = [];
        $currentDate = now()->parse($startDate);
        $endOfMonth = now()->parse($endDate);
        
        while ($currentDate <= $endOfMonth) {
            $date = $currentDate->toDateString();
            $dailyStats[$date] = $this->bookingService->getRiderDailyStats($riderQueue, $date);
            $currentDate->addDay();
        }

        return response()->json([
            'rider' => [
                'id' => $riderQueue->id,
                'name' => $riderQueue->user->name,
                'email' => $riderQueue->user->email,
            ],
            'month' => $month,
            'summary' => [
                'total_rides' => $monthlyRides,
                'total_earnings' => $monthlyEarnings,
                'total_online_minutes' => $monthlyOnlineMinutes,
                'total_online_hours' => round($monthlyOnlineMinutes / 60, 2),
                'average_rides_per_day' => round($monthlyRides / $currentDate->day, 2),
                'average_earnings_per_day' => round($monthlyEarnings / $currentDate->day, 2),
            ],
            'daily_breakdown' => $dailyStats,
        ]);
    }

    public function adminAllRidersSummary(Request $request)
    {
        $date = $request->get('date', now()->toDateString());
        
        $riders = RiderQueue::with('user')->get();
        
        $summary = $riders->map(function ($riderQueue) use ($date) {
            $stats = $this->bookingService->getRiderDailyStats($riderQueue, $date);
            
            return [
                'id' => $riderQueue->id,
                'name' => $riderQueue->user->name,
                'email' => $riderQueue->user->email,
                'is_online' => $riderQueue->is_online,
                'queue_position' => $riderQueue->queue_position,
                'rides' => $stats['rides'],
                'earnings' => $stats['earnings'],
                'online_minutes' => $stats['online_minutes'],
                'online_hours' => round($stats['online_hours'], 2),
            ];
        });

        // Totals
        $totalRiders = $riders->count();
        $onlineRiders = $riders->where('is_online', true)->count();
        $totalRides = $summary->sum('rides');
        $totalEarnings = $summary->sum('earnings');
        $totalOnlineMinutes = $summary->sum('online_minutes');

        return response()->json([
            'date' => $date,
            'summary' => [
                'total_riders' => $totalRiders,
                'online_riders' => $onlineRiders,
                'offline_riders' => $totalRiders - $onlineRiders,
                'total_rides' => $totalRides,
                'total_earnings' => $totalEarnings,
                'total_online_hours' => round($totalOnlineMinutes / 60, 2),
                'average_rides_per_rider' => $totalRiders > 0 ? round($totalRides / $totalRiders, 2) : 0,
                'average_earnings_per_rider' => $totalRiders > 0 ? round($totalEarnings / $totalRiders, 2) : 0,
            ]
        ]);
    }
}
