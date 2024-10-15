<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Storage;
use Validator;
class ProductController extends BaseController
{
    public function addProductToProviderService(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required', // Ensure it exists in the users table
            'provider_service_id' => 'required|exists:provider_services,id',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            'price' => 'required',
        ]);

        // Return validation errors if any
        if ($validator->fails()) {
            return $this->sendError('Server Error', $validator->errors());
        }

        $productdata = [
            'name' => $request->name,
            'provider_service_id' => $request->provider_service_id,
            'price' => $request->price,
        ];

        try {
            if ($request->hasFile('image')) {
                $profilePicturePath = $request->file('image')->store('rtf/providers/products', 'do');
                Storage::disk('do')->setVisibility($profilePicturePath, 'public');
                $productdata['image'] = Storage::disk('do')->url($profilePicturePath);
            }

            $data = Product::create($productdata);

            return $this->sendResponse($data, 'Provider data added successfully.');

        } catch (\Throwable $th) {
            return $this->sendError('Server Error', $th->getMessage());

        }

    }

    public function getServiceProviderProducts(Request $request)
    {
        $providerServiceId = $request->query('provider_service_id');
        if (!$providerServiceId) {
            return $this->sendError('Validation Error', 'Provider Service Id is requried');
        }

        try {
            $data = Product::where('provider_service_id', $providerServiceId)->get();
            return $this->sendResponse($data, 'Products for the Service Provider');
        } catch (\Throwable $th) {
            return $this->sendError('Server Error', $th->getMessage());
        }

    }
}
