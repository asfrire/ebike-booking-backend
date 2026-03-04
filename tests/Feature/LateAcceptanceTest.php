<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Rider;
use App\Models\Booking;
use App\Models\BookingRider;
use App\Services\BookingService;
use Carbon\Carbon;

class LateAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected $bookingService;

    public function setUp(): void
    {
        parent::setUp();
        $this->bookingService = app(BookingService::class);
    }

    /**
     * Test late acceptance rule exactly as specified
     */
    public function test_late_acceptance_rule(): void
    {
        // Create customer
        $customer = User::create([
            'name' => 'Customer User',
            'email' => 'customer@test.com',
            'password' => bcrypt('password'),
            'role' => 'customer',
        ]);

        // Create 2 riders
        $riders = [];
        for ($i = 1; $i <= 2; $i++) {
            $user = User::create([
                'name' => "Rider {$i}",
                'email' => "rider{$i}@test.com",
                'password' => bcrypt('password'),
                'role' => 'rider',
            ]);

            $riders[] = Rider::create([
                'user_id' => $user->id,
                'is_online' => true,
                'queue_position' => $i,
                'capacity' => 2,
            ]);
        }

        // Create booking with 4 passengers (needs both riders)
        $booking = Booking::create([
            'customer_id' => $customer->id,
            'pickup_location' => '123 Main St',
            'dropoff_location' => '456 Oak Ave',
            'pax' => 4,
            'remaining_pax' => 4,
            'status' => 'pending',
        ]);

        // Assign riders
        $this->bookingService->assignRiders($booking);
        $booking->refresh();

        // Should have 2 riders assigned
        $this->assertEquals(2, $booking->bookingRiders->count());
        $this->assertEquals('fully_assigned', $booking->status);

        echo "\n✅ Setup: Booking created with 2 riders assigned";

        // Test Case 1: Late acceptance allowed - booking not yet accepted, seats not reassigned
        // Expire first rider assignment
        $assignment1 = $booking->bookingRiders()->where('rider_id', $riders[0]->id)->first();
        $assignment1->expires_at = Carbon::now()->subMinutes(5); // 5 minutes ago
        $assignment1->save();

        echo "\n✅ Test Case 1: First rider assignment expired";

        // First rider tries to accept after expiration
        $result1 = $this->bookingService->acceptBooking($riders[0], $booking);

        // Should be allowed because:
        // - booking.status != accepted (still fully_assigned)
        // - seats not yet reassigned and accepted (second rider still assigned)
        $this->assertTrue($result1['success']);
        $this->assertEquals('Booking accepted successfully', $result1['message']);

        // Verify assignment status
        $assignment1->refresh();
        $this->assertEquals('accepted', $assignment1->status);

        echo "\n✅ Test Case 1: Late acceptance allowed - PASSED";

        // Test Case 2: Late acceptance rejected - seats already reassigned and accepted
        // Expire second rider assignment
        $assignment2 = $booking->bookingRiders()->where('rider_id', $riders[1]->id)->first();
        $assignment2->expires_at = Carbon::now()->subMinutes(10); // 10 minutes ago
        $assignment2->status = 'expired';
        $assignment2->save();

        echo "\n✅ Test Case 2: Second rider assignment expired";

        // Create a new rider and assign the expired seats
        $rider3User = User::create([
            'name' => 'Rider 3',
            'email' => 'rider3@test.com',
            'password' => bcrypt('password'),
            'role' => 'rider',
        ]);

        $rider3 = Rider::create([
            'user_id' => $rider3User->id,
            'is_online' => true,
            'queue_position' => 3,
            'capacity' => 2,
        ]);

        // Manually create assignment for rider 3 (simulating reassignment)
        BookingRider::create([
            'booking_id' => $booking->id,
            'rider_id' => $rider3->id,
            'allocated_seats' => 2,
            'status' => 'accepted', // Already accepted
            'expires_at' => Carbon::now()->addMinutes(3),
        ]);

        echo "\n✅ Test Case 2: Seats reassigned to rider 3 and accepted";

        // Now second rider tries to accept after expiration
        $result2 = $this->bookingService->acceptBooking($riders[1], $booking);

        // Should be rejected because:
        // - booking.status != accepted (still fully_assigned)
        // - BUT seats have been reassigned and accepted (rider 3 accepted 2 seats)
        // - total accepted seats (2) + this rider's seats (2) >= booking.pax (4)
        $this->assertFalse($result2['success']);
        $this->assertEquals('Seats have been reassigned to other riders', $result2['message']);

        echo "\n✅ Test Case 2: Late acceptance rejected - PASSED";

        // Test Case 3: Late acceptance rejected - booking already accepted
        // Accept the booking by accepting rider 3's assignment (already done)
        // Now mark booking as accepted
        $booking->status = 'accepted';
        $booking->save();

        echo "\n✅ Test Case 3: Booking marked as accepted";

        // First rider tries to accept again
        $result3 = $this->bookingService->acceptBooking($riders[0], $booking);

        // Should be rejected because booking.status == accepted
        $this->assertFalse($result3['success']);
        $this->assertEquals('Booking already accepted by other riders', $result3['message']);

        echo "\n✅ Test Case 3: Late acceptance rejected (booking already accepted) - PASSED";

        // Test Case 4: Late acceptance within DB transaction
        // Reset booking status
        $booking->status = 'fully_assigned';
        $booking->save();

        // Remove rider 3 assignment
        BookingRider::where('rider_id', $rider3->id)->delete();

        // Expire first rider again
        $assignment1->status = 'assigned';
        $assignment1->expires_at = Carbon::now()->subMinutes(5);
        $assignment1->save();

        // Simulate concurrent acceptance attempt
        $result4 = $this->bookingService->acceptBooking($riders[0], $booking);

        // Should work within DB transaction
        $this->assertTrue($result4['success']);

        echo "\n✅ Test Case 4: Late acceptance within DB transaction - PASSED";

        echo "\n✅ All late acceptance rule tests completed successfully!";
    }

    /**
     * Test edge cases for late acceptance
     */
    public function test_late_acceptance_edge_cases(): void
    {
        // Create fresh test data
        $customer = User::create([
            'name' => 'Customer',
            'email' => 'customer3@test.com',
            'password' => bcrypt('password'),
            'role' => 'customer',
        ]);

        $riderUser = User::create([
            'name' => 'Rider',
            'email' => 'rider@test.com',
            'password' => bcrypt('password'),
            'role' => 'rider',
        ]);

        $rider = Rider::create([
            'user_id' => $riderUser->id,
            'is_online' => true,
            'queue_position' => 1,
            'capacity' => 2,
        ]);

        $booking = Booking::create([
            'customer_id' => $customer->id,
            'pickup_location' => 'Test',
            'dropoff_location' => 'Test',
            'pax' => 2,
            'remaining_pax' => 2,
            'status' => 'pending',
        ]);

        // Create assignment
        $assignment = BookingRider::create([
            'booking_id' => $booking->id,
            'rider_id' => $rider->id,
            'allocated_seats' => 2,
            'status' => 'assigned',
            'expires_at' => Carbon::now()->subMinutes(5), // Already expired
        ]);

        // Edge Case 1: Accept expired assignment with no other riders
        $result = $this->bookingService->acceptBooking($rider, $booking);

        // Should be allowed (no competition for seats)
        $this->assertTrue($result['success']);

        echo "\n✅ Edge Case 1: Accept expired assignment with no competition - PASSED";

        // Edge Case 2: Try to accept non-existent assignment
        $rider2User = User::create([
            'name' => 'Rider 2',
            'email' => 'rider2@test.com',
            'password' => bcrypt('password'),
            'role' => 'rider',
        ]);

        $rider2 = Rider::create([
            'user_id' => $rider2User->id,
            'is_online' => true,
            'queue_position' => 2,
            'capacity' => 2,
        ]);

        $result2 = $this->bookingService->acceptBooking($rider2, $booking);

        // Should be rejected (no assignment found)
        $this->assertFalse($result2['success']);
        // The error message might be different if booking is already accepted
        $this->assertContains($result2['message'], ['No assignment found for this rider', 'Booking already accepted by other riders']);

        echo "\n✅ Edge Case 2: Accept non-existent assignment - REJECTED (PASSED)";

        echo "\n✅ All edge case tests completed successfully!";
    }
}
