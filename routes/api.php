<?php

use Illuminate\Http\Request;

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

$api = app('Dingo\Api\Routing\Router');


/*
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:api');

Route::auth();
/**/


$api->version('v1', function ($api) {

    // Get your user info.
    $api->get('userinfo', 'App\Http\Controllers\Auth\AuthController@userinfo');


    // include authentication routes
    require __DIR__.'/api.auth.php';

    require __DIR__.'/api.cylance.php';
});


