<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingRider;
use App\Models\Rider;
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
        $maxPosition = Rider::online()->max('queue_position') ?? 0;
        $rider->queue_position = $maxPosition + 1;
        $rider->save();
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
                $position++;
            }
        });
    }
}
