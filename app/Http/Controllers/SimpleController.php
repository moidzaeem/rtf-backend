<?php

namespace App\Http\Controllers;

use App\Http\Controllers\API\BaseController;
use App\Models\User;
use Illuminate\Http\Request;

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
}
