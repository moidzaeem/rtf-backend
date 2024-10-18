<?php

namespace App\Http\Controllers\API;

use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AddressController extends BaseController
{
    public function addAddress(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'address_line_1' => 'required|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'postal_code' => 'required|string|max:20',
            'country' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        try {
            $address = Address::create([
                'user_id' => Auth::id(),
                'address_line_1' => $request->address_line_1,
                'address_line_2' => $request->address_line_2,
                'city' => $request->city,
                'state' => $request->state,
                'postal_code' => $request->postal_code,
                'country' => $request->country,
            ]);
            return $this->sendResponse($address, 'Address added successfully');

        } catch (\Throwable $th) {
            return $this->sendError('Server Error', $th->getMessage());

        }
    }

    public function getAddresses()
    {
        try {
            $addresses = Auth::user()->addresses; // Get addresses for the authenticated user
            return $this->sendResponse($addresses, 'User Addresses');

        } catch (\Throwable $th) {
            return $this->sendError('Server Error', $th->getMessage());
        }
    }

    public function deleteAddress(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'address_id' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        try {
            Address::find($request->address_id)->delete();
            return $this->sendResponse([], 'Address deleted successfully');
        } catch (\Throwable $th) {
            return $this->sendError('Server Error', $th->getMessage());

        }
    }
}

