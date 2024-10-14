<?php

namespace App\Http\Controllers\API;

use App\Models\ServiceRating;
use Illuminate\Http\Request;
use Validator;
class RatingController extends BaseController
{
    public function rateService(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'provider_service_id' => 'required|exists:provider_services,id',
            'rating' => 'required|integer|between:1,5',
            'comment' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors());
        }

        try {

            $rating = ServiceRating::create([
                'provider_service_id' => $request->provider_service_id,
                'user_id' => auth()->id(),
                'rating' => $request->rating,
                'comment' => $request->comment,
            ]);

            return $this->sendResponse($rating, 'Rating submitted successfully!');

        } catch (\Throwable $th) {
            return $this->sendError('Server Error', $th->getMessage());

        }
    }

}
