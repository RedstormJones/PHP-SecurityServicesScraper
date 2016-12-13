<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of the routes that are handled
| by your application. Just tell Laravel the URIs it should respond
| to using a Closure or controller method. Build something great!
|
*/

Route::get('/', function () {
    return view('welcome');
    //return redirect('ui');
});

/*
Auth::routes();


Route::get('/home', 'HomeController@index');


Route::get('/dashboard', 'HomeController@dashboard');
/**/

/*
* Cylance routes
*
*/
//Route::get('/Cylance', 'CylanceController@index');
//Route::get('/Cylance/{device_id}', 'CylanceController@show_device');

/*
* IronPort routes
*
*/
//Route::get('/IronPort', 'IronPortController@index');

/*
* SecurityCenter routes
*
*/
//Route::get('/SecurityCenter', 'SecurityCenterController@index');

/*
* PhishMe routes
*
*/
//Route::get('/PhishMe', 'PhishMeController@index');
