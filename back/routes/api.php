<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\EventDayController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\MeetingController;
use App\Http\Controllers\Api\V1\PersonController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\SectorController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('/health', HealthController::class)->name('health');

    // Login da SPA: antes, GET /sanctum/csrf-cookie.
    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('auth.login');

    Route::middleware(['auth:sanctum', EnsureUserIsActive::class])->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');

        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');

        Route::apiResource('people', PersonController::class)->except('destroy');

        Route::apiResource('users', UserController::class)->except('destroy');
        Route::patch('/users/{user}/role', [UserController::class, 'updateRole'])->name('users.role');
        Route::patch('/users/{user}/status', [UserController::class, 'updateStatus'])->name('users.status');
        Route::patch('/users/{user}/sector', [UserController::class, 'updateSector'])->name('users.sector');

        Route::apiResource('events', EventController::class)->except('destroy');
        Route::scopeBindings()->group(function () {
            Route::get('/events/{event}/days', [EventDayController::class, 'index'])->name('events.days.index');
            Route::post('/events/{event}/days', [EventDayController::class, 'store'])->name('events.days.store');
            Route::patch('/events/{event}/days/{day}', [EventDayController::class, 'update'])->name('events.days.update');
        });

        Route::get('/events/{event}/sectors', [SectorController::class, 'index'])->name('events.sectors.index');
        Route::post('/events/{event}/sectors', [SectorController::class, 'store'])->name('events.sectors.store');
        Route::get('/sectors/{sector}', [SectorController::class, 'show'])->name('sectors.show');
        Route::patch('/sectors/{sector}', [SectorController::class, 'update'])->name('sectors.update');
        Route::patch('/sectors/{sector}/status', [SectorController::class, 'updateStatus'])->name('sectors.status');

        Route::get('/events/{event}/meetings', [MeetingController::class, 'index'])->name('events.meetings.index');
        Route::post('/events/{event}/meetings', [MeetingController::class, 'store'])->name('events.meetings.store');
        Route::get('/meetings/{meeting}', [MeetingController::class, 'show'])->name('meetings.show');
        Route::patch('/meetings/{meeting}', [MeetingController::class, 'update'])->name('meetings.update');
    });
});
