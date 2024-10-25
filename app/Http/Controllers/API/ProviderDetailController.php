<?php

namespace App\Http\Controllers\API;

use App\Models\Booking;
use App\Models\ProviderDetail;
use App\Models\ProviderService;
use App\Models\User;
use Auth;
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
            'country' => 'required',
            'postal_code' => 'nullable|string|max:20',
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'id_card' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'bg_image' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'state' => 'nullable|string|max:20',
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
                'country' => $request->country,
                'state'=>$request->state
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

    public function providerDashboardData()
    {
        try {
            $userId = Auth::id();
            $providerDetails = ProviderDetail::where('user_id', $userId)->first();

            if (!$providerDetails) {
                return response()->json(['error' => 'Provider details not found.'], 404);
            }

            // Total bookings for the provider service
            $totalBookings = Booking::where('provider_service_id', $providerDetails->id)->count();

            // Total current month sales
            $totalCurrentMonthSales = Booking::where('provider_service_id', $providerDetails->id)
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('payment');

            // Total previous month sales
            $totalPreviousMonthSales = Booking::where('provider_service_id', $providerDetails->id)
                ->whereMonth('created_at', now()->subMonth()->month)
                ->whereYear('created_at', now()->year)
                ->sum('payment');

            // Calculate percentage change
            $percentageChange = 0;
            if ($totalPreviousMonthSales > 0) {
                $percentageChange = (($totalCurrentMonthSales - $totalPreviousMonthSales) / $totalPreviousMonthSales) * 100;
            }

            // Monthly sales data for the current year
            $monthlySales = Booking::where('provider_service_id', $providerDetails->id)
                ->whereYear('created_at', now()->year)
                ->selectRaw('MONTH(created_at) as month, SUM(payment) as total_sales')
                ->groupBy('month')
                ->orderBy('month')
                ->get();

            // Prepare the data for graph
            $salesData = [];
            for ($i = 1; $i <= 12; $i++) {
                $salesData[date('F', mktime(0, 0, 0, $i, 1))] = 0; // Initialize months
            }

            foreach ($monthlySales as $sale) {
                $monthName = date('F', mktime(0, 0, 0, $sale->month, 1));
                $salesData[$monthName] = $sale->total_sales;
            }


            // Prepare final data for response
            $data = [
                'total_bookings' => $totalBookings,
                'total_current_month_sales' => $totalCurrentMonthSales,
                'total_previous_month_sales' => $totalPreviousMonthSales,
                'percentage_change' => $percentageChange,
                'monthly_sales' => $salesData,
                'services' => $this->getServicesWithMostOrders()
            ];

            // Return response with all the dashboard data
            return $this->sendResponse($data, 'Provider Dashboard Details');
        } catch (\Throwable $th) {
            return $this->sendError('Server Error', [$th->getMessage()]);
        }
    }

    public function getServicesWithMostOrders()
    {
        try {
            $userId = Auth::id();
            $providerDetails = ProviderDetail::where('user_id', $userId)->first();

            if (!$providerDetails) {
                return response()->json(['error' => 'Provider details not found.'], 404);
            }

            // Retrieve all services with the count of bookings
            return ProviderService::withCount(['bookings', 'ratings']) // Count total bookings and ratings
            ->withAvg('ratings', 'rating') // Calculate average rating
            ->where('user_id', $userId)
            ->orderBy('bookings_count', 'desc') // Order by the count of bookings
            ->get();

        } catch (\Throwable $th) {
            return [];
        }
    }

    




}
