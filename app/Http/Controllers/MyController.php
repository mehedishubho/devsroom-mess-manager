<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class MyController extends Controller
{
    public function index(): View
    {
        return view('my');
    }
}
