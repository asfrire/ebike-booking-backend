<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingRider;
use App\Models\Rider;
use App\Models\RiderSession;
use App\Models\Subdivision;
use App\Models\Phase;
use App\Models\Fare;
use App\Events\BookingAssigned;
use App\Events\RiderPositionUpdated;
use App\Events\BookingStatusUpdated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BookingService
{
    public function checkTimeouts()
    {
        $expiredAssignments = BookingRider::expired()->get();

        foreach ($expiredAssignments as $assignment) {
            DB::transaction(function () use ($assignment) {
                // Mark as expired
                $assignment->status = 'expired';
                $assignment->save();

                // Return seats to booking
                $booking = $assignment->booking;
                $booking->remaining_pax += $assignment->allocated_seats;
                $booking->save();

                // Move rider to end of queue
                $rider = $assignment->rider;
                $this->moveRiderToEndOfQueue($rider);

                // Try to reassign the returned seats
                $this->assignRiders($booking, true);
            });
        }
    }

    public function assignRiders(Booking $booking, $preserveRemainingPax = false)
    {
        $remaining = $preserveRemainingPax ? $booking->remaining_pax : $booking->remaining_pax;

        if ($remaining <= 0) {
            return;
        }

        // Get available riders ordered by queue position
        $riders = Rider::available()
            ->byQueuePosition()
            ->get();

        foreach ($riders as $rider) {
            if ($remaining <= 0) {
                break;
            }

            // Skip if this rider already has an assignment for this booking
            $existingAssignment = BookingRider::where('booking_id', $booking->id)
                ->where('rider_id', $rider->id)
                ->first();
            
            if ($existingAssignment) {
                continue;
            }

            $seats = min($rider->capacity, $remaining);

            // Create booking rider assignment
            BookingRider::create([
                'booking_id' => $booking->id,
                'rider_id' => $rider->id,
                'allocated_seats' => $seats,
                'status' => 'assigned',
                'expires_at' => now()->addMinutes(3),
            ]);

            $remaining -= $seats;
        }

        // Update booking status
        if (!$preserveRemainingPax) {
            $booking->remaining_pax = $remaining;
        }
        
        if ($remaining > 0) {
            $booking->status = 'partially_assigned';
        } else {
            $booking->status = 'fully_assigned';
        }
        $booking->save();

        // Send push notifications to assigned riders
        $this->sendPushNotifications($booking);
        
        // Broadcast booking assignment to riders
        broadcast(new BookingAssigned($booking, $assignedRiders));
    }

    public function acceptBooking(Rider $rider, Booking $booking)
    {
        return DB::transaction(function () use ($rider, $booking) {
            // Lock the booking row
            $lockedBooking = Booking::lockForUpdate()->find($booking->id);

            if ($lockedBooking->status === 'accepted') {
                return ['success' => false, 'message' => 'Booking already accepted by other riders'];
            }

            // Find the assignment for this rider
            $assignment = BookingRider::where('booking_id', $booking->id)
                ->where('rider_id', $rider->id)
                ->first();

            if (!$assignment) {
                return ['success' => false, 'message' => 'No assignment found for this rider'];
            }

            // Check if assignment is expired but seats haven't been reassigned
            if ($assignment->status === 'expired') {
                // Check if the seats are still available (not reassigned and accepted)
                $reassignedSeats = BookingRider::where('booking_id', $booking->id)
                    ->where('rider_id', '!=', $rider->id)
                    ->where('status', 'accepted')
                    ->sum('allocated_seats');

                $totalAcceptedSeats = $reassignedSeats + $assignment->allocated_seats;

                if ($totalAcceptedSeats >= $booking->pax) {
                    return ['success' => false, 'message' => 'Seats have been reassigned to other riders'];
                }

                // Allow late acceptance
                $assignment->status = 'accepted';
                $assignment->save();
            } elseif ($assignment->status === 'assigned') {
                // Normal acceptance
                $assignment->status = 'accepted';
                $assignment->save();
            } else {
                return ['success' => false, 'message' => 'Assignment cannot be accepted'];
            }

            // Recalculate remaining_pax based on all accepted assignments
            $totalAcceptedSeats = BookingRider::where('booking_id', $booking->id)
                ->where('status', 'accepted')
                ->sum('allocated_seats');
            
            $booking->remaining_pax = max(0, $booking->pax - $totalAcceptedSeats);

            // Check if all assignments are accepted
            $nonExpiredAssignments = BookingRider::where('booking_id', $booking->id)
                ->where('status', '!=', 'expired')
                ->get();

            $allAccepted = $nonExpiredAssignments->every(function ($assignment) {
                return $assignment->status === 'accepted';
            });

            if ($allAccepted && $nonExpiredAssignments->count() > 0) {
                $booking->status = 'accepted';
            }

            $booking->save();

            return ['success' => true, 'message' => 'Booking accepted successfully'];
        });
    }

    public function moveRiderToEndOfQueue(Rider $rider)
    {
        $oldPosition = $rider->queue_position;
        $maxPosition = Rider::online()->max('queue_position') ?? 0;
        $rider->queue_position = $maxPosition + 1;
        $rider->save();

        // Broadcast position change
        broadcast(new RiderPositionUpdated($rider->id, $rider->queue_position, $oldPosition));
    }

    private function sendPushNotifications(Booking $booking)
    {
        $assignedRiders = $booking->assignedRiders()->with('rider.user')->get();

        foreach ($assignedRiders as $assignment) {
            $user = $assignment->rider->user;
            if ($user->device_token) {
                // TODO: Implement FCM push notification
                Log::info("Push notification sent to rider {$user->id} for booking {$booking->id}");
            }
        }
    }

    public function goOnline(Rider $rider)
    {
        return DB::transaction(function () use ($rider) {
            $rider->is_online = true;
            
            $maxPosition = Rider::online()->max('queue_position') ?? 0;
            $rider->queue_position = $maxPosition + 1;
            
            $rider->save();
        });
    }

    public function goOffline(Rider $rider)
    {
        return DB::transaction(function () use ($rider) {
            // Store current position before nullifying
            $currentPosition = $rider->queue_position;
            
            $rider->is_online = false;
            $rider->queue_position = null;
            $rider->save();

            // Reorder remaining riders
            $riders = Rider::online()
                ->where('queue_position', '>', $currentPosition)
                ->orderBy('queue_position')
                ->get();

            $position = $currentPosition;
            foreach ($riders as $r) {
                $r->queue_position = $position;
                $r->save();
                
                // Broadcast position change
                broadcast(new RiderPositionUpdated($r->id, $r->queue_position, $r->queue_position - 1));
                
                $position++;
            }
        });
    }

    /**
     * Start rider session when going online
     */
    public function startRiderSession(Rider $rider)
    {
        // Auto-close any existing active sessions
        $this->closeActiveSession($rider);
        
        // Create new session
        $session = RiderSession::create([
            'rider_id' => $rider->id,
            'time_in' => now(),
        ]);
        
        return $session;
    }

    /**
     * End rider session when going offline
     */
    public function endRiderSession(Rider $rider)
    {
        $activeSession = $rider->activeSession()->first();
        
        if ($activeSession) {
            $timeOut = now();
            $totalMinutes = $timeOut->diffInMinutes($activeSession->time_in);
            
            $activeSession->update([
                'time_out' => $timeOut,
                'total_minutes' => $totalMinutes,
            ]);
        }
        
        return $activeSession;
    }

    /**
     * Close any active session for a rider (edge case handling)
     */
    public function closeActiveSession(Rider $rider)
    {
        $activeSession = $rider->activeSession()->first();
        
        if ($activeSession) {
            $timeOut = now();
            $totalMinutes = $timeOut->diffInMinutes($activeSession->time_in);
            
            $activeSession->update([
                'time_out' => $timeOut,
                'total_minutes' => $totalMinutes,
            ]);
        }
    }

    /**
     * Get rider's daily statistics
     */
    public function getRiderDailyStats(Rider $rider, $date = null)
    {
        $date = $date ?: now()->toDateString();
        
        // Today's rides
        $todayRides = $rider->bookingRiders()
            ->where('status', 'completed')
            ->whereDate('updated_at', $date)
            ->count();

        // Today's earnings
        $todayEarnings = $rider->bookingRiders()
            ->where('status', 'completed')
            ->whereDate('updated_at', $date)
            ->sum('earning_amount');

        // Today's online time
        $todaySessions = $rider->riderSessions()
            ->whereDate('time_in', $date)
            ->get();

        $todayOnlineMinutes = $todaySessions->sum('total_minutes');

        return [
            'date' => $date,
            'rides' => $todayRides,
            'earnings' => $todayEarnings,
            'online_minutes' => $todayOnlineMinutes,
            'online_hours' => $todayOnlineMinutes / 60,
        ];
    }

    /**
     * Determine phase based on subdivision, block, and lot
     */
    public function determinePhase($subdivisionId, $blockNumber, $lotNumber)
    {
        $subdivision = Subdivision::find($subdivisionId);
        
        if (!$subdivision) {
            return null;
        }

        // Primera always has Phase 1
        if ($subdivision->name === 'Primera') {
            return Phase::where('subdivision_id', $subdivisionId)
                       ->where('name', 'Phase 1')
                       ->first();
        }

        // Sonera logic
        if ($subdivision->name === 'Sonera') {
            $block = (int) $blockNumber;
            $lot = (int) $lotNumber;

            // Check Block 2 specific conditions first
            if ($block === 2) {
                if ($lot >= 1 && $lot <= 57) {
                    return Phase::where('subdivision_id', $subdivisionId)
                               ->where('name', 'Phase 1')
                               ->first();
                } elseif ($lot >= 58) {
                    return Phase::where('subdivision_id', $subdivisionId)
                               ->where('name', 'Phase 2')
                               ->first();
                }
            }

            // Phase 1 conditions (other blocks)
            if ($block >= 1 && $block <= 15) {
                return Phase::where('subdivision_id', $subdivisionId)
                           ->where('name', 'Phase 1')
                           ->first();
            }

            // Phase 2 conditions (other blocks)
            if ($block >= 16 && $block <= 25) {
                return Phase::where('subdivision_id', $subdivisionId)
                           ->where('name', 'Phase 2')
                           ->first();
            }
        }

        return null;
    }

    /**
     * Get fare per passenger for subdivision and phase
     */
    public function getFarePerPassenger($subdivisionId, $phaseId)
    {
        $fare = Fare::where('subdivision_id', $subdivisionId)
                   ->where('phase_id', $phaseId)
                   ->first();

        return $fare ? $fare->fare_per_passenger : null;
    }

    /**
     * Calculate total fare for booking
     */
    public function calculateTotalFare($subdivisionId, $phaseId, $pax)
    {
        $farePerPassenger = $this->getFarePerPassenger($subdivisionId, $phaseId);
        
        if ($farePerPassenger === null) {
            return null;
        }

        return $farePerPassenger * $pax;
    }

    /**
     * Process booking with fare calculation
     */
    public function processBookingWithFare($bookingData)
    {
        // Determine phase
        $phase = $this->determinePhase(
            $bookingData['subdivision_id'],
            $bookingData['block_number'],
            $bookingData['lot_number']
        );

        if (!$phase) {
            return [
                'success' => false,
                'message' => 'Unable to determine phase for the given location'
            ];
        }

        // Get fare per passenger
        $farePerPassenger = $this->getFarePerPassenger(
            $bookingData['subdivision_id'],
            $phase->id
        );

        if ($farePerPassenger === null) {
            return [
                'success' => false,
                'message' => 'Fare not configured for this subdivision and phase'
            ];
        }

        // Calculate total fare
        $totalFare = $this->calculateTotalFare(
            $bookingData['subdivision_id'],
            $phase->id,
            $bookingData['pax']
        );

        // Add fare information to booking data
        $bookingData['phase_id'] = $phase->id;
        $bookingData['fare_per_passenger'] = $farePerPassenger;
        $bookingData['total_fare'] = $totalFare;

        return [
            'success' => true,
            'booking_data' => $bookingData,
            'phase' => $phase,
            'fare_per_passenger' => $farePerPassenger,
            'total_fare' => $totalFare,
        ];
    }

    /**
     * Update earnings calculation to use actual fare
     */
    public function calculateEarnings(Booking $booking)
    {
        if ($booking->status !== 'completed') {
            return;
        }

        // Use the actual total fare from booking
        $totalFare = $booking->total_fare;
        
        if (!$totalFare || $totalFare <= 0) {
            return;
        }

        $platformFee = $totalFare * 0.15; // 15% platform fee
        $totalRiderEarning = $totalFare - $platformFee;

        // Update booking earnings
        $booking->update([
            'platform_fee' => $platformFee,
            'rider_earning' => $totalRiderEarning,
        ]);

        // Calculate earnings per rider
        $acceptedRiders = $booking->bookingRiders()
            ->where('status', 'accepted')
            ->get();

        if ($acceptedRiders->isEmpty()) {
            return;
        }

        $farePerSeat = $totalRiderEarning / $booking->pax;

        foreach ($acceptedRiders as $bookingRider) {
            $riderEarning = $farePerSeat * $bookingRider->allocated_seats;
            
            $bookingRider->update([
                'earning_amount' => $riderEarning,
                'status' => 'completed',
            ]);
        }
    }
}
