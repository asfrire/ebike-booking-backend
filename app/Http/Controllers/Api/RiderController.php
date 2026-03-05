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

        // Edge case: Auto-close any existing active session
        $this->bookingService->closeActiveSession($rider);

        if ($rider->is_online) {
            return response()->json(['message' => 'Already online'], 422);
        }

        $this->bookingService->goOnline($rider);
        
        // Start rider session
        $session = $this->bookingService->startRiderSession($rider);

        return response()->json([
            'message' => 'Now online',
            'queue_position' => $rider->queue_position,
            'session_id' => $session->id,
            'time_in' => $session->time_in,
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
        
        // End rider session
        $session = $this->bookingService->endRiderSession($rider);

        return response()->json([
            'message' => 'Now offline',
            'session_id' => $session ? $session->id : null,
            'total_minutes' => $session ? $session->total_minutes : null,
        ]);
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

    public function dashboard(Request $request)
    {
        $rider = $request->user()->rider;
        
        if (!$rider) {
            return response()->json(['message' => 'Rider profile not found'], 404);
        }

        // Get today's statistics
        $todayStats = $this->bookingService->getRiderDailyStats($rider);

        // Get current session info
        $activeSession = $rider->activeSession()->first();
        $currentSessionMinutes = 0;
        
        if ($activeSession) {
            $currentSessionMinutes = now()->diffInMinutes($activeSession->time_in);
        }

        return response()->json([
            'today_rides' => $todayStats['rides'],
            'today_earnings' => $todayStats['earnings'],
            'today_online_minutes' => $todayStats['online_minutes'],
            'today_online_hours' => round($todayStats['online_hours'], 2),
            'current_status' => $rider->is_online ? 'online' : 'offline',
            'queue_position' => $rider->queue_position,
            'current_session_minutes' => $currentSessionMinutes,
            'is_available' => $rider->isAvailable(),
            'active_assignments_count' => $rider->bookingRiders()
                ->whereIn('status', ['assigned', 'accepted'])
                ->count(),
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

    public function adminDailyReport(Request $request, Rider $rider)
    {
        $date = $request->get('date', now()->toDateString());
        
        $stats = $this->bookingService->getRiderDailyStats($rider, $date);
        
        return response()->json([
            'rider' => [
                'id' => $rider->id,
                'name' => $rider->user->name,
                'email' => $rider->user->email,
            ],
            'date' => $date,
            'rides' => $stats['rides'],
            'earnings' => $stats['earnings'],
            'online_minutes' => $stats['online_minutes'],
            'online_hours' => round($stats['online_hours'], 2),
            'sessions' => $rider->riderSessions()
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

    public function adminMonthlyReport(Request $request, Rider $rider)
    {
        $month = $request->get('month', now()->format('Y-m'));
        $startDate = $month . '-01';
        $endDate = now()->parse($startDate)->endOfMonth()->toDateString();
        
        // Get monthly stats
        $monthlyRides = $rider->bookingRiders()
            ->where('status', 'completed')
            ->whereDate('updated_at', '>=', $startDate)
            ->whereDate('updated_at', '<=', $endDate)
            ->count();

        $monthlyEarnings = $rider->bookingRiders()
            ->where('status', 'completed')
            ->whereDate('updated_at', '>=', $startDate)
            ->whereDate('updated_at', '<=', $endDate)
            ->sum('earning_amount');

        $monthlyOnlineMinutes = $rider->riderSessions()
            ->whereDate('time_in', '>=', $startDate)
            ->whereDate('time_in', '<=', $endDate)
            ->sum('total_minutes');

        // Daily breakdown
        $dailyStats = [];
        $currentDate = now()->parse($startDate);
        $endOfMonth = now()->parse($endDate);
        
        while ($currentDate <= $endOfMonth) {
            $date = $currentDate->toDateString();
            $dailyStats[$date] = $this->bookingService->getRiderDailyStats($rider, $date);
            $currentDate->addDay();
        }

        return response()->json([
            'rider' => [
                'id' => $rider->id,
                'name' => $rider->user->name,
                'email' => $rider->user->email,
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
        
        $riders = Rider::with('user')->get();
        
        $summary = $riders->map(function ($rider) use ($date) {
            $stats = $this->bookingService->getRiderDailyStats($rider, $date);
            
            return [
                'id' => $rider->id,
                'name' => $rider->user->name,
                'email' => $rider->user->email,
                'is_online' => $rider->is_online,
                'queue_position' => $rider->queue_position,
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
            ],
            'riders' => $summary->sortByDesc('earnings')->values(),
        ]);
    }

    private function sendCustomerNotification(Booking $booking, $message)
    {
        // TODO: Implement FCM push notification
        \Log::info("Customer notification: {$message} for booking {$booking->id}");
    }
}
