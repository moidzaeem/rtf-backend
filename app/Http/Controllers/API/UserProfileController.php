<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Validator;
class UserProfileController extends BaseController
{
    public function update(Request $request)
    {
        // Validate the incoming request data
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255|unique:users,email,' . auth()->id(),
            'password' => 'sometimes|string|min:8',
            'phone_no' => 'sometimes|string|max:15',
            'profile_image' => 'sometimes|image|mimes:jpg,jpeg,png|max:2048', // 2MB max
        ]);

        // Return validation errors if any
        if ($validator->fails()) {
            return $this->sendError('Server Error',$validator->errors());
        }

        try {
            $user = auth()->user();
            // Prepare validated data for updating
            $validatedData = $validator->validated();

            // Handle profile picture upload
            if ($request->hasFile('profile_image')) {
                $profilePicturePath = $request->file('profile_image')->store('rtf/users/profile_images', 'do');
                Storage::disk('do')->setVisibility($profilePicturePath, 'public');
                $validatedData['profile_image'] = Storage::disk('do')->url($profilePicturePath);
                Log::info('Profile Image Uploaded:', [$request->file('profile_image')->getClientOriginalName()]);
            }

            // Hash the password if it's being updated
            if (isset($validatedData['password'])) {
                $validatedData['password'] = Hash::make($validatedData['password']);
            }

            // Log user before update
            Log::info('User Before Update:', $user->toArray());

            // Update the user's profile
            $user->update(array_filter($validatedData)); // Use array_filter to skip null values

            // Log user after update
            Log::info('User After Update:', $user->fresh()->toArray());
            return $this->sendResponse($user, 'Profile Updated Successfully.');
        } catch (\Throwable $th) {
            Log::error('Update Error:', [$th->getMessage()]);
            return $this->sendError('Server Error', $th->getMessage());
        }
    }
}
