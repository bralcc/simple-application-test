<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\Booking;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rejects_conflicting_bookings()
    {
        $room = Room::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Existing booking
        Booking::create([
            'room_id' => $room->id,
            'user_id' => $user1->id,
            'start_time' => '2025-06-25 10:00:00',
            'end_time' => '2025-06-25 11:00:00',
        ]);

        $newBookings = [
            [
                'user_id' => $user2->id,
                'start_time' => '2025-06-25 10:30:00',
                'end_time' => '2025-06-25 11:30:00',
            ],
            [
                'user_id' => $user2->id,
                'start_time' => '2025-06-25 12:00:00',
                'end_time' => '2025-06-25 13:00:00',
            ]
        ];

        $service = new BookingService();
        $result = $service->createBatchBookings($room->id, $newBookings);

        $this->assertFalse($result['success']);
        $this->assertCount(1, $result['conflicts']);
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_it_accepts_non_conflicting_bookings()
    {
        $room = Room::factory()->create();
        $user = User::factory()->create();

        $newBookings = [
            [
                'user_id' => $user->id,
                'start_time' => '2025-06-25 09:00:00',
                'end_time' => '2025-06-25 10:00:00',
            ],
            [
                'user_id' => $user->id,
                'start_time' => '2025-06-25 11:00:00',
                'end_time' => '2025-06-25 12:00:00',
            ]
        ];

        $service = new BookingService();
        $result = $service->createBatchBookings($room->id, $newBookings);

        $this->assertTrue($result['success']);

        dd($result['success']);
        $this->assertDatabaseCount('bookings', 2);
    }
}
