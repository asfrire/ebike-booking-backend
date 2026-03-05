<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Rider;
use App\Models\Booking;
use App\Models\BookingRider;
use App\Models\RiderSession;
use App\Services\BookingService;
use Carbon\Carbon;

class RiderEarningsSystemTest extends TestCase
{
    use RefreshDatabase;

    protected $bookingService;

    public function setUp(): void
    {
        parent::setUp();
        $this->bookingService = app(BookingService::class);
    }

    /**
     * Test rider session tracking and earnings calculation
     */
    public function test_rider_session_and_earnings_system(): void
    {
        // Create rider
        $user = User::create([
            'name' => 'Test Rider',
            'email' => 'rider@test.com',
            'password' => bcrypt('password'),
            'role' => 'rider',
        ]);

        $rider = Rider::create([
            'user_id' => $user->id,
            'is_online' => false,
            'queue_position' => null,
            'capacity' => 2,
        ]);

        echo "\n✅ Setup: Created rider";

        // Test Case 1: Rider goes online - session starts
        $this->bookingService->goOnline($rider);
        $session = $this->bookingService->startRiderSession($rider);

        $rider->refresh();
        $session->refresh();

        $this->assertTrue($rider->is_online);
        $this->assertEquals(1, $rider->queue_position);
        $this->assertNotNull($session->time_in);
        $this->assertNull($session->time_out);
        $this->assertNull($session->total_minutes);

        echo "\n✅ Test Case 1: Rider goes online - session started - PASSED";

        // Test Case 2: Create booking and assign to rider
        $customer = User::create([
            'name' => 'Test Customer',
            'email' => 'customer@test.com',
            'password' => bcrypt('password'),
            'role' => 'customer',
        ]);

        $booking = Booking::create([
            'customer_id' => $customer->id,
            'pickup_location' => 'Test Pickup',
            'dropoff_location' => 'Test Dropoff',
            'pax' => 2,
            'remaining_pax' => 0,
            'status' => 'fully_assigned',
        ]);

        // Create booking rider assignment
        $bookingRider = BookingRider::create([
            'booking_id' => $booking->id,
            'rider_id' => $rider->id,
            'allocated_seats' => 2,
            'status' => 'accepted',
            'expires_at' => now()->addMinutes(3),
        ]);

        echo "\n✅ Test Case 2: Booking created and assigned to rider - PASSED";

        // Test Case 3: Rider goes offline - session ends
        $this->bookingService->goOffline($rider);
        $endedSession = $this->bookingService->endRiderSession($rider);

        $rider->refresh();
        $endedSession->refresh();

        $this->assertFalse($rider->is_online);
        $this->assertNull($rider->queue_position);
        $this->assertNotNull($endedSession->time_out);
        $this->assertNotNull($endedSession->total_minutes);
        $this->assertGreaterThan(0, $endedSession->total_minutes);

        echo "\n✅ Test Case 3: Rider goes offline - session ended - PASSED";
        echo "\n   Session duration: {$endedSession->total_minutes} minutes";

        // Test Case 4: Complete booking and calculate earnings
        $this->bookingService->calculateEarnings($booking);

        $booking->refresh();
        $bookingRider->refresh();

        $this->assertEquals('completed', $booking->status);
        $this->assertNotNull($booking->total_fare);
        $this->assertNotNull($booking->platform_fee);
        $this->assertNotNull($booking->rider_earning);
        $this->assertEquals('completed', $bookingRider->status);
        $this->assertNotNull($bookingRider->earning_amount);

        echo "\n✅ Test Case 4: Booking completed - earnings calculated - PASSED";
        echo "\n   Total fare: {$booking->total_fare}";
        echo "\n   Platform fee: {$booking->platform_fee}";
        echo "\n   Rider earning: {$booking->rider_earning}";
        echo "\n   Rider's share: {$bookingRider->earning_amount}";

        // Test Case 5: Daily statistics
        $stats = $this->bookingService->getRiderDailyStats($rider);

        $this->assertEquals(1, $stats['rides']);
        $this->assertEquals($bookingRider->earning_amount, $stats['earnings']);
        $this->assertEquals($endedSession->total_minutes, $stats['online_minutes']);
        $this->assertEquals($endedSession->total_minutes / 60, $stats['online_hours']);

        echo "\n✅ Test Case 5: Daily statistics calculated - PASSED";
        echo "\n   Today's rides: {$stats['rides']}";
        echo "\n   Today's earnings: {$stats['earnings']}";
        echo "\n   Online minutes: {$stats['online_minutes']}";
        echo "\n   Online hours: " . round($stats['online_hours'], 2);

        // Test Case 6: Multiple sessions in one day
        // Start second session
        $this->bookingService->goOnline($rider);
        $session2 = $this->bookingService->startRiderSession($rider);

        // Simulate some time passing
        Carbon::setTestNow(now()->addMinutes(30));

        $this->bookingService->goOffline($rider);
        $endedSession2 = $this->bookingService->endRiderSession($rider);

        $stats2 = $this->bookingService->getRiderDailyStats($rider);

        $this->assertEquals($endedSession->total_minutes + $endedSession2->total_minutes, $stats2['online_minutes']);

        echo "\n✅ Test Case 6: Multiple sessions tracked - PASSED";
        echo "\n   Total online minutes: {$stats2['online_minutes']}";

        // Test Case 7: Edge case - auto-close orphaned session
        // Create orphaned session (time_in but no time_out)
        $orphanedSession = RiderSession::create([
            'rider_id' => $rider->id,
            'time_in' => now()->subHours(2),
            'time_out' => null,
            'total_minutes' => null,
        ]);

        // Go online again - should auto-close orphaned session
        $this->bookingService->goOnline($rider);
        $newSession = $this->bookingService->startRiderSession($rider);

        $orphanedSession->refresh();
        $this->assertNotNull($orphanedSession->time_out);
        $this->assertNotNull($orphanedSession->total_minutes);

        echo "\n✅ Test Case 7: Orphaned session auto-closed - PASSED";
        echo "\n   Orphaned session duration: {$orphanedSession->total_minutes} minutes";

        echo "\n✅ All rider earnings system tests completed successfully!";
    }

    /**
     * Test multi-rider booking earnings distribution
     */
    public function test_multi_rider_booking_earnings(): void
    {
        // Create two riders
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

        // Create booking with 3 passengers
        $customer = User::create([
            'name' => 'Test Customer',
            'email' => 'customer@test.com',
            'password' => bcrypt('password'),
            'role' => 'customer',
        ]);

        $booking = Booking::create([
            'customer_id' => $customer->id,
            'pickup_location' => 'Test Pickup',
            'dropoff_location' => 'Test Dropoff',
            'pax' => 3,
            'remaining_pax' => 0,
            'status' => 'fully_assigned',
        ]);

        // Assign riders (2 seats to rider 1, 1 seat to rider 2)
        $bookingRider1 = BookingRider::create([
            'booking_id' => $booking->id,
            'rider_id' => $riders[0]->id,
            'allocated_seats' => 2,
            'status' => 'accepted',
        ]);

        $bookingRider2 = BookingRider::create([
            'booking_id' => $booking->id,
            'rider_id' => $riders[1]->id,
            'allocated_seats' => 1,
            'status' => 'accepted',
        ]);

        // Complete booking and calculate earnings
        $this->bookingService->calculateEarnings($booking);

        $booking->refresh();
        $bookingRider1->refresh();
        $bookingRider2->refresh();

        // Verify earnings distribution
        $expectedTotalFare = 50 + (3 * 20); // Base fare + per passenger
        $expectedPlatformFee = $expectedTotalFare * 0.15;
        $expectedTotalRiderEarning = $expectedTotalFare - $expectedPlatformFee;
        $expectedFarePerSeat = $expectedTotalRiderEarning / 3;

        $this->assertEquals($expectedTotalFare, $booking->total_fare);
        $this->assertEquals($expectedPlatformFee, $booking->platform_fee);
        $this->assertEquals($expectedTotalRiderEarning, $booking->rider_earning);

        // Rider 1 should get 2 seats worth
        $this->assertEquals($expectedFarePerSeat * 2, $bookingRider1->earning_amount);

        // Rider 2 should get 1 seat worth
        $this->assertEquals($expectedFarePerSeat * 1, $bookingRider2->earning_amount);

        echo "\n✅ Multi-rider earnings distribution test - PASSED";
        echo "\n   Total fare: {$booking->total_fare}";
        echo "\n   Platform fee: {$booking->platform_fee}";
        echo "\n   Rider 1 earnings (2 seats): {$bookingRider1->earning_amount}";
        echo "\n   Rider 2 earnings (1 seat): {$bookingRider2->earning_amount}";
    }
}
