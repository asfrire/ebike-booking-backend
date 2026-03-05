<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Rider;
use App\Models\Booking;
use App\Models\BookingRider;
use App\Models\Subdivision;
use App\Models\Phase;
use App\Models\Fare;
use App\Services\BookingService;
use Carbon\Carbon;

class FareManagementSystemTest extends TestCase
{
    use RefreshDatabase;

    protected $bookingService;

    public function setUp(): void
    {
        parent::setUp();
        $this->bookingService = app(BookingService::class);
        
        // Seed the fare system
        $this->seed(\Database\Seeders\FareSystemSeeder::class);
    }

    /**
     * Test complete fare management system
     */
    public function test_complete_fare_system(): void
    {
        echo "\n🚀 TESTING COMPLETE FARE MANAGEMENT SYSTEM\n";

        // Test Case 1: Verify seeded data
        $primera = Subdivision::where('name', 'Primera')->first();
        $sonera = Subdivision::where('name', 'Sonera')->first();

        $this->assertNotNull($primera);
        $this->assertNotNull($sonera);

        $primeraPhase1 = Phase::where('subdivision_id', $primera->id)->where('name', 'Phase 1')->first();
        $soneraPhase1 = Phase::where('subdivision_id', $sonera->id)->where('name', 'Phase 1')->first();
        $soneraPhase2 = Phase::where('subdivision_id', $sonera->id)->where('name', 'Phase 2')->first();

        $this->assertNotNull($primeraPhase1);
        $this->assertNotNull($soneraPhase1);
        $this->assertNotNull($soneraPhase2);

        echo "\n✅ Test Case 1: Seeded data verified - PASSED";

        // Test Case 2: Primera phase determination (always Phase 1)
        $phase = $this->bookingService->determinePhase($primera->id, '5', '10');
        $this->assertEquals($primeraPhase1->id, $phase->id);
        echo "\n✅ Test Case 2: Primera phase determination - PASSED";

        // Test Case 3: Sonera Phase 1 determination
        $phase1 = $this->bookingService->determinePhase($sonera->id, '5', '10'); // Block 1-15
        $this->assertEquals($soneraPhase1->id, $phase1->id);

        $phase2 = $this->bookingService->determinePhase($sonera->id, '2', '25'); // Block 2 Lot 1-57
        $this->assertEquals($soneraPhase1->id, $phase2->id);
        echo "\n✅ Test Case 3: Sonera Phase 1 determination - PASSED";

        // Test Case 4: Sonera Phase 2 determination
        $phase3 = $this->bookingService->determinePhase($sonera->id, '20', '10'); // Block 16-25
        $this->assertEquals($soneraPhase2->id, $phase3->id);

        $phase4 = $this->bookingService->determinePhase($sonera->id, '2', '60'); // Block 2 Lot 58+
        $this->assertEquals($soneraPhase2->id, $phase4->id);
        echo "\n✅ Test Case 4: Sonera Phase 2 determination - PASSED";

        // Test Case 5: Fare calculation
        $farePerPassenger = $this->bookingService->getFarePerPassenger($primera->id, $primeraPhase1->id);
        $this->assertEquals(25.00, $farePerPassenger);

        $totalFare = $this->bookingService->calculateTotalFare($primera->id, $primeraPhase1->id, 3);
        $this->assertEquals(75.00, $totalFare);
        echo "\n✅ Test Case 5: Fare calculation - PASSED";

        // Test Case 6: Complete booking with fare
        $customer = User::create([
            'name' => 'Test Customer',
            'email' => 'customer@test.com',
            'password' => bcrypt('password'),
            'role' => 'customer',
        ]);

        $bookingData = [
            'subdivision_id' => $sonera->id,
            'block_number' => '20',
            'lot_number' => '15',
            'pax' => 4,
        ];

        $result = $this->bookingService->processBookingWithFare($bookingData);

        $this->assertTrue($result['success']);
        $this->assertEquals($soneraPhase2->id, $result['phase']->id);
        $this->assertEquals(30.00, $result['fare_per_passenger']);
        $this->assertEquals(120.00, $result['total_fare']);

        echo "\n✅ Test Case 6: Complete booking with fare - PASSED";
        echo "\n   Phase: {$result['phase']->name}";
        echo "\n   Fare per passenger: ₱{$result['fare_per_passenger']}";
        echo "\n   Total fare: ₱{$result['total_fare']}";

        // Test Case 7: Create actual booking
        $booking = Booking::create($result['booking_data'] + [
            'customer_id' => $customer->id,
            'pickup_location' => 'Test Pickup',
            'dropoff_location' => 'Test Dropoff',
            'remaining_pax' => 4,
            'status' => 'pending',
        ]);

        $this->assertEquals($sonera->id, $booking->subdivision_id);
        $this->assertEquals($soneraPhase2->id, $booking->phase_id);
        $this->assertEquals('20', $booking->block_number);
        $this->assertEquals('15', $booking->lot_number);
        $this->assertEquals(30.00, $booking->fare_per_passenger);
        $this->assertEquals(120.00, $booking->total_fare);

        echo "\n✅ Test Case 7: Actual booking creation - PASSED";

        // Test Case 8: Multi-rider earnings with real fare
        $rider1 = Rider::create(['user_id' => User::factory()->create(['role' => 'rider'])->id, 'capacity' => 2]);
        $rider2 = Rider::create(['user_id' => User::factory()->create(['role' => 'rider'])->id, 'capacity' => 2]);

        // Assign riders
        BookingRider::create([
            'booking_id' => $booking->id,
            'rider_id' => $rider1->id,
            'allocated_seats' => 2,
            'status' => 'accepted',
            'expires_at' => now()->addMinutes(3),
            'earning_amount' => 0, // Initialize with 0
        ]);

        BookingRider::create([
            'booking_id' => $booking->id,
            'rider_id' => $rider2->id,
            'allocated_seats' => 2,
            'status' => 'accepted',
            'expires_at' => now()->addMinutes(3),
            'earning_amount' => 0, // Initialize with 0
        ]);

        // Set booking status to completed before calculating earnings
        $booking->status = 'completed';
        $booking->save();

        // Complete booking and calculate earnings
        $this->bookingService->calculateEarnings($booking);

        $booking->refresh();
        $booking->load('bookingRiders');

        echo "\nDebug earnings calculation:";
        echo "\nBooking status: {$booking->status}";
        echo "\nTotal fare: {$booking->total_fare}";
        echo "\nPlatform fee: {$booking->platform_fee}";
        echo "\nRider earning: {$booking->rider_earning}";

        foreach ($booking->bookingRiders as $br) {
            echo "\nRider {$br->rider_id}: status={$br->status}, earning={$br->earning_amount}";
        }

        $this->assertEquals('completed', $booking->status);
        $this->assertEquals(120.00, $booking->total_fare);
        $this->assertEquals(18.00, $booking->platform_fee); // 15% of 120
        $this->assertEquals(102.00, $booking->rider_earning); // 120 - 18

        // Check rider earnings (each seat = 102 / 4 = 25.5)
        $rider1Earning = $booking->bookingRiders->where('rider_id', $rider1->id)->first()->earning_amount;
        $rider2Earning = $booking->bookingRiders->where('rider_id', $rider2->id)->first()->earning_amount;

        $this->assertEquals(51.00, $rider1Earning); // 2 seats * 25.5
        $this->assertEquals(51.00, $rider2Earning); // 2 seats * 25.5

        echo "\n✅ Test Case 8: Multi-rider earnings with real fare - PASSED";
        echo "\n   Total fare: ₱{$booking->total_fare}";
        echo "\n   Platform fee: ₱{$booking->platform_fee}";
        echo "\n   Rider earnings: ₱{$booking->rider_earning}";
        echo "\n   Rider 1 earning (2 seats): ₱{$rider1Earning}";
        echo "\n   Rider 2 earning (2 seats): ₱{$rider2Earning}";

        echo "\n\n🎉 ALL FARE SYSTEM TESTS COMPLETED SUCCESSFULLY!";
    }

    /**
     * Test Admin fare management endpoints
     */
    public function test_admin_fare_management(): void
    {
        echo "\n🔧 TESTING ADMIN FARE MANAGEMENT\n";

        // Create admin user
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        // Test Case 1: Get all fares
        $response = $this->actingAs($admin)->getJson('/api/admin/fares');
        $response->assertStatus(200);
        $fares = $response->json();
        $this->assertCount(3, $fares); // 3 seeded fares
        echo "\n✅ Test Case 1: Get all fares - PASSED";

        // Test Case 2: Create new fare
        $subdivision = Subdivision::where('name', 'Primera')->first();
        $phase = Phase::where('subdivision_id', $subdivision->id)->first();

        $response = $this->actingAs($admin)->postJson('/api/admin/fares', [
            'subdivision_id' => $subdivision->id,
            'phase_id' => $phase->id,
            'fare_per_passenger' => 28.50,
        ]);

        $response->assertStatus(422); // Should fail - fare already exists
        echo "\n✅ Test Case 2: Prevent duplicate fare - PASSED";

        // Test Case 3: Update existing fare
        $fare = Fare::first();
        $response = $this->actingAs($admin)->putJson("/api/admin/fares/{$fare->id}", [
            'fare_per_passenger' => 27.75,
        ]);

        $response->assertStatus(200);
        $updatedFare = $response->json('fare');
        $this->assertEquals(27.75, $updatedFare['fare_per_passenger']);
        echo "\n✅ Test Case 3: Update fare - PASSED";

        // Test Case 4: Preview fare calculation
        $sonera = Subdivision::where('name', 'Sonera')->first();
        $response = $this->actingAs($admin)->postJson('/api/admin/preview-fare', [
            'subdivision_id' => $sonera->id,
            'block_number' => '20',
            'lot_number' => '15',
            'pax' => 3,
        ]);

        $response->assertStatus(200);
        $preview = $response->json();
        $this->assertEquals('Phase 2', $preview['phase']['name']);
        $this->assertEquals(30.00, $preview['fare_per_passenger']);
        $this->assertEquals(90.00, $preview['total_fare']);
        echo "\n✅ Test Case 4: Preview fare calculation - PASSED";
        echo "\n   Phase: {$preview['phase']['name']}";
        echo "\n   Fare per passenger: {$preview['formatted_fare_per_passenger']}";
        echo "\n   Total fare: {$preview['formatted_total_fare']}";

        echo "\n\n🎉 ALL ADMIN FARE MANAGEMENT TESTS COMPLETED SUCCESSFULLY!";
    }
}
