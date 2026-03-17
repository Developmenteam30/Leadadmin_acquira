<?php

use App\Http\Controllers\LiveFeedController;
use Illuminate\Support\Facades\Route;

// Public API spec page (shareable URL with hash validation)
Route::get('/live/{idFeedIn}/apispec', [LiveFeedController::class, 'showApiSpec']);

Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');
