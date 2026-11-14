<?php

namespace App\Http\Controllers;

use App\Services\ClusterProgress;
use Illuminate\View\View;

class LeaderboardController extends Controller
{
    public function __invoke(ClusterProgress $progress): View
    {
        return view('leaderboard', [
            'rows' => $progress->leaderboard(),
        ]);
    }
}
