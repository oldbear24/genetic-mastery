<?php

namespace App\Http\Controllers;

use App\Services\ClusterProgress;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ClusterProgress $progress): View
    {
        return view('dashboard', [
            'user' => $request->user(),
            'summary' => $progress->summary(),
        ]);
    }
}
