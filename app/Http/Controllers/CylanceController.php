<?php

namespace App\Http\Controllers;

use App\Cylance\CylanceDevice;

class CylanceController extends Controller
{
    /**
     * Create a new Cylance Controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show Cylance Dashboard page.
     *
     * @return Illuminate\Http\Response
     */
    public function index()
    {
        $bad_devices = CylanceDevice::whereNull('deleted_at')
                            ->orderBy('files_unsafe', 'desc')
                            ->limit(10)
                            ->get();

        $bad_devices->toArray();

        return view('Cylance', compact('bad_devices'));
    }

    /**
     * Show Cylance device page.
     *
     * @return \Illuminate\Http\Response
     */
    public function show_device($device_id)
    {
        $device = CylanceDevice::where('device_id', $device_id)->first();

        return view('CylanceDevice', compact('device'));
    }
}    // end of CylanceController class
