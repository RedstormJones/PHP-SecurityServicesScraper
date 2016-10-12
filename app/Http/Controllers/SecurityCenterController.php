<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;

class SecurityCenterController extends Controller
{
	/**
	* Create new SecurityCenter Controller instance
	*
	* @return void
	*/
	public function __construct()
	{
		$this->middleware('auth');
	}

	/**
	* Show SecurityCenter dashboard
	*
	* @return \Illuminate\Http\Response
	*/
	public function index()
	{
		return view('SecurityCenter');
	}
}
