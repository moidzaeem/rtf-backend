<?php

namespace App\Http\Controllers\API;

use App\Models\Booking;
use App\Models\BookingHistory;
use App\Models\Notification;
use App\Models\PaymentLog;
use App\Models\ProviderService;
use App\Models\User;
use Auth;
use DB;
use Illuminate\Http\Request;
use Stripe\PaymentIntent;
use Stripe\Stripe;
use Validator;
use Stripe\StripeClient;

class BookingController extends BaseController
{
    public function booking(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'provider_service_id' => 'required|exists:provider_services,id',
            'booking_date' => 'required|date|after:today',
            'booking_time' => 'required|date_format:H:i',
            'payment_method_id' => 'nullable', // Now include payment method ID in validation
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

        // need to check booking type deposite amount
        DB::beginTransaction(); // Start transaction

        $isCOD = false;

        try {

            $providerService = ProviderService::findOrFail($request->provider_service_id);
            $depositeType = $providerService->deposite_type;
            if ($depositeType === 'full' || $depositeType === 'percentage') {
                if (!$request->payment_method_id) {
                    return $this->sendError('Payment Method Error', ['Payment Method is required']);

                }
            } else {
                $isCOD = true;
            }

            $booking = Booking::create([
                'user_id' => Auth::id(),
                'provider_service_id' => $request->provider_service_id,
                'booking_date' => $request->booking_date,
                'booking_time' => $request->booking_time,
                'payment_status' => 'pending', // Set initial payment status
                'tax_amount' => $request->tax_amount ?? '0',
                'payment' => $request->payment,
                'payment_method' => $depositeType . ' ' . ($providerService->deposite_percentage ?? '0') // Corrected line
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

            $totalAmount = $booking->products->sum(function ($product) {
                return $product->price * $product->pivot->quantity;
            }) + floatval($request->tax_amount ?? 0);

            // Determine payment amount based on deposit type
            $paymentAmount = 0;
            if ($depositeType === 'full') {
                $paymentAmount = $totalAmount; // Charge full amount
            } elseif ($depositeType === 'percentage') {
                // Assuming there's a percentage field in provider_service to indicate the deposit percentage
                $percentage = $providerService->deposite_percentage ?? 0; // Default to 0 if not set
                $paymentAmount = ($totalAmount * $percentage) / 100; // Calculate percentage
            }

            // Charge the user
            Stripe::setApiKey(env('STRIPE_SECRET'));
            $user = User::findOrFail(Auth::id());

            // Ensure the user has a valid payment method
            if (empty($user->stripe_customer_id)) {
                return $this->sendError('Customer not found. Please add a payment method first.');
            }
            $provider = $booking->providerService->user;

            if (!$isCOD) {
                $paymentIntent = PaymentIntent::create([
                    'amount' => $totalAmount * 100, // Amount in cents
                    'currency' => 'USD',
                    'customer' => $user->stripe_customer_id,
                    'payment_method' => $request->payment_method_id,
                    // ['metadata' => ['tax_transaction' => $stripeTax['id']]],
                    'off_session' => true,
                    'confirm' => true,
                ]);

                // Update booking payment status
                $booking->payment_status = 'paid';
                $booking->payment = $totalAmount; // Store the payment amount in the booking
                $booking->save();

                // Update provider's wallet
                $provider = $booking->providerService->user; // Get the provider associated with the booking

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

                $wallet = $provider->wallet ?? $provider->wallet()->create(['balance' => 0]);
                $wallet->increment('balance', $totalAmount - $booking->tax_amount);



            } else {
                // Create notifications
                Notification::create([
                    'user_id' => Auth::id(),
                    'title' => 'Payment Pending',
                    'message' => "Your payment of " . ($totalAmount) . " is pending (COD)."
                ]);

                Notification::create([
                    'user_id' => $provider->id,
                    'title' => 'Payment Successful',
                    'message' => "You received a booking of total amount " . ($totalAmount) . " for Booking which is COD" . $booking->id
                ]);
            }



            DB::commit(); // Commit transaction
            return $this->sendResponse($booking, 'Successfully booked.');

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
        $now = now();

        try {
            // Check if the user is a provider and retrieve the provider service
            if (Auth::user()->role === 'provider') {
                $providerService = ProviderService::where('user_id', $userId)->first();

                if (!$providerService) {
                    return $this->sendError('Provider service not found.');
                }

                $serviceId = $providerService->id;
                $query = Booking::where('provider_service_id', $serviceId);
            } else {
                $query = Booking::where('user_id', $userId);
            }

            // Fetch bookings with related models and sort
            $bookings = $query->with(['products', 'providerService'])
                ->get()
                ->sortByDesc('booking_date'); // Sort bookings by booking_date descending

            // Split bookings into upcoming and past
            $upcomingBookings = $bookings->filter(fn($booking) => $booking->booking_date > $now)->values()->toArray();
            $pastBookings = $bookings->filter(fn($booking) => $booking->booking_date <= $now)->values()->toArray();

            return $this->sendResponse([
                'upcoming' => $upcomingBookings,
                'past' => $pastBookings,
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


    public function receipt(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'booking_id' => 'required|exists:bookings,id',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors());
        }

        try {
            $booking = Booking::with(['products', 'providerService'])->find($request->booking_id);

            $totalPrice = $booking->products->sum(function ($product) {
                return $product->price * $product->pivot->quantity;
            });

            // Prepare the response data
            $responseData = [
                'id' => $booking->id,
                'booking_date' => $booking->booking_date,
                'name' => $booking->providerService->user->name,
                'booking_time' => $booking->booking_time,
                'payment_status' => $booking->payment_status,
                'tax_amount' => $booking->tax_amount,
                'payment' => $booking->payment,
                'item_total' => $totalPrice,
                'booking_status' => $booking->booking_status,
                'display_image' => $booking->providerService->display_image,
                'payment_method' => $booking->payment_method,
                'products' => $booking->products->map(function ($product) {
                    return [
                        'id' => $product->id,
                        'name' => $product->name,
                        'price' => $product->price,
                        'quantity' => $product->pivot->quantity,
                        'image' => $product->image,
                    ];
                }),
            ];
            return $this->sendResponse($responseData, 'Booking Receipt Data');

        } catch (\Throwable $th) {
            return $this->sendError('Server Error', [$th->getMessage()]);
        }

    }


}
