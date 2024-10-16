<?php

use App\Http\Controllers\API\AddressController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\BookingController;
use App\Http\Controllers\API\FeedController;
use App\Http\Controllers\API\PaymentController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\ProviderDetailController;
use App\Http\Controllers\API\RatingController;
use App\Http\Controllers\API\ServiceController;
use App\Http\Controllers\API\UserProfileController;
use App\Http\Controllers\SimpleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('verify-email/{token}', [SimpleController::class, 'verifyEmail']);

Route::post('google-login', [SimpleController::class, 'requestTokenGoogle']);


Route::middleware('auth:sanctum')->group(function () {
    // Serivices

    Route::get('services', [ServiceController::class, 'getAllServices']);

    Route::post('provider/add-service', [ServiceController::class, 'addServiceToProvider']);
    Route::post('provider/add-service-media', action: [ServiceController::class, 'addServiceMedia']);
    Route::post('provider/add-service-time', action: [ServiceController::class, 'addServiceTiming']);

    // rate-service
    Route::post('provider/rate-service', action: [RatingController::class, 'rateService']);

    // add -product

    Route::post('provider/add-product', [ProductController::class, 'addProductToProviderService']);
    Route::get('/get-products', [ProductController::class, 'getServiceProviderProducts']);

    //BOOKing
    Route::post('booking', [BookingController::class, 'booking']);
    Route::get('bookings', [BookingController::class, 'getUserBookings']);

    //Payment Methods
    Route::post('payment-method', [PaymentController::class, 'createPaymentMethod']);
    Route::get('payment-methods', [PaymentController::class, 'getUserPaymentMethods']);


    // Payment
    Route::post('/payment', [PaymentController::class, 'chargeUser']);
    Route::get('payments-history', [PaymentController::class, 'getUserPaymentHistory']);




    // User details route
    Route::get('/user', function (Request $request) {
        return $request->user();
    });



    // Provider details route
    Route::post('provider-details', [ProviderDetailController::class, 'addProvideDetails']);
    Route::get('provider-details', [ProviderDetailController::class, 'getProviderDetails']);
    Route::post('update-profile', [UserProfileController::class, 'update']);

    // user addresses
    Route::post('/addresses', [AddressController::class, 'addAddress']);
    Route::get('/addresses', [AddressController::class, 'getAddresses']);

});

Route::get('feeds', action: [FeedController::class, 'randomFeeds']);

Route::controller(AuthController::class)->group(function () {
    Route::post('register', 'register');
    Route::post('login', 'login');
});