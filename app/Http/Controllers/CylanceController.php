<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Cylance\CylanceDevice;

class CylanceController extends Controller
{
	/**
	* Create a new Cylance Controller instance
	*
	* @return void
	*/
	public function __construct()
	{
		$this->middleware('auth');
	}

	/**
	* Show Cylance Dashboard page
	*
	* @return Illuminate\Http\Response
	*/
	public function index()
	{
		$bad_devices = CylanceDevice::whereNull('deleted_at')
							->orderBy('files_unsafe', 'desc')
							->limit(10)
							->pluck('files_unsafe', 'device_name');

		return view('Cylance', compact('bad_devices'));
	}

}	// end of CylanceController class
