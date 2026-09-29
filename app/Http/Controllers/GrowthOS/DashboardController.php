<?php

namespace App\Http\Controllers\GrowthOS;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('growth-os.dashboard');
    }
}
