<?php

use App\Http\Controllers\Auth\MagicLinkController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Reports\AreaReportController;
use App\Http\Controllers\Reports\IncidentReportController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'app')->name('home');

Route::prefix('api/auth')->name('auth.')->group(function (): void {
    Route::post('magiclink', [MagicLinkController::class, 'store'])->name('magiclink.request');
    Route::get('magiclink/verify', [MagicLinkController::class, 'verify'])->name('magiclink.verify');

    Route::middleware('auth')->group(function (): void {
        Route::get('user', [SessionController::class, 'show'])->name('user');
        Route::post('logout', [SessionController::class, 'destroy'])->name('logout');
    });
});

Route::prefix('api/cases')->middleware('auth')->name('incidents.')->group(function (): void {
    Route::post('incident/report', [IncidentReportController::class, 'store'])->name('report');
    Route::get('/', [IncidentReportController::class, 'index'])->name('index');
    Route::get('{reference}', [IncidentReportController::class, 'show'])->name('show');
    Route::get('{reference}/photos/{photo}', [IncidentReportController::class, 'photo'])
        ->whereNumber('photo')
        ->name('photos.show');
});

Route::prefix('api/area/reports')->middleware(['auth', 'can:view-area-reports'])->name('area.reports.')->group(function (): void {
    Route::get('/', [AreaReportController::class, 'index'])->name('index');
    Route::get('{reference}', [AreaReportController::class, 'show'])->name('show');
    Route::get('{reference}/photos/{photo}', [AreaReportController::class, 'photo'])
        ->whereNumber('photo')->name('photos.show');
});