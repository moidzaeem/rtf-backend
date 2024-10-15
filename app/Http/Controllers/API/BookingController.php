<?php

namespace App\Http\Controllers\API;

use App\Models\Booking;
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

            foreach ($request->products as $product) {
                // Assuming you have a pivot table or related model
                $booking->products()->attach($product['product_id'], ['quantity' => $product['quantity']]);
            }
            return $this->sendResponse($booking, 'Successfully Booked');

        } catch (\Throwable $th) {
            return $this->sendError('Booking Error', 'There was an error creating the booking: ' . $th->getMessage());
        }
    }

    public function getUserBookings()
    {
        $userId = Auth::id();

        try {
            $now = now(); // Get the current date and time

            // Fetch all bookings, categorized by upcoming and past
            $upcomingBookings = Booking::where('user_id', $userId)
                ->where('booking_date', '>', $now)
                ->with('products')
                ->get();

            $pastBookings = Booking::where('user_id', $userId)
                ->where('booking_date', '<=', $now)
                ->with('products')
                ->get();

            return $this->sendResponse([
                'upcoming' => $upcomingBookings,
                'past' => $pastBookings,
            ], 'User Bookings');
        } catch (\Throwable $th) {
            \Log::error('Error retrieving user bookings: ' . $th->getMessage());
            return $this->sendError('Could not retrieve user bookings. Please try again later.');
        }
    }

}
