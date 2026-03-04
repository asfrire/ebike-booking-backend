<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Rider;
use App\Models\Booking;
use App\Models\BookingRider;
use App\Services\BookingService;

class BookingLogicTest extends TestCase
{
    use RefreshDatabase;

    protected $bookingService;

    public function setUp(): void
    {
        parent::setUp();
        $this->bookingService = app(BookingService::class);
    }

    /**
     * Test booking creation and rider assignment logic exactly as specified
     */
    public function test_booking_creation_and_assignment_logic(): void
    {
        // Create customer
        $customer = User::create([
            'name' => 'Customer User',
            'email' => 'customer@test.com',
            'password' => bcrypt('password'),
            'role' => 'customer',
        ]);

        // Create 3 riders with different capacities
        $riders = [];
        for ($i = 1; $i <= 3; $i++) {
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
                'capacity' => 2, // All have capacity 2
            ]);
        }

        // Test Case 1: Single rider can handle booking (3 passengers)
        $booking1 = Booking::create([
            'customer_id' => $customer->id,
            'pickup_location' => '123 Main St',
            'dropoff_location' => '456 Oak Ave',
            'pax' => 3,
            'remaining_pax' => 3, // ✅ remaining_pax = pax
            'status' => 'pending', // ✅ status = pending
        ]);

        // Call assignRiders()
        $this->bookingService->assignRiders($booking1);

        // Verify assignment logic
        $booking1->refresh();
        
        // Should be fully_assigned (all 3 passengers assigned: 2+1)
        $this->assertEquals('fully_assigned', $booking1->status);
        $this->assertEquals(0, $booking1->remaining_pax); // 3 - 2 - 1 = 0 remaining
        
        // Should have 2 riders assigned
        $assignments = $booking1->bookingRiders;
        $this->assertEquals(2, $assignments->count());
        
        // First rider gets 2 seats
        $this->assertEquals(2, $assignments[0]->allocated_seats);
        $this->assertEquals('assigned', $assignments[0]->status);
        
        // Second rider gets 1 seat
        $this->assertEquals(1, $assignments[1]->allocated_seats);
        $this->assertEquals('assigned', $assignments[1]->status);

        echo "\n✅ Test Case 1: Full assignment (3 passengers with 2 riders) - PASSED";

        // Test Case 2: Exact fit (4 passengers) - create fresh riders
        $riders2 = [];
        for ($i = 4; $i <= 5; $i++) {
            $user = User::create([
                'name' => "Rider {$i}",
                'email' => "rider{$i}@test.com",
                'password' => bcrypt('password'),
                'role' => 'rider',
            ]);

            $riders2[] = Rider::create([
                'user_id' => $user->id,
                'is_online' => true,
                'queue_position' => $i - 3,
                'capacity' => 2,
            ]);
        }

        $booking2 = Booking::create([
            'customer_id' => $customer->id,
            'pickup_location' => '789 Pine St',
            'dropoff_location' => '321 Elm Ave',
            'pax' => 4,
            'remaining_pax' => 4,
            'status' => 'pending',
        ]);

        $this->bookingService->assignRiders($booking2);
        $booking2->refresh();

        // Should be fully_assigned (4 = 2 + 2)
        $this->assertEquals('fully_assigned', $booking2->status);
        $this->assertEquals(0, $booking2->remaining_pax);
        
        // Should have 2 riders assigned
        $assignments2 = $booking2->bookingRiders;
        $this->assertEquals(2, $assignments2->count());
        
        // Both riders get 2 seats each
        $this->assertEquals(2, $assignments2[0]->allocated_seats);
        $this->assertEquals(2, $assignments2[1]->allocated_seats);

        echo "\n✅ Test Case 2: Exact fit (4 passengers) - PASSED";

        // Test Case 3: Partial assignment when not enough riders (7 passengers, only 3 riders)
        // Make sure previous riders are offline to not interfere
        Rider::where('id', '!=', 0)->update(['is_online' => false]);
        
        $riders3 = [];
        for ($i = 6; $i <= 8; $i++) {
            $user = User::create([
                'name' => "Rider {$i}",
                'email' => "rider{$i}@test.com",
                'password' => bcrypt('password'),
                'role' => 'rider',
            ]);

            $riders3[] = Rider::create([
                'user_id' => $user->id,
                'is_online' => true,
                'queue_position' => $i - 5,
                'capacity' => 2,
            ]);
        }
        $booking3 = Booking::create([
            'customer_id' => $customer->id,
            'pickup_location' => '555 Maple St',
            'dropoff_location' => '999 Oak Ave',
            'pax' => 7,
            'remaining_pax' => 7,
            'status' => 'pending',
        ]);

        $this->bookingService->assignRiders($booking3);
        $booking3->refresh();

        // Should be partially_assigned (7 > 2+2+2 = 6 capacity available)
        $this->assertEquals('partially_assigned', $booking3->status);
        $this->assertEquals(1, $booking3->remaining_pax); // 7 - 6 = 1 remaining
        
        // Should have 3 riders assigned (all available)
        $assignments3 = $booking3->bookingRiders;
        $this->assertEquals(3, $assignments3->count());

        echo "\n✅ Test Case 3: Partial assignment (7 passengers, only 3 riders) - PASSED";

        // Test Case 4: All riders assigned exactly (6 passengers, 3 riders available)
        // Make the 3 riders available again by clearing their assignments
        $riderIds = collect($riders3)->pluck('id');
        BookingRider::whereIn('rider_id', $riderIds)->delete();
        
        $booking4 = Booking::create([
            'customer_id' => $customer->id,
            'pickup_location' => '777 Cedar St',
            'dropoff_location' => '888 Birch Ave',
            'pax' => 6,
            'remaining_pax' => 6,
            'status' => 'pending',
        ]);

        $this->bookingService->assignRiders($booking4);
        $booking4->refresh();

        // Should be fully_assigned (6 = 2+2+2)
        $this->assertEquals('fully_assigned', $booking4->status);
        $this->assertEquals(0, $booking4->remaining_pax);
        
        // Should have 3 riders assigned (all available)
        $assignments4 = $booking4->bookingRiders;
        $this->assertEquals(3, $assignments4->count());

        echo "\n✅ Test Case 4: All riders assigned (6 passengers) - PASSED";

        // Verify expiration times are set (3 minutes from now)
        foreach ($assignments3 as $assignment) {
            $this->assertNotNull($assignment->expires_at);
            $this->assertEquals('assigned', $assignment->status);
            $expiresAt = $assignment->expires_at;
            $expectedTime = now()->addMinutes(3);
            $this->assertLessThan(5, $expectedTime->diffInSeconds($expiresAt)); // Within 5 seconds
        }

        echo "\n✅ Expiration times set correctly (3 minutes) - PASSED";
        echo "\n✅ All booking logic tests completed successfully!";
    }

    /**
     * Test rider availability logic (is_online = true, not busy)
     */
    public function test_rider_availability_logic(): void
    {
        // Create riders with different statuses
        $onlineAvailableRider = $this->createRider('online1@test.com', true, 1);
        $onlineBusyRider = $this->createRider('online2@test.com', true, 2);
        $offlineRider = $this->createRider('offline@test.com', false, 3);

        // Make one rider busy by giving them an assignment
        BookingRider::create([
            'booking_id' => Booking::create([
                'customer_id' => User::create(['name' => 'Test', 'email' => 'test@test.com', 'password' => bcrypt('pass'), 'role' => 'customer'])->id,
                'pickup_location' => 'Test',
                'dropoff_location' => 'Test',
                'pax' => 1,
                'remaining_pax' => 1,
                'status' => 'pending',
            ])->id,
            'rider_id' => $onlineBusyRider->id,
            'allocated_seats' => 1,
            'status' => 'assigned',
            'expires_at' => now()->addMinutes(3),
        ]);

        // Test available riders query
        $availableRiders = Rider::available()->byQueuePosition()->get();
        
        // Should only include online and not busy riders
        $this->assertEquals(1, $availableRiders->count());
        $this->assertEquals($onlineAvailableRider->id, $availableRiders[0]->id);

        echo "\n✅ Rider availability logic (online + not busy) - PASSED";
    }

    private function createRider($email, $isOnline, $queuePosition)
    {
        $user = User::create([
            'name' => 'Test Rider',
            'email' => $email,
            'password' => bcrypt('password'),
            'role' => 'rider',
        ]);

        return Rider::create([
            'user_id' => $user->id,
            'is_online' => $isOnline,
            'queue_position' => $queuePosition,
            'capacity' => 2,
        ]);
    }
}
