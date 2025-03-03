<?php

namespace App\Http\Controllers\API;

use App\Models\ServiceMedia;
use Illuminate\Http\Request;

class FeedController extends BaseController
{

    public function randomFeeds(Request $request)
    {
        $count = $request->query('count', 25); // Default to 25 if not provided
        $serviceId = $request->query('service_id');
        try {
            // Build the query for fetching random media
            $query = ServiceMedia::with(['providerService.user', 'providerService.providerDetails', 'providerService.service', 'providerService.openHours']);

            // If a category ID is provided, filter by category
            if ($serviceId) {
                $query->whereHas('providerService.service', function ($query) use ($serviceId) {
                    $query->where('id', $serviceId);
                });
            }

            $query->where('is_approved', true);

            // Fetch random media with related provider service details
            $feeds = $query->inRandomOrder()->take($count)->get();

            $formattedFeeds = $feeds->filter(function ($media) {
                // Only keep media items with a non-null URL
                return !is_null($media->url);
            })->map(function ($media) {
                // Safely access providerService
                $providerService = $media->providerService;
                if (!$providerService) {
                    return null; // Skip this iteration
                }
            
                // Safely handle openingHours
                $openingHours = $providerService && $providerService->openHours
                    ? $providerService->openHours->map(function ($hour) {
                        return [
                            'day' => $hour->day,
                            'is_open' => $hour->is_open,
                            'start_time' => $hour->start_time,
                            'close_time' => $hour->close_time,
                        ];
                    })->toArray() // Convert to array if needed
                    : [];
            
                // Calculate average rating
                $averageRating = $providerService ? $providerService->ratings()->avg('rating') : null;
                $totalRatings = $providerService ? $providerService->ratings()->count() : 0;
            
                // Safely access provider details and user
                $user = $providerService ? $providerService->user : null;
                $providerDetails = $providerService ? $providerService->providerDetails : null;
            
                return [
                    'media_id' => $media->id,
                    'display_image' => $providerService->display_image ?? null,
                    'url' => $media->url,
                    'openingHours' => $openingHours,
                    'provider_service_id'=>$providerService->id,
                    'provider' => [
                        'id' => $user->id ?? null,
                        'name' => $user->name ?? null,
                        'email' => $user->email ?? null,
                        'phone_no' => $user->phone_no ?? null,
                        'address' => optional($providerDetails)->address,
                        'city' => optional($providerDetails)->city,
                        'postal_code' => optional($providerDetails)->postal_code,
                    ],
                    'service' => [
                        'id' => $providerService->service->id ?? null,
                        'name' => $providerService->service->name ?? null,
                        'description' => $providerService->description ?? null,
                        'price' => $providerService->price ?? null,
                        'average_rating' => $averageRating,
                        'totalRatings' => $totalRatings,
                    ],
                    'created_at' => $media->created_at,
                    'updated_at' => $media->updated_at,
                ];
            });
            
            
            

            // Check if there are any feeds returned
            if ($formattedFeeds->isEmpty()) {
                return $this->sendResponse([], 'No active media found.');
            }

            return $this->sendResponse($formattedFeeds, 'Feeds');

        } catch (\Exception $e) {
            return $this->sendError('Server Error', $e->getMessage());
        }
    }


}
