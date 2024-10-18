<?php

namespace App\Http\Controllers;

use App\Http\Controllers\API\BaseController;
use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Stripe\Stripe;

class SimpleController extends BaseController
{
    public function verifyEmail($token)
    {
        $user = User::where('verification_token', $token)->first();

        if (!$user) {
            return $this->sendError('Invalid Token.', 'The verification token is invalid or has expired.');
        }

        $user->is_verified = true;
        $user->verification_token = null; // Clear the token
        $user->save();

        return $this->sendResponse([], 'Email verified successfully.');
    }

    public function requestTokenGoogle(Request $request)
    {
        try {
            // Attempt to get the user from Google using the token
            $user = Socialite::driver('google')->stateless()->userFromToken($request->token);
            \Log::info($user->getEmail());

            // Check if the user data was retrieved successfully
            if (!$user) {
                return response(['error' => 'Invalid token or unable to retrieve user data.'], 401);
            }

            // Getting or creating user from DB
            $userFromDb = User::firstOrCreate(
                ['email' => $user->getEmail()],
                [
                    'email_verified_at' => now(),
                    'name' => $user->offsetGet('given_name') ?: '',
                    'role' => 'customer',
                    'password' => bcrypt('12345678')

                ]
            );

            if (!$userFromDb->hasVerifiedEmail()) {
                $userFromDb->email_verified_at = now();  // Mark as verified
                $userFromDb->save();
            }
            // Create a token for the user
            $token = $userFromDb->createToken('MyApp')->plainTextToken;

            // Prepare the success response
            $success = [
                'token' => $token,
                'name' => $userFromDb->name,
                'user' => $userFromDb,
            ];

            return $this->sendResponse($success, 'Login With Google Successfull');

        } catch (\Exception $e) {
            return $this->sendError(['Server Error', [$e->getMessage()]], 401);
        }
    }

    public function webhook(Request $request)
    {
        $payload = $request->getContent();
        $sig_header = $request->header('Stripe-Signature');

        try {
            Stripe::setApiKey(apiKey: env('STRIPE_SECRET'));

            // Verify the webhook signature
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $sig_header,
                env('STRIPE_WEBHOOK_SECRET') // Make sure to set this in your .env file
            );

            // Handle the setup_intent.succeeded event
            if ($event->type === 'setup_intent.succeeded') {
                $setupIntent = $event->data->object; // This contains the Setup Intent details

                // Retrieve the payment method ID
                $paymentMethodId = $setupIntent->payment_method;

                // Retrieve payment method details from Stripe
                $paymentMethod = \Stripe\PaymentMethod::retrieve($paymentMethodId);

                // Get the Stripe customer ID
                $stripeCustomerId = $setupIntent->customer;

                // Find the user associated with the Stripe customer ID
                $user = User::where('stripe_customer_id', $stripeCustomerId)->first();

                if ($user) {
                    // Store the payment method details in the database
                    $user->paymentMethods()->create([
                        'stripe_payment_method_id' => $paymentMethod->id,
                        'card_brand' => $paymentMethod->card->brand,
                        'last4' => $paymentMethod->card->last4,
                    ]);
                } else {
                    \Log::info('User not found for Stripe customer ID: ' . $stripeCustomerId);
                }
            }

            return response()->json(['status' => 'success']);
        } catch (\UnexpectedValueException $e) {
            return response()->json(['error' => 'Invalid payload'], 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            return response()->json(['error' => 'Invalid signature'], 400);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


}
