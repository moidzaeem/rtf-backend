<?php

namespace App\Http\Controllers\API;

use App\Models\ProviderDetail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Storage;
use Validator;
class ProviderDetailController extends BaseController
{
    public function addProvideDetails(Request $request)
    {
        // Validate the incoming request data
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id', // Ensure it exists in the users table
            'dob' => 'required|date|before:today',
            'address' => 'required|string|max:255',
            'city' => 'string|max:100',
            'country'=>'required',
            'postal_code' => 'nullable|string|max:20',
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'id_card' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'bg_image'=>'image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        // Return validation errors if any
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        try {
            // Convert the date of birth format
            $dob = Carbon::createFromFormat('d-m-Y', $request->dob)->format('Y-m-d');

            // Prepare the provider detail data
            $providerDetailData = [
                'user_id' => $request->user_id,
                'dob' => $dob,
                'address' => $request->address,
                'city' => $request->city,
                'postal_code' => $request->postal_code,
                'country'=>$request->country
            ];

            // Handle profile picture upload
            if ($request->hasFile('profile_picture')) {
                $profilePicturePath = $request->file('profile_picture')->store('rtf/providers/profile_pictures', 'do');
                Storage::disk('do')->setVisibility($profilePicturePath, 'public');
                $providerDetailData['profile_picture'] = Storage::disk('do')->url($profilePicturePath);
            }

            if ($request->hasFile('bg_image')) {
                $bgImage = $request->file('bg_image')->store('rtf/providers/bg_image', 'do');
                Storage::disk('do')->setVisibility($bgImage, 'public');
                $providerDetailData['bg_image'] = Storage::disk('do')->url($bgImage);
            }

            // Handle ID card upload
            if ($request->hasFile('id_card')) {
                $idCardPath = $request->file('id_card')->store('rtf/providers/id_cards', 'do');
                Storage::disk('do')->setVisibility($idCardPath, 'public');
                $providerDetailData['id_card'] = Storage::disk('do')->url($idCardPath);
            }

            // Create or update provider details
            $data = ProviderDetail::updateOrCreate(
                ['user_id' => $request->user_id], // Use user_id to find the existing record
                $providerDetailData
            );

            return $this->sendResponse($data, 'Provider data added successfully.');

        } catch (\Throwable $th) {
            return $this->sendError('Server Error', $th->getMessage());
        }
    }

    public function getProviderDetails(Request $request)
    {
        $providerId = $request->query('provider_id');

        if (!$providerId) {
            return $this->sendError('Provider ID is required.');
        }

        try {
            $data = User::where('id', $providerId)
                ->with(['providerDetails:id,user_id,dob,address,city,postal_code,country,bg_image', 'providerService:id,user_id,service_id,description,price,is_active,display_image'])
                ->select('id', 'name', 'email', 'phone_no', 'role') // Select only useful fields
                ->first();

            if (!$data) {
                return $this->sendError('Provider not found.');
            }

            return $this->sendResponse($data, 'Provider Details');

        } catch (\Throwable $th) {
            return $this->sendError('Server Error', [$th->getMessage()]);
        }
    }

}
