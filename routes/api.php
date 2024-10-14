<?php

use App\Http\Controllers\API\AddressController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\ProviderDetailController;
use App\Http\Controllers\API\UserProfileController;
use App\Http\Controllers\SimpleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('verify-email/{token}', [SimpleController::class, 'verifyEmail']);


Route::middleware('auth:sanctum')->group(function () {
    // User details route
    Route::get('/user', function (Request $request) {
        return $request->user();
    });



    // Provider details route
    Route::post('provider-details', [ProviderDetailController::class, 'addProvideDetails']);
    Route::post('update-profile', [UserProfileController::class, 'update']);

    // user addresses
    Route::post('/addresses', [AddressController::class, 'addAddress']);
    Route::get('/addresses', [AddressController::class, 'getAddresses']);

});

Route::controller(AuthController::class)->group(function () {
    Route::post('register', 'register');
    Route::post('login', 'login');
});