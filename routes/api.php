<?php

use App\Http\Controllers\Api\V1\Auth\RefreshTokenController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/refresh', RefreshTokenController::class)->name('auth.refresh');
});
