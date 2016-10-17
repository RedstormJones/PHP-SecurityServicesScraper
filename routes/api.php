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


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:api');



$api->version('v1', function ($api) {

    // Authenticate returns a JWT upon success to authenticate additional API calls.
    /**
     * @SWG\Get(
     *     path="/telephony/api/authenticate",
     *     tags={"Authentication"},
     *     summary="Get JSON web token by TLS client certificate authentication",
     *     @SWG\Response(
     *         response=200,
     *         description="Authentication succeeded",
     *         ),
     *     ),
     * )
     **/
    $api->get('authenticate', 'App\Http\Controllers\Auth\AuthController@authenticate');
    /**
     * @SWG\Post(
     *     path="/telephony/api/authenticate",
     *     tags={"Authentication"},
     *     summary="Get JSON web token by LDAP user authentication",
     *     @SWG\Parameter(
     *         name="username",
     *         in="formData",
     *         description="LDAP username",
     *         required=true,
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *         name="password",
     *         in="formData",
     *         description="LDAP password",
     *         required=true,
     *         type="string"
     *     ),
     *     @SWG\Response(
     *         response=200,
     *         description="Authentication succeeded",
     *         ),
     *     ),
     * )
     **/
    $api->post('authenticate', 'App\Http\Controllers\Auth\AuthController@authenticate');


});
