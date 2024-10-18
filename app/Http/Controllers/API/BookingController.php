<?php

namespace App\Http\Controllers\API;

use App\Models\Booking;
use App\Models\BookingHistory;
use App\Models\Notification;
use App\Models\PaymentLog;
use App\Models\User;
use Auth;
use DB;
use Illuminate\Http\Request;
use Stripe\PaymentIntent;
use Stripe\Stripe;
use Validator;
class BookingController extends BaseController
{
    public function booking(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'provider_service_id' => 'required|exists:provider_services,id',
            'booking_date' => 'required|date|after:today',
            'booking_time' => 'required|date_format:H:i',
            'payment_method_id' => 'required', // Now include payment method ID in validation
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

        DB::beginTransaction(); // Start transaction
        try {
            $booking = Booking::create([
                'user_id' => Auth::id(),
                'provider_service_id' => $request->provider_service_id,
                'booking_date' => $request->booking_date,
                'booking_time' => $request->booking_time,
                'payment_status' => 'pending', // Set initial payment staatus
                'payment'=>$request->payment
            ]);

            BookingHistory::create([
                'user_id' => Auth::id(),
                'booking_id' => $booking->id,
                'previous_status' => 'none',
                'modified_status' => 'created'
            ]);

            foreach ($request->products as $product) {
                $booking->products()->attach($product['product_id'], ['quantity' => $product['quantity']]);
            }

            // Calculate total amount
            $totalAmount = $booking->products->sum(function ($product) {
                return $product->price * $product->pivot->quantity; // Assuming you have a 'price' attribute in your Product model
            });

            // Charge the user
            Stripe::setApiKey(apiKey: env('STRIPE_SECRET'));
            $user = User::findOrFail(Auth::id());

            // Ensure the user has a valid payment method
            if (empty($user->stripe_customer_id)) {
                return $this->sendError('Customer not found. Please add a payment method first.');
            }

            $paymentIntent = PaymentIntent::create([
                'amount' => $totalAmount * 100, // Amount in cents
                'currency' => 'USD',
                'customer' => $user->stripe_customer_id,
                'payment_method' => $request->payment_method_id,
                'off_session' => true,
                'confirm' => true,
            ]);

            // Update booking payment status
            $booking->payment_status = 'paid';
            $booking->payment = $totalAmount; // Store the payment amount in the booking
            $booking->save();

            // Update provider's wallet
            $provider = $booking->providerService->user; // Get the provider associated with the booking
            if (!$provider->wallet) {
                $provider->wallet()->create(['balance' => $totalAmount]); // Create a wallet if it doesn't exist
            } else {
                $provider->wallet->balance += $totalAmount; // Increment wallet balance
                $provider->wallet->save();
            }

            // Log the payment
            PaymentLog::create([
                'user_id' => Auth::id(),
                'booking_id' => $booking->id,
                'payment_long' => json_encode($paymentIntent)
            ]);

            // Create notifications
            Notification::create([
                'user_id' => Auth::id(),
                'title' => 'Payment Successful',
                'message' => "Your payment of " . ($totalAmount) . " is successful."
            ]);

            Notification::create([
                'user_id' => $provider->id,
                'title' => 'Payment Successful',
                'message' => "You received a payment of " . ($totalAmount) . " for Booking " . $booking->id
            ]);

            DB::commit(); // Commit transaction
            return $this->sendResponse($booking, 'Successfully booked and paid.');

        } catch (\Stripe\Exception\CardException $e) {
            DB::rollBack(); // Rollback transaction on error
            return response()->json(['error' => 'Card error: ' . $e->getMessage()], 400);
        } catch (\Exception $e) {
            DB::rollBack(); // Rollback transaction on error
            return $this->sendError('An error occurred while processing your payment.', $e->getMessage());
        }
    }


    public function getUserBookings()
    {
        $userId = Auth::id();

        try {
            $now = now();

            // Fetch all bookings in a single query with eager loading
            $bookings = Booking::where('user_id', $userId)
                ->with(['products', 'providerService'])
                ->get();

            // Split bookings into upcoming and past
            $upcomingBookings = $bookings->filter(function ($booking) use ($now) {
                return $booking->booking_date > $now;
            })->values()->toArray(); // Use values() to reindex the array

            $pastBookings = $bookings->filter(function ($booking) use ($now) {
                return $booking->booking_date <= $now;
            })->values()->toArray(); // Use values() to reindex the array

            return $this->sendResponse([
                'upcoming' => $upcomingBookings, // Now an indexed array
                'past' => $pastBookings, // Also an indexed array
            ], 'User Bookings');
        } catch (\Throwable $th) {
            \Log::error('Error retrieving user bookings: ' . $th->getMessage(), [
                'exception' => $th,
            ]);
            return $this->sendError('Could not retrieve user bookings. Please try again later.');
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
