<?php

namespace App\Http\Controllers;

use App\Models\Digestion;
use Inertia\Inertia;
use App\Models\Emotion;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Dashboard/Dashboard', []);
    }
}
