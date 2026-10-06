<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\SubmissionController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('home'));
Route::post('/participations', [SubmissionController::class, 'store'])->middleware('throttle:10,1')->name('submissions.store');
Route::get('/participations/{submission}', [SubmissionController::class, 'show'])->name('submissions.show');
Route::get('/health', function () {
    try { DB::select('select 1'); Redis::connection()->ping(); return response()->json(['status'=>'ok','database'=>'ok','redis'=>'ok']); }
    catch (\Throwable) { return response()->json(['status'=>'degraded','database'=>'error','redis'=>'error'], 503); }
});
Route::get('/admin', [AdminController::class, 'index'])->middleware('admin.basic')->name('admin.index');
Route::middleware('admin.basic')->prefix('admin')->name('admin.')->group(function () {
    Route::post('/submissions/refresh-all', [AdminController::class, 'refreshAll'])->name('submissions.refresh-all');
    Route::post('/submissions/refresh-selected', [AdminController::class, 'refreshSelected'])->name('submissions.refresh-selected');
    Route::get('/submissions/{submission}', [AdminController::class, 'show'])->name('submissions.show');
    Route::post('/submissions/{submission}/refresh', [AdminController::class, 'refresh'])->name('submissions.refresh');
    Route::delete('/submissions/{submission}', [AdminController::class, 'destroy'])->name('submissions.destroy');
});
