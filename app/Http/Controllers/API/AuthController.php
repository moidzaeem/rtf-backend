<?php

namespace App\Http\Controllers\API;

use App\Mail\VerificationEmail;
use App\Models\SubscriptionProvider;
use App\Models\User;
use Auth;
use Illuminate\Support\Facades\Mail;
use Validator;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Stripe\Stripe;
use Stripe\Customer;
class AuthController extends BaseController
{
    /**
     * Login api
     *
     * @return \Illuminate\Http\Response
     */
    public function login(Request $request)
    {
        if (Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
            $user = Auth::user();
            $success['token'] = $user->createToken('MyApp')->plainTextToken;
            $success['name'] = $user->name;
            $success['user'] = $user;
            $success['provider_subscriptions'] = SubscriptionProvider::where('user_id', $user->id)->get();
            return $this->sendResponse($success, 'User login successfully.');
        } else {
            return $this->sendError('Unauthorised.', ['error' => 'Unauthorised']);
        }
    }

    /**
     * Register api
     *
     * @return \Illuminate\Http\Response
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'email' => 'required|email',
            'password' => 'required',
            'c_password' => 'required|same:password',
            'role' => 'required'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $input = $request->all();
        // Check if user already exists
        if (User::where('email', $input['email'])->exists()) {
            return $this->sendError('User Already Exists.', 'A User with this email address is already registered.', 500);
        }
        try {
            $input['password'] = bcrypt($input['password']);
            $input['verification_token'] = Str::random(60); // Generate a random verification token
            $user = User::create($input);
            \Log::info($user);
            $emailSent = Mail::to($user->email)->send(new VerificationEmail($user));
            \Log::info('Email Sent');
            $success['token'] = $user->createToken('MyApp')->plainTextToken;
            $success['name'] = $user->name;
            $success['user'] = $user;

            Stripe::setApiKey(env('STRIPE_SECRET'));
            // Create a Stripe customer
            $customer = Customer::create([
                'email' => $user->email,
            ]);

            $user->stripe_customer_id = $customer->id;
            $user->save();


            return $this->sendResponse($success, 'User register successfully.');
        } catch (\Throwable $th) {
            return $this->sendError('Server Error', $th->getMessage());
        }
    }
}
