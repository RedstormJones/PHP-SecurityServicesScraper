<?php

namespace App\Http\Controllers;

class IronPortController extends Controller
{
    /**
     * Create new IronPort Controller instance.
     *
     * @return void
     */
    public function __constuct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the IronPort dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('IronPort');
    }
}