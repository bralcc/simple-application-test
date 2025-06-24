<?php

namespace App\Services;

use App\Models\Booking;
use Exception;
use Illuminate\Support\Facades\DB;

class BookingService
{
    public function createBatchBookings(int $roomId, array $bookings)
    {
        try {
            DB::transaction(function () use ($bookings, $roomId) {
                $this->checkInternalOverlaps($bookings);

                foreach ($bookings as $booking) {
                    $this->checkOverlapsInDB($booking, $roomId);
                }

                foreach ($bookings as $booking) {
                    Booking::create(array_merge($booking, ['room_id' => $roomId]));
                }
            });
            return ['success' => true, 'conflicts' => []];
        } catch (Exception $e) {
            return [
                'success' => false,
                'conflicts' => [$e->getMessage()]
            ];
        }
    }

    private function checkInternalOverlaps(array $bookings): void
    {
        // sort bookings by start time
        $sortedBookings = collect($bookings)->sortBy('start_time')->values();

        // pointer
        for ($i = 1; $i < $sortedBookings->count(); $i++) {
            $prev = $sortedBookings[$i - 1];
            $curr = $sortedBookings[$i];

            // throw error if current start time is smaller than previous
            if ($curr['start_time'] < $prev['end_time']) {
                throw new Exception("Bookings overlap within batch");
            }
        }
    }

    private function checkOverlapsInDB(array $booking, int $roomId): void
    {
        $conflictingBooking = Booking::where('room_id', $roomId)
            ->where(function ($query) use ($booking) {
                $query->where('start_time', '<', $booking['end_time'])
                    ->where('end_time', '>', $booking['start_time']);
            })->first();

        // if conflicting booking exists, log into conflicts
        if ($conflictingBooking) {
            throw new Exception("Bookings overlap with bookings in DB.");
        }
    }
}
