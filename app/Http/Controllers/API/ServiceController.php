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
            $services = Service::where('is_active', true)->with('specialities')->get();
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

            return $this->sendResponse($providerService, 'Service added successfully');


        } catch (\Throwable $th) {
            Log::error($th->getMessage());
            return $this->sendError('Server Error', $th->getMessage());

        }

    }

    public function addServiceMedia(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'provider_service_id' => 'required|exists:provider_services,id',
            'display_image' => 'file|mimes:jpeg,png,jpg,gif,svg',
            'media' => 'required|array',
            'media.*' => 'file|mimes:jpeg,png,jpg,gif,svg,mp4,mov,avi', // Allowing video files and increasing size limit
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors());
        }

        if ($request->hasFile('media')) {
            try {
                // Handle media file uploads
                $mediaFiles = $request->file('media');
                $mediaUrls = []; // Array to hold URLs of uploaded media

                foreach ($mediaFiles as $mediaFile) {
                    // Store the media file and set its visibility
                    $mediaPath = $mediaFile->store('rtf/providers/media', 'do');
                    Storage::disk('do')->setVisibility($mediaPath, 'public');

                    // Create the ServiceMedia record and store URL
                    $mediaUrls[] = Storage::disk('do')->url($mediaPath);
                    ServiceMedia::create([
                        'url' => end($mediaUrls), // Use the last stored URL
                        'provider_service_id' => $request->provider_service_id,
                        'user_id' => Auth::id(),
                    ]);
                }

                // Handle display image upload
                $displayImageFile = $request->file('display_image'); // Assuming display_image is uploaded separately
                if ($displayImageFile) {
                    $displayImagePath = $displayImageFile->store('rtf/providers/media/services/dp', 'do');
                    Storage::disk('do')->setVisibility($displayImagePath, 'public');

                    // Update provider service display image
                    $providerService = ProviderService::find($request->provider_service_id);
                    if ($providerService) {
                        $providerService->display_image = Storage::disk('do')->url($displayImagePath);
                        $providerService->save();
                    }
                }

                return $this->sendResponse($mediaUrls, 'Media uploaded successfully');
            } catch (\Exception $e) {
                // Handle the error
                return $this->sendError('File upload error', ['message' => $e->getMessage()]);
            }
        }

        return $this->sendError('No media files found.');
    }

    public function addServiceTiming(Request $request)
    {
        // Validate the request data
        $validator = Validator::make($request->all(), [
            'timings' => 'required|array',
            'timings.*.day' => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'timings.*.start_time' => 'required|date_format:H:i',
            'timings.*.close_time' => 'required|date_format:H:i|after:timings.*.start_time',
            'timings.*.provider_service_id' => 'required|exists:provider_services,id',
            'timings.*.is_open' => 'required|boolean',
        ]);
    
        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors());
        }
    
        $responses = [];
    
        try {
            foreach ($request->timings as $timing) {
                // Find the existing opening hour entry
                $openingHour = OpeningHour::where('day', $timing['day'])
                    ->where('provider_service_id', $timing['provider_service_id'])
                    ->first();
    
                if ($openingHour) {
                    // Update the existing entry
                    $openingHour->update([
                        'start_time' => $timing['start_time'],
                        'close_time' => $timing['close_time'],
                        'is_open' => $timing['is_open'],
                    ]);
                    $responses[] = ['id' => $openingHour->id, 'message' => 'Service timing updated successfully!'];
                } else {
                    // Create a new opening hour entry
                    $openingHour = OpeningHour::create([
                        'day' => $timing['day'],
                        'start_time' => $timing['start_time'],
                        'close_time' => $timing['close_time'],
                        'provider_service_id' => $timing['provider_service_id'],
                        'is_open' => $timing['is_open'],
                    ]);
                    $responses[] = ['id' => $openingHour->id, 'message' => 'Service timing added successfully!'];
                }
            }
    
            return $this->sendResponse($responses, message: 'All service timings processed successfully!');
    
        } catch (\Throwable $th) {
            return $this->sendError('Server error.', $th->getMessage());
        }
    }
    


}
