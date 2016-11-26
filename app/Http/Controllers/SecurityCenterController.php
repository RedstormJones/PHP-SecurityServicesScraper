<?php

namespace App\Http\Controllers;

class SecurityCenterController extends Controller
{
    /**
     * Create new SecurityCenter Controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show SecurityCenter dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('SecurityCenter');
    }
}
