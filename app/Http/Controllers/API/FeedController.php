<?php

namespace App\Http\Controllers\API;

use App\Models\ServiceMedia;
use Illuminate\Http\Request;

class FeedController extends BaseController
{

    public function randomFeeds(Request $request)
    {
        $count = $request->query('count', 5); // Default to 5 if not provided
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

            // Fetch random media with related provider service details
            $feeds = $query->inRandomOrder()->take($count)->get();

            $formattedFeeds = $feeds->map(function ($media) {
                $openingHours = $media->providerService->openHours->map(function ($hour) {
                    return [
                        'day' => $hour->day,
                        'is_open' => $hour->is_open,
                        'start_time' => $hour->start_time,
                        'close_time' => $hour->close_time,
                    ];
                });

                 // Calculate average rating
                $averageRating = $media->providerService->ratings()->avg('rating');
                $totalRatings = $media->providerService->ratings()->count();
                return [
                    'media_id' => $media->id,
                    'display_image'=>  $media->providerService->display_image,
                    'url' => $media->url,
                    'openingHours' => $openingHours,
                    'provider' => [
                        'id' => $media->providerService->user->id,
                        'name' => $media->providerService->user->name,
                        'email' => $media->providerService->user->email,
                        'phone_no' => $media->providerService->user->phone_no,
                        'address' => optional($media->providerService->providerDetails)->address,
                        'city' => optional($media->providerService->providerDetails)->city,
                        'postal_code' => optional($media->providerService->providerDetails)->postal_code,
                    ],
                    'service' => [
                        'id' => $media->providerService->service->id,
                        'name' => $media->providerService->service->name,
                        'description' => $media->providerService->description,
                        'price' => $media->providerService->price,
                        'average_rating' => $averageRating,
                        'totalRatings'=>$totalRatings

                    ],
                    'created_at' => $media->created_at,
                    'updated_at' => $media->updated_at,
                ];
            });

            // Check if there are any feeds returned
            if ($formattedFeeds->isEmpty()) {
                return $this->sendResponse([], 'No active media found.');
            }

            return $this->sendResponse(result: $formattedFeeds, message: 'Feeds');

        } catch (\Exception $e) {
            return $this->sendError('Server Error', $e->getMessage());
        }
    }


}
