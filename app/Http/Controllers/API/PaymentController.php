<?php

namespace App\Http\Controllers\API;

use App\Models\Booking;
use App\Models\PaymentLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Stripe\Customer;
use Stripe\PaymentMethod;
use Stripe\Stripe;
use Validator;

class PaymentController extends BaseController
{
    public function createPaymentMethod(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors());
        }

        Stripe::setApiKey(env('STRIPE_SECRET'));

        // Create a payment method
        try {
            $paymentMethod = PaymentMethod::create([
                'type' => 'card',
                'card' => [
                    'token' => $request->token,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Payment method creation failed: ' . $e->getMessage()], 400);
        }

        try {
            // Find or create the customer
            $user = User::findOrFail(Auth::id());
            $customer = null;

            if (empty($user->stripe_customer_id)) {
                // Create a new customer if not exists
                $customer = Customer::create([
                    'email' => $user->email,
                ]);

                // Save the Stripe customer ID in the user model
                $user->stripe_customer_id = $customer->id;
                $user->save();
            } else {
                // Retrieve existing customer
                $customer = Customer::retrieve($user->stripe_customer_id);
            }

            // Attach the payment method to the customer
            try {
                $paymentMethod->attach(['customer' => $customer->id]);
            } catch (\Exception $e) {
                return response()->json(['error' => 'Failed to attach payment method: ' . $e->getMessage()], 400);
            }

            // Optionally, set the payment method as the default
            $customer->invoice_settings = ['default_payment_method' => $paymentMethod->id];
            $customer->save();

            // Store the payment method details in the database
            $user->paymentMethods()->create([
                'stripe_payment_method_id' => $paymentMethod->id,
                'card_brand' => $paymentMethod->card->brand,
                'last4' => $paymentMethod->card->last4,
            ]);



            return $this->sendResponse($paymentMethod, 'Payment Method added');
        } catch (\Throwable $th) {
            return $this->sendError('Server Error', $th->getMessage());

        }
    }

    public function getUserPaymentMethods()
    {
        try {
            $payments = \App\Models\PaymentMethod::where('user_id', Auth::id())->get();
            return $this->sendResponse($payments, 'Payment Methods');
        } catch (\Throwable $th) {
            return $this->sendError('Server Error', [$th->getMessage()]);
        }
    }

    public function chargeUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'booking_id' => 'required|exists:bookings,id',
            'payment_method_id' => 'required'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors());
        }

        Stripe::setApiKey(env('STRIPE_SECRET'));

        try {
            $user = User::findOrFail(Auth::id());

            // Ensure the user has a valid payment method
            if (empty($user->stripe_customer_id)) {
                return $this->sendError('Customer not found. Please add a payment method first.');
            }

            // Fetch the booking and related products
            $booking = Booking::with(['products', 'user'])->findOrFail($request->booking_id);

            if ($booking->payment_status === 'paid') {
                return $this->sendError('Validation Error', 'You already paid for this :)', 401);
            }

            // Calculate total amount
            $totalAmount = $booking->products->sum(function ($product) {
                return $product->price * $product->pivot->quantity; // Assuming you have a 'price' attribute in your Product model
            });

            \Log::info($booking);



            $paymentIntent = \Stripe\PaymentIntent::create([
                'amount' => $totalAmount * 100, // Amount in cents
                'currency' => 'USD',
                'customer' => $user->stripe_customer_id,
                'payment_method' => $request->payment_method_id ?? $user->default_payment_method_id, // Assuming you've stored this
                'off_session' => true,
                'confirm' => true,
            ]);

            // Update booking payment status
            $booking->payment_status = 'paid';
            $booking->payment = $totalAmount; // Store the payment amount in the booking
            $booking->save();

            $provider = $booking->user; // Get the provider associated with the booking
            if (!$provider->wallet) {
                $provider->wallet()->create(['balance' => $totalAmount]); // Create a wallet if it doesn't exist
            } else {
                $provider->wallet->balance += $totalAmount; // Increment wallet balance
                $provider->wallet->save();
            }



            PaymentLog::create([
                'user_id' => Auth::id(),
                'booking_id' => $request->booking_id,
                'payment_long' => json_encode($paymentIntent)
            ]);

            return $this->sendResponse($paymentIntent, 'Payment successful.');

        } catch (\Stripe\Exception\CardException $e) {
            return response()->json(['error' => 'Card error: ' . $e->getMessage()], 400);
        } catch (\Exception $e) {
            return $this->sendError('An error occurred while processing your payment.', $e->getMessage());
        }

    }

    public function getUserPaymentHistory()
    {
        try {
            $paymentHistory = PaymentLog::where('user_id', Auth::id())
                ->with('booking')
                ->get()
                ->map(function ($payment) {
                    // Decode the payment_long JSON string
                    $paymentDetails = json_decode($payment->payment_long, true);

                    return [
                        'transaction_id' => isset($paymentDetails['id']) ? $paymentDetails['id'] : 'N/A',
                        'amount' => isset($paymentDetails['amount']) ? $paymentDetails['amount'] / 100 : 0, // Convert from cents to dollars
                        'currency' => 'USD', // Assuming all payments are in USD
                        'payment_status' => $payment->booking->payment_status,
                        'booking_id' => $payment->booking->id,
                        'booking_date' => $payment->booking->booking_date,
                        'booking_time' => $payment->booking->booking_time,
                        'created_at' => $payment->created_at->format('Y-m-d H:i:s'),
                    ];
                });

            return $this->sendResponse($paymentHistory, 'Payment Transaction History');
        } catch (\Throwable $th) {
            return $this->sendError('Server Error', $th->getMessage());
        }
    }

}
