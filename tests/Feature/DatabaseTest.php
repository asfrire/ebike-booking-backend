<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Rider;
use App\Models\Booking;
use App\Models\BookingRider;

class DatabaseTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test database tables exist and relationships work
     */
    public function test_database_tables_and_relationships(): void
    {
        // Create users with different roles
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $riderUser = User::create([
            'name' => 'Rider User',
            'email' => 'rider@test.com',
            'password' => bcrypt('password'),
            'role' => 'rider',
        ]);

        $customerUser = User::create([
            'name' => 'Customer User',
            'email' => 'customer@test.com',
            'password' => bcrypt('password'),
            'role' => 'customer',
        ]);

        // Create rider profile
        $rider = Rider::create([
            'user_id' => $riderUser->id,
            'is_online' => true,
            'queue_position' => 1,
            'capacity' => 3,
        ]);

        // Create booking
        $booking = Booking::create([
            'customer_id' => $customerUser->id,
            'pickup_location' => '123 Main St',
            'dropoff_location' => '456 Oak Ave',
            'pax' => 5,
            'remaining_pax' => 5,
            'status' => 'pending',
        ]);

        // Create booking rider assignment
        $bookingRider = BookingRider::create([
            'booking_id' => $booking->id,
            'rider_id' => $rider->id,
            'allocated_seats' => 3,
            'status' => 'assigned',
            'expires_at' => now()->addMinutes(3),
        ]);

        // Test relationships
        $this->assertInstanceOf(User::class, $rider->user);
        $this->assertEquals('rider@test.com', $rider->user->email);
        
        $this->assertInstanceOf(User::class, $booking->customer);
        $this->assertEquals('customer@test.com', $booking->customer->email);
        
        $this->assertInstanceOf(Booking::class, $bookingRider->booking);
        $this->assertInstanceOf(Rider::class, $bookingRider->rider);

        // Test role methods
        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($riderUser->isRider());
        $this->assertTrue($customerUser->isCustomer());

        // Test data integrity
        $this->assertEquals(5, $booking->pax);
        $this->assertEquals(5, $booking->remaining_pax);
        $this->assertEquals('pending', $booking->status);
        
        $this->assertEquals(3, $rider->capacity);
        $this->assertTrue($rider->is_online);
        $this->assertEquals(1, $rider->queue_position);

        $this->assertEquals(3, $bookingRider->allocated_seats);
        $this->assertEquals('assigned', $bookingRider->status);

        echo "\n✅ Database tables created successfully!";
        echo "\n✅ All relationships working correctly!";
        echo "\n✅ Data integrity verified!";
    }
}
