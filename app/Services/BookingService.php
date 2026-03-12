<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingRider;
use App\Models\RiderQueue;
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
                if ($booking) {
                    $booking->remaining_pax += $assignment->allocated_seats;
                    $booking->save();

                    // Try to reassign the returned seats
                    $this->assignRiders($booking, true);
                }

                // Move rider to end of queue
                $riderQueue = $assignment->rider;
                if ($riderQueue) {
                    $this->moveRiderToEndOfQueue($riderQueue);
                }
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
        $riders = RiderQueue::available()
            ->byQueuePosition()
            ->get();

        $assignedRiders = collect();

        foreach ($riders as $riderQueue) {
            if ($remaining <= 0) {
                break;
            }

            // Skip if this rider already has an assignment for this booking
            $existingAssignment = BookingRider::where('booking_id', $booking->id)
                ->where('rider_id', $riderQueue->rider_id)
                ->first();
            
            if ($existingAssignment) {
                continue;
            }

            $riderCapacity = $riderQueue->user->vehicles->sum('capacity');
            $seats = min($riderCapacity, $remaining);

            // Create booking rider assignment
            BookingRider::create([
                'booking_id' => $booking->id,
                'rider_id' => $riderQueue->rider_id,
                'allocated_seats' => $seats,
                'status' => 'assigned',
                'expires_at' => now()->addMinutes(3),
            ]);

            // Set rider status to on duty
            $riderQueue->status = 'on_duty';
            $riderQueue->save();

            // Add to assigned riders list
            $assignedRiders->push($riderQueue);

            $remaining -= $seats;
        }

        // Update booking status
        if (!$preserveRemainingPax) {
            $booking->remaining_pax = $remaining;
        }
        
        // Keep status as 'pending' until riders accept
        if ($remaining <= 0 && !$preserveRemainingPax) {
            $booking->status = 'pending'; // All seats assigned, waiting for acceptance
        }

        // Send push notifications to assigned riders
        $this->sendPushNotifications($booking);
        
        // Broadcast booking assignment to riders
        broadcast(new BookingAssigned($booking, $assignedRiders));
    }

    public function acceptBooking(RiderQueue $riderQueue, Booking $booking)
    {
        // Update booking: change status to waiting first, then set rider_id
        $booking->status = 'waiting';
        $booking->rider_id = $riderQueue->rider_id;
        $booking->save();

        // Set rider status to on duty
        $riderQueue->status = 'on_duty';
        $riderQueue->save();

        return ['success' => true, 'message' => 'Booking accepted successfully'];
    }

    public function moveRiderToEndOfQueue(RiderQueue $riderQueue)
    {
        $oldPosition = $riderQueue->queue_position;
        $maxPosition = RiderQueue::online()->max('queue_position') ?? 0;
        $riderQueue->queue_position = $maxPosition + 1;
        $riderQueue->save();

        // Broadcast position change
        broadcast(new RiderPositionUpdated($riderQueue->rider_id, $riderQueue->queue_position, $oldPosition));
    }

    private function sendPushNotifications(Booking $booking)
    {
        $assignedRiders = $booking->assignedRiders()->with('rider.user')->get();

        foreach ($assignedRiders as $assignment) {
            $riderQueue = $assignment->rider;
            $user = $riderQueue ? $riderQueue->user : null;
            if ($user && $user->device_token) {
                // TODO: Implement FCM push notification
                Log::info("Push notification sent to rider {$user->id} for booking {$booking->id}");
            }
        }
    }

    public function goOnline(RiderQueue $riderQueue, $mode = 'stand_by')
    {
        return DB::transaction(function () use ($riderQueue, $mode) {
            $riderQueue->is_online = true;
            $riderQueue->status = 'open';
            if ($mode === 'listed') {
                $maxPosition = RiderQueue::online()->where('queue_position', '!=', 'stand by')->max('queue_position') ?? 0;
                $riderQueue->queue_position = (string)($maxPosition + 1);
            } else {
                $riderQueue->queue_position = 'stand by';
            }
            $riderQueue->save();
        });
    }

    public function goOffline(RiderQueue $riderQueue)
    {
        return DB::transaction(function () use ($riderQueue) {
            // Store current position before nullifying
            $currentPosition = $riderQueue->queue_position;
            
            $riderQueue->is_online = false;
            $riderQueue->queue_position = null;
            $riderQueue->status = null;
            $riderQueue->save();

            // Shift all riders with higher positions down
            $riders = RiderQueue::online()
                ->where('queue_position', '>', $currentPosition)
                ->orderBy('queue_position')
                ->get();

            foreach ($riders as $r) {
                $r->queue_position = $r->queue_position - 1;
                $r->save();
                broadcast(new RiderPositionUpdated($r->rider_id, $r->queue_position, $r->queue_position + 1));
            }
        });
    }

    /**
     * Start rider session when going online
     */
    public function startRiderSession(RiderQueue $riderQueue)
    {
        // Auto-close any existing active sessions
        $this->closeActiveSession($riderQueue);
        
        // Create new session
        $session = RiderSession::create([
            'rider_id' => $riderQueue->rider_id,
            'time_in' => now(),
        ]);
        
        return $session;
    }

    /**
     * End rider session when going offline
     */
    public function endRiderSession(RiderQueue $riderQueue)
    {
        $activeSession = $riderQueue->activeSession()->first();
        
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
    public function closeActiveSession(RiderQueue $riderQueue)
    {
        $activeSession = $riderQueue->activeSession()->first();
        
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
    public function getRiderDailyStats(RiderQueue $riderQueue, $date = null)
    {
        $date = $date ?: now()->toDateString();
        
        // Today's rides - completed bookings assigned to this rider
        $todayRides = \App\Models\Booking::where('rider_id', $riderQueue->rider_id)
            ->where('status', 'done')
            ->whereDate('updated_at', $date)
            ->count();

        // Today's earnings - sum of rider_earning from completed bookings
        $todayEarnings = \App\Models\Booking::where('rider_id', $riderQueue->rider_id)
            ->where('status', 'done')
            ->whereDate('updated_at', $date)
            ->sum('rider_earning');

        // Today's online time
        $todaySessions = $riderQueue->riderSessions()
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
        if ($booking->status !== 'done') {
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

            // Set rider status back to open
            $riderQueue = RiderQueue::where('rider_id', $bookingRider->rider_id)->first();
            if ($riderQueue) {
                $riderQueue->status = 'open';
                $riderQueue->save();
            }
        }
    }
}
