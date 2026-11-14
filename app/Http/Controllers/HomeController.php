<?php

namespace App\Http\Controllers;

use App\Services\ClusterProgress;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(ClusterProgress $progress): View
    {
        return view('home', [
            'summary' => $progress->summary(),
            'grid' => $progress->grid(),
            'stats' => config('genetic.stats'),
            'recent' => $progress->recentDiscoveries(5),
            'leaders' => $progress->leaderboard(5),
        ]);
    }
}
