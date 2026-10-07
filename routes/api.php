<?php

use App\Http\Controllers\Api\InspectionController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', function () {
        try {
            DB::select('select 1');
            Redis::connection()->ping();

            return response()->json(['status' => 'ok', 'database' => 'ok', 'redis' => 'ok']);
        } catch (\Throwable) {
            return response()->json(['status' => 'degraded', 'database' => 'error', 'redis' => 'error'], 503);
        }
    });

    Route::post('/inspect', [InspectionController::class, 'store'])
        ->middleware(['api.token', 'throttle:sharecontest-api']);
});
