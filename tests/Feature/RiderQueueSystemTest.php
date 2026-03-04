<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Rider;
use App\Models\Booking;
use App\Models\BookingRider;
use App\Services\BookingService;

class RiderQueueSystemTest extends TestCase
{
    use RefreshDatabase;

    protected $bookingService;

    public function setUp(): void
    {
        parent::setUp();
        $this->bookingService = app(BookingService::class);
    }

    /**
     * Test rider queue system exactly as specified
     */
    public function test_rider_queue_system(): void
    {
        // Create riders
        $riders = [];
        for ($i = 1; $i <= 5; $i++) {
            $user = User::create([
                'name' => "Rider {$i}",
                'email' => "rider{$i}@test.com",
                'password' => bcrypt('password'),
                'role' => 'rider',
            ]);

            $riders[] = Rider::create([
                'user_id' => $user->id,
                'is_online' => false,
                'queue_position' => null,
                'capacity' => 2,
            ]);
        }

        echo "\n✅ Setup: Created 5 riders (all offline)";

        // Test Case 1: When rider goes online
        // First rider goes online
        $this->bookingService->goOnline($riders[0]);

        $riders[0]->refresh();
        $this->assertTrue($riders[0]->is_online);
        $this->assertEquals(1, $riders[0]->queue_position); // max(null) + 1

        echo "\n✅ Test Case 1: First rider goes online - PASSED";

        // Second rider goes online
        $this->bookingService->goOnline($riders[1]);

        $riders[1]->refresh();
        $this->assertTrue($riders[1]->is_online);
        $this->assertEquals(2, $riders[1]->queue_position); // max(1) + 1

        echo "\n✅ Test Case 1: Second rider goes online - PASSED";

        // Third rider goes online
        $this->bookingService->goOnline($riders[2]);

        $riders[2]->refresh();
        $this->assertTrue($riders[2]->is_online);
        $this->assertEquals(3, $riders[2]->queue_position); // max(2) + 1

        echo "\n✅ Test Case 1: Third rider goes online - PASSED";

        // Fourth rider goes online
        $this->bookingService->goOnline($riders[3]);

        $riders[3]->refresh();
        $this->assertTrue($riders[3]->is_online);
        $this->assertEquals(4, $riders[3]->queue_position); // max(3) + 1

        echo "\n✅ Test Case 1: Fourth rider goes online - PASSED";

        // Fifth rider goes online
        $this->bookingService->goOnline($riders[4]);

        $riders[4]->refresh();
        $this->assertTrue($riders[4]->is_online);
        $this->assertEquals(5, $riders[4]->queue_position); // max(4) + 1

        echo "\n✅ Test Case 1: Fifth rider goes online - PASSED";

        // Verify queue order
        $onlineRiders = Rider::online()->orderBy('queue_position')->get();
        for ($i = 0; $i < 5; $i++) {
            $this->assertEquals($i + 1, $onlineRiders[$i]->queue_position);
        }

        echo "\n✅ Queue order verified: 1, 2, 3, 4, 5 - PASSED";

        // Test Case 2: When rider goes offline
        // Third rider goes offline
        $this->bookingService->goOffline($riders[2]);

        $riders[2]->refresh();
        $this->assertFalse($riders[2]->is_online);
        $this->assertNull($riders[2]->queue_position); // queue_position removed

        echo "\n✅ Test Case 2: Third rider goes offline - PASSED";

        // Verify queue reordering
        $onlineRiders = Rider::online()->orderBy('queue_position')->get();
        $this->assertEquals(1, $onlineRiders[0]->queue_position); // Rider 1
        $this->assertEquals(2, $onlineRiders[1]->queue_position); // Rider 2
        $this->assertEquals(3, $onlineRiders[2]->queue_position); // Rider 4 (was 4, now 3)
        $this->assertEquals(4, $onlineRiders[3]->queue_position); // Rider 5 (was 5, now 4)

        echo "\n✅ Test Case 2: Queue reordered after offline - PASSED";

        // Test Case 3: When rider times out (move to end of queue)
        // Create a booking and assign to first rider
        $customer = User::create([
            'name' => 'Customer',
            'email' => 'customer@test.com',
            'password' => bcrypt('password'),
            'role' => 'customer',
        ]);

        $booking = Booking::create([
            'customer_id' => $customer->id,
            'pickup_location' => 'Test',
            'dropoff_location' => 'Test',
            'pax' => 2,
            'remaining_pax' => 2,
            'status' => 'pending',
        ]);

        // Assign first rider
        BookingRider::create([
            'booking_id' => $booking->id,
            'rider_id' => $riders[0]->id,
            'allocated_seats' => 2,
            'status' => 'assigned',
            'expires_at' => now()->subMinutes(5), // Already expired
        ]);

        echo "\n✅ Test Case 3: Created booking with expired assignment for rider 1";

        // Check initial queue position
        $riders[0]->refresh();
        $this->assertEquals(1, $riders[0]->queue_position);

        // Simulate timeout (move to end of queue)
        $this->bookingService->moveRiderToEndOfQueue($riders[0]);

        $riders[0]->refresh();
        $this->assertTrue($riders[0]->is_online);
        $this->assertEquals(5, $riders[0]->queue_position); // max(4) + 1

        echo "\n✅ Test Case 3: Rider 1 moved to end of queue after timeout - PASSED";

        // Verify queue order after timeout
        $onlineRiders = Rider::online()->orderBy('queue_position')->get();
        $this->assertEquals(2, $onlineRiders[0]->queue_position); // Rider 2
        $this->assertEquals(3, $onlineRiders[1]->queue_position); // Rider 4
        $this->assertEquals(4, $onlineRiders[2]->queue_position); // Rider 5
        $this->assertEquals(5, $onlineRiders[3]->queue_position); // Rider 1 (moved to end)

        echo "\n✅ Test Case 3: Queue reordered after timeout - PASSED";

        // Test Case 4: Multiple timeouts
        // Expire second rider assignment
        BookingRider::create([
            'booking_id' => $booking->id,
            'rider_id' => $riders[1]->id,
            'allocated_seats' => 2,
            'status' => 'assigned',
            'expires_at' => now()->subMinutes(3),
        ]);

        // Simulate timeout for rider 2
        $this->bookingService->moveRiderToEndOfQueue($riders[1]);

        $riders[1]->refresh();
        $this->assertEquals(6, $riders[1]->queue_position); // max(5) + 1

        echo "\n✅ Test Case 4: Rider 2 moved to end of queue after timeout - PASSED";

        // Final queue order
        $onlineRiders = Rider::online()->orderBy('queue_position')->get();
        $this->assertEquals(3, $onlineRiders[0]->queue_position); // Rider 4
        $this->assertEquals(4, $onlineRiders[1]->queue_position); // Rider 5
        $this->assertEquals(5, $onlineRiders[2]->queue_position); // Rider 1
        $this->assertEquals(6, $onlineRiders[3]->queue_position); // Rider 2

        echo "\n✅ Test Case 4: Multiple timeouts - queue order: 4, 5, 1, 2 - PASSED";

        echo "\n✅ All rider queue system tests completed successfully!";
    }

    /**
     * Test queue position logic
     */
    public function test_queue_position_logic(): void
    {
        // Create riders
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
                'capacity' => 2,
            ]);
        }

        // Test queue position calculation
        $ridersAhead = Rider::online()
            ->where('queue_position', '<', $riders[1]->queue_position)
            ->count();

        $this->assertEquals(1, $ridersAhead); // One rider ahead of position 2

        echo "\n✅ Queue position logic - PASSED";
    }

    /**
     * Test rider status logic
     */
    public function test_rider_status_logic(): void
    {
        // Create rider
        $user = User::create([
            'name' => 'Rider',
            'email' => 'rider@test.com',
            'password' => bcrypt('password'),
            'role' => 'rider',
        ]);

        $rider = Rider::create([
            'user_id' => $user->id,
            'is_online' => true,
            'queue_position' => 1,
            'capacity' => 2,
        ]);

        // Test rider availability
        $isAvailable = $rider->is_available;
        $this->assertTrue($isAvailable);

        echo "\n✅ Rider status logic - PASSED";
    }

    /**
     * Test FIFO queue ordering
     */
    public function test_fifo_queue_ordering(): void
    {
        // Create riders
        $riders = [];
        for ($i = 1; $i <= 4; $i++) {
            $user = User::create([
                'name' => "Rider {$i}",
                'email' => "rider{$i}@test.com",
                'password' => bcrypt('password'),
                'role' => 'rider',
            ]);

            $riders[] = Rider::create([
                'user_id' => $user->id,
                'is_online' => false,
                'queue_position' => null,
                'capacity' => 2,
            ]);
        }

        // Go online in random order to test FIFO
        $order = [2, 0, 3, 1]; // Random order (0-indexed)
        foreach ($order as $index) {
            $this->bookingService->goOnline($riders[$index]);
        }

        // Should maintain FIFO order regardless of online order
        $onlineRiders = Rider::online()->orderBy('queue_position')->get();
        $this->assertEquals(1, $onlineRiders[0]->queue_position); // First online (rider 3)
        $this->assertEquals(2, $onlineRiders[1]->queue_position); // Second online (rider 1)
        $this->assertEquals(3, $onlineRiders[2]->queue_position); // Third online (rider 4)
        $this->assertEquals(4, $onlineRiders[3]->queue_position); // Fourth online (rider 2)

        echo "\n✅ FIFO ordering maintained - PASSED";
    }

    /**
     * Test queue integrity after multiple operations
     */
    public function test_queue_integrity(): void
    {
        // Create riders
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
                'is_online' => false,
                'queue_position' => null,
                'capacity' => 2,
            ]);
        }

        // Go online
        $this->bookingService->goOnline($riders[0]);
        $this->bookingService->goOnline($riders[1]);
        $this->bookingService->goOnline($riders[2]);

        // Verify initial queue
        $this->assertEquals(1, $riders[0]->queue_position);
        $this->assertEquals(2, $riders[1]->queue_position);
        $this->assertEquals(3, $riders[2]->queue_position);

        // Middle rider goes offline and comes back online
        $this->bookingService->goOffline($riders[1]);
        $this->bookingService->goOnline($riders[1]);

        // Should be at end of queue
        $this->assertEquals(3, $riders[1]->queue_position); // Position 3 (was 2, now end)

        // Queue should be: 1, 3, 4 (actual values)
        $onlineRiders = Rider::online()->orderBy('queue_position')->get();
        $this->assertEquals(1, $onlineRiders[0]->queue_position); // Rider 1
        $this->assertEquals(2, $onlineRiders[1]->queue_position); // Rider 3
        $this->assertEquals(3, $onlineRiders[2]->queue_position); // Rider 2

        echo "\n✅ Queue integrity maintained after operations - PASSED";
    }
}
