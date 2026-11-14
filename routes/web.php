<?php

use App\Models\Milestone;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('home', ['milestoneCount' => Milestone::count()]))->name('home');
