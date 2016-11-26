<?php

namespace App\Http\Controllers;

class PhishMeController extends Controller
{
    /**
     * Create new PhishMe Controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show PhishMe dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('PhishMe');
    }
}
