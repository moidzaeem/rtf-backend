<?php

namespace App\Http\Controllers\API;

use App\Models\Booking;
use App\Models\BookingHistory;
use App\Models\Notification;
use Auth;
use Illuminate\Http\Request;
use Validator;
class BookingController extends BaseController
{
    public function booking(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'provider_service_id' => 'required|exists:provider_services,id',
            'booking_date' => 'required|date|after:today',
            'booking_time' => 'required|date_format:H:i',
            'payment' => 'required',
            'products' => 'required|array',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
        ]);

        // Custom validation for booking time in the future
        $validator->after(function ($validator) use ($request) {
            $bookingDate = $request->input('booking_date');
            $bookingTime = $request->input('booking_time');
            $bookingDateTime = \Carbon\Carbon::createFromFormat('Y-m-d H:i', $bookingDate . ' ' . $bookingTime);

            if ($bookingDateTime->isPast()) {
                $validator->errors()->add('booking_time', 'The booking date and time must be in the future.');
            }
        });

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors());
        }

        try {
            $booking = Booking::create([
                'user_id' => Auth::id(),
                'provider_service_id' => $request->provider_service_id,
                'booking_date' => $request->booking_date,
                'booking_time' => $request->booking_time,
                'payment' => $request->payment,
            ]);

            BookingHistory::create([
                'user_id' => Auth::id(),
                'booking_id' => $booking->id,
                'previous_status' => 'none',
                'modified_status' => 'created'
            ]);

            foreach ($request->products as $product) {
                // Assuming you have a pivot table or related model
                $booking->products()->attach($product['product_id'], ['quantity' => $product['quantity']]);
            }

            Notification::create([
                'user_id' => Auth::id(),
                'title' => 'Booking Created',
                'message' => 'Your Booking is created!'
            ]);
            return $this->sendResponse($booking, 'Successfully Booked');

        } catch (\Throwable $th) {
            return $this->sendError('Booking Error', 'There was an error creating the booking: ' . $th->getMessage());
        }
    }

    public function getUserBookings()
    {
        $userId = Auth::id();

        try {
            $now = now();

            // Fetch all bookings in a single query, with eager loading
            $bookings = Booking::where('user_id', $userId)
                ->with(['products', 'providerService'])
                ->get();

            // Split bookings into upcoming and past
            $upcomingBookings = $bookings->where('booking_date', '>', $now);
            $pastBookings = $bookings->where('booking_date', '<=', $now);

            return $this->sendResponse([
                'upcoming' => $upcomingBookings,
                'past' => $pastBookings,
            ], 'User Bookings');
        } catch (\Throwable $th) {
            \Log::error('Error retrieving user bookings: ' . $th->getMessage(), [
                'exception' => $th,
            ]);
            return $this->sendError(error: 'Could not retrieve user bookings. Please try again later.');
        }
    }

    public function cancelBooking(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'booking_id' => 'required|exists:bookings,id',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors());
        }

        try {
            $booking = Booking::findOrFail($request->booking_id);

            // Check if the authenticated user is the owner of the booking
            if ($booking->user_id != Auth::id()) {
                return $this->sendError('Authorization Error', 'You are not authorized to perform this action');
            }

            // Check if booking status is 'completed'
            if ($booking->booking_status === 'completed') {
                return $this->sendError('Cancellation Error', "You can't refund now, the booking is already completed.");
            }

            // Log the booking history
            BookingHistory::create([
                'user_id' => Auth::id(),
                'booking_id' => $request->booking_id,
                'previous_status' => $booking->booking_status,
                'modified_status' => 'cancelled'
            ]);

            // Update the booking status to cancelled
            $booking->booking_status = 'cancelled';
            $booking->save();

            return $this->sendResponse($booking, 'Your booking is cancelled. Your amount will be refunded soon.');

        } catch (\Throwable $th) {
            return $this->sendError('Server Error', [$th->getMessage()]);
        }
    }



}
