<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\OpeningHour;
use App\Models\ProviderService;
use App\Models\Service;
use App\Models\ServiceMedia;
use App\Models\ServiceSpeciality;
use Auth;
use Illuminate\Http\Request;
use Log;
use Storage;
use Validator;
class ServiceController extends BaseController
{
    public function getAllServices()
    {
        try {
            $services = Service::where('is_active', operator: true)->with('specialities')->get();
            return $this->sendResponse($services, 'All services');
        } catch (\Throwable $th) {
            return $this->sendError('Server Error', $th->getMessage());
        }
    }

    public function addServiceToProvider(Request $request)
    {
        $user = Auth::user();
        if ($user->role === 'customer') {
            return $this->sendError('Unauthorised', ['You are unauthorise to perform this action'], 401);
        }

        $validator = Validator::make($request->all(), [
            'service_id' => 'required|exists:services,id',
            'speciality_ids' => 'array',
            'speciality_ids.*' => 'exists:specialities,id',
            'price' => 'required',
            'description' => 'sometimes|string|max:255' // Optional max length
        ]);


        if ($validator->fails()) {
            return $this->sendError('Server Error', $validator->errors());
        }

        try {

            $providerService = ProviderService::create([
                'service_id' => $request->service_id,
                'price' => $request->price,
                'description' => $request->description,
                'user_id' => Auth::id(),
            ]);
            foreach ($request->speciality_ids as $key => $value) {
                ServiceSpeciality::create([
                    'user_id' => Auth::id(),
                    'provider_service_id' => $providerService->id,
                    'speciality_id' => $value
                ]);
            }

            return $this->sendResponse($providerService, ['Service added successfully']);


        } catch (\Throwable $th) {
            Log::error($th->getMessage());
            return $this->sendError('Server Error', $th->getMessage());

        }

    }

    public function addServiceMedia(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'provider_service_id' => 'required|exists:provider_services,id',
            'media' => 'required|array',
            'media.*' => 'file|mimes:jpeg,png,jpg,gif,svg,mp4,mov,avi', // Allowing video files and increasing size limit
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors());
        }

        if ($request->hasFile('media')) {
            try {
                $mediaPaths = []; // Array to hold the paths of uploaded media

                foreach ($request->file('media') as $mediaFile) {
                    // Store the media file and set its visibility
                    $mediaPath = $mediaFile->store('rtf/providers/media', 'do');
                    Storage::disk('do')->setVisibility($mediaPath, 'public');

                    // Create the ServiceMedia record
                    $media = ServiceMedia::create([
                        'url' => Storage::disk('do')->url($mediaPath),
                        'provider_service_id' => $request->provider_service_id,
                        'user_id' => Auth::id(),
                    ]);
                }

                return $this->sendResponse($media, 'Media uploaded successfully');
            } catch (\Exception $e) {
                // Handle the error (e.g., log it or return a response)
                return $this->sendError('File upload error', ['message' => $e->getMessage()]);
            }
        }

        return $this->sendError('No media files found.');
    }

    public function addServiceTiming(Request $request)
    {
        // Validate the request data
        $validator = Validator::make($request->all(), [
            'day' => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'start_time' => 'required|date_format:H:i',
            'close_time' => 'required|date_format:H:i|after:start_time',
            'provider_service_id' => 'required|exists:provider_services,id',
            'is_open'=>'required|boolean'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors());
        }

        try {

            // Find the existing opening hour entry
            $openingHour = OpeningHour::where('day', $request->day)
                ->where('provider_service_id', $request->provider_service_id)
                ->first();

            if ($openingHour) {
                // Update the existing entry
                $openingHour->update([
                    'start_time' => $request->start_time,
                    'close_time' => $request->close_time,
                    'is_open'=>$request->is_open
                ]);
                return $this->sendResponse($openingHour, message: 'Service timing updated successfully!');

            } else {
                // Create a new opening hour entry
                $openingHour = OpeningHour::create([
                    'day' => $request->day,
                    'start_time' => $request->start_time,
                    'close_time' => $request->close_time,
                    'provider_service_id' => $request->provider_service_id,
                    'is_open'=>$request->is_open
                ]);

                return $this->sendResponse($openingHour, message: 'Service timing added successfully!');

            }
        } catch (\Throwable $th) {
            return $this->sendError('Server error.', $th->getMessage());

        }
    }


}
