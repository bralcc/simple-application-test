<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Support\Facades\DB;
use Exception;

class BookingService
{
    private $conflicts = [];

    public function createBatchBookings(int $roomId, array $bookings): array
    {
        try {
            // use DB transaction to roll back when conflict
            DB::transaction(
                function () use ($bookings, $roomId) {
                    // check overlaps submitted bookings
                    $this->checkOverlaps($bookings);

                    //check overlaps in db
                    foreach ($bookings as $booking) {
                        $this->checkOverlapsInDB($booking, $roomId);
                    }

                    // insert all bookings if no exception is thrown
                    foreach ($bookings as $booking) {
                        //lets pretend create method exists
                        Booking::create($booking);
                    }
                }
            );
            return ['success' => true, 'conflicts' => $this->conflicts];
        } catch (Exception $e) {
            // dd($e->getMessage());
            return ['success' => false, 'conflicts' => $this->conflicts, 'error' => $e->getMessage()];
        }
    }

    private function checkOverlaps(array $bookings): bool
    {
        // sort bookings by start time
        $sortedBookings = collect($bookings)->sortBy('start_time')->values();

        // pointer
        for ($i = 1; $i < $sortedBookings->count(); $i++) {
            $prev = $sortedBookings[$i - 1];
            $curr = $sortedBookings[$i];


            // throw error if current start time is smaller than previous
            if ($curr['start_time'] < $prev['end_time']) {

                // log conflicting bookings 
                $this->conflicts[] = [
                    'booking_id1' => $prev['id'],
                    'start_time1' => $prev['start_time'],
                    'end_time1' => $prev['end_time'],
                    'booking_id2' => $curr['id'],
                    'start_time1' => $curr['start_time'],
                    'end_time1' => $curr['end_time'],
                    'error' => 'Bookings overlap'
                ];

                throw new Exception("Bookings overlap within batch");
            }
        }

        // if nothing overlaps return true
        return true;
    }

    private function checkOverlapsInDB(array $booking, int $roomId): bool
    {
        $conflictingBooking = Booking::where('room_id', $roomId)
            ->where(function ($query) use ($booking) {
                $query->whereBetween('start_time', [$booking['start_time'], $booking['end_time']])
                    ->orWhereBetween('end_time', [$booking['start_time'], $booking['end_time']])
                    ->orWhere(function ($query) use ($booking) {
                        $query->where('start_time', '<=', $booking['start_time'])
                            ->where('end_time', '>=', $booking['end_time']);
                    });
            })->first();

        // if conflicting booking exists, log into conflicts
        if ($conflictingBooking) {
            $this->conflicts[] = [
                'new_booking' => $booking,
                'conflicting_booking' => [
                    'id' => $conflictingBooking->id,
                    'room_id' => $conflictingBooking->room_id,
                    'start_time' => $conflictingBooking->start_time,
                    'end_time' => $conflictingBooking->end_time
                ],
                'error' => 'Booking overlaps with existing one'
            ];
            throw new Exception("Bookings overlap with bookings in DB.");
        }

        // if nothing overlaps return true
        return true;
    }
}
