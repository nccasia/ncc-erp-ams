<?php

use App\Http\Controllers\Api;
// use Illuminate\Http\Request;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::group(['prefix' => 'v1', 'middleware' => ['throttle:'.config('app.api_throttle_per_minute').',1']], function () {

    Route::post('auth/google',
    [
        Api\UsersController::class,
        'loginGoogleV2'
    ]
);

}); // end API routes



