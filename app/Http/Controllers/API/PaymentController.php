<?php

namespace App\Http\Controllers\API;

use App\Models\Booking;
use App\Models\Notification;
use App\Models\PaymentLog;
use App\Models\PaymentMethod as LocalPaymentMethod;
use App\Models\SubscriptionProvider;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Stripe\Customer;
use Stripe\Invoice;
use Stripe\PaymentIntent;
use Stripe\PaymentMethod;
use Stripe\SetupIntent;
use Stripe\Stripe;
use TaxJar;
use Validator;
use Stripe\Subscription;

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
            $booking = Booking::with(['products', 'user', 'providerService'])->findOrFail($request->booking_id);

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

            $provider = $booking->providerService->user; // Get the provider associated with the booking
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

            Notification::create([
                'user_id' => Auth::id(),
                'title' => 'Payment Successful',
                'message' => "Your Payment " . ($totalAmount) . " is successful"
            ]);

            Notification::create([
                'user_id' => $booking->providerService->user_id,
                'title' => 'Payment Successful',
                'message' => "Your received a payment " . ($totalAmount) . " against Booking " . $request->booking_id
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

    public function createSetupIntent(Request $request)
    {
        $user = Auth::user();
        Stripe::setApiKey(env('STRIPE_SECRET'));

        try {
            if (empty($user->stripe_customer_id)) {
                $customer = Customer::create([
                    'email' => $user->email,
                ]);
                $user->stripe_customer_id = $customer->id;
                $user->save();
            }

            // Create a SetupIntent
            $setupIntent = SetupIntent::create([
                'payment_method_types' => ['card'], // Specify payment method types
                'customer' => $user->stripe_customer_id, // Attach the customer ID
            ]);

            // Properly create the data array
            $data = [
                'client_secret' => $setupIntent->client_secret,
            ];

            return $this->sendResponse($data, 'Client secret retrieved successfully.');

        } catch (\Exception $e) {
            return $this->sendError('Server Error', $e->getMessage());
        }

    }

    public function calculateTax(Request $request)
    {
        $amount = $request->input('amount'); // Amount before tax
        $zip = $request->input('zip'); // Destination ZIP code
        $state = $request->input('state'); // Optional state

        // Initialize the TaxJar client
        $client = new TaxJar\Client('12f81937edc370f68b962bf4381dc88e');

        // Prepare parameters for the tax calculation
        $params = [
            'amount' => $amount,
            'shipping' => 0, // Include shipping amount if applicable
            'to_zip' => $zip,
            'to_country' => 'US', // Default country
        ];

        if ($state) {
            $params['to_state'] = $state;
        }

        try {
            // Calculate the tax
            $taxData = $client->taxForOrder($params);

            return $this->sendResponse($taxData, 'Tax caclulated');

        } catch (\TaxJar\Exception $e) {
            return $this->sendError('TaxJar API Error', [$e->getMessage()], 500);
        }
    }

    public function removePaymentMethod(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'payment_method_id' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors());
        }

        try {
            Stripe::setApiKey(env('STRIPE_SECRET'));
            // Detach the payment method
            $paymentMethod = PaymentMethod::retrieve($request->payment_method_id);
            $paymentMethod->detach();

            LocalPaymentMethod::where('stripe_payment_method_id', $request->payment_method_id)->delete();


            return $this->sendResponse([], 'Payment method removed');
        } catch (\Exception $e) {
            return $this->sendError('Server Error', $e->getMessage());
        }
    }

    public function createProviderSubscriptions()
{
    $user = Auth::user();

    if ($user->role !== 'provider') {
        return $this->sendError('Role Error', 'This is for providers only');
    }

    // Check for existing subscriptions
    $existingSubscription = SubscriptionProvider::where('user_id', $user->id)->first();

    // If an active subscription exists, return an error message
    if ($existingSubscription && $existingSubscription->status === 'active') {
        return $this->sendError('Subscription Error', 'You already have an active subscription.');
    }

    try {
        Stripe::setApiKey(env('STRIPE_SECRET'));

        // Ensure the user has a Stripe customer ID
        if (!$user->stripe_customer_id) {
            $customer = Customer::create([
                'email' => $user->email,
            ]);
            $user->update(['stripe_customer_id' => $customer->id]);
        }

        // Check if there's a pending subscription
        if ($existingSubscription && $existingSubscription->status === 'incomplete') {
            // Retrieve the existing subscription
            $stripeSubscription = Subscription::retrieve($existingSubscription->stripe_subscription_id, ['expand' => ['latest_invoice.payment_intent']]);
            
            // Check if the latest_invoice is available
            if (isset($stripeSubscription->latest_invoice)) {
                $latestInvoiceId = $stripeSubscription->latest_invoice; // This is an ID
                $latestInvoice = Invoice::retrieve($latestInvoiceId, ['expand' => ['payment_intent']]);

                // Check for the payment intent
                if (isset($latestInvoice->payment_intent)) {
                    $paymentIntent = PaymentIntent::retrieve($latestInvoice->payment_intent);

                    // Check if payment method is available
                    if (!$paymentIntent->payment_method) {
                        return $this->sendResponse(
                            [
                                'payment_intent_client_secret' => $paymentIntent->client_secret,
                            ],
                            'No payment method available. Please add one to complete the subscription.'
                        );
                    }

                    // Confirm the payment intent
                    try {
                        $paymentIntent->confirm();

                        // Check the status after confirming
                        if ($paymentIntent->status === 'succeeded') {
                            // Update your local subscription status
                            $existingSubscription->update(['status' => 'active']);
                            return $this->sendResponse(
                                [
                                    'subscription_id' => $stripeSubscription->id,
                                    'message' => 'Your pending payment has been processed successfully.'
                                ],
                                'Payment succeeded.'
                            );
                        } else {
                            return $this->sendError('Payment Error', 'Payment not successful. Current status: ' . $paymentIntent->status);
                        }
                    } catch (\Exception $e) {
                        return $this->sendError('Payment Confirmation Error', $e->getMessage());
                    }
                } else {
                    return $this->sendError('Payment Error', 'No payment intent found for the pending subscription.');
                }
            } else {
                return $this->sendError('Subscription Error', 'No latest invoice found for the subscription.');
            }
        }

        // Create a new subscription if none exists
        $subscription = Subscription::create([
            'customer' => $user->stripe_customer_id,
            'items' => [['price' => env('STRIPE_PLAN_ID')]],
            'payment_behavior' => 'default_incomplete',
            'expand' => ['latest_invoice.payment_intent'],
        ]);

        if (isset($subscription->latest_invoice)) {
            $paymentIntent = $subscription->latest_invoice->payment_intent;

            // Store the new subscription in your local database
            SubscriptionProvider::create([
                'user_id' => $user->id,
                'stripe_subscription_id' => $subscription->id,
                'status' => $subscription->status,
            ]);

            // Return response with subscription ID and payment intent
            return $this->sendResponse(
                [
                    'subscription_id' => $subscription->id,
                    'payment_intent_client_secret' => $paymentIntent->client_secret,
                ],
                'Subscription created, awaiting payment method.'
            );
        } else {
            return $this->sendError('Subscription Error', 'No latest invoice found after creating the subscription.');
        }

    } catch (\Exception $e) {
        return $this->sendError('Server Error', $e->getMessage(), 500);
    }
}





}
