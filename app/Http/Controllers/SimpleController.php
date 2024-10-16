<?php

namespace App\Http\Controllers;

use App\Http\Controllers\API\BaseController;
use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

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


}
