<?php

namespace App\Http\Controllers;

use App\Cylance;
use App\IronPort;
use App\PhishMe;
use App\SecurityCenter;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application home page.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('home');
    }

    /**
     * Show the application dashboard page.
     *
     * @return \Illuminate\Http\Response
     */
    public function dashboard()
    {
        $tools = [
            'Cylance',
            'IronPort',
            'SecurityCenter',
            'PhishMe',
        ];

        return view('dashboard', compact('tools'));
    }
}
