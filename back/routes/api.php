<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ChallengeController;
use App\Http\Controllers\Api\V1\ClassController;
use App\Http\Controllers\Api\V1\CompanyController;
use App\Http\Controllers\Api\V1\CompanyRepresentativeController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\EventDayController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\MeetingController;
use App\Http\Controllers\Api\V1\ParticipantController;
use App\Http\Controllers\Api\V1\PersonController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\SectorController;
use App\Http\Controllers\Api\V1\TeamController;
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

        Route::get('/events/{event}/classes', [ClassController::class, 'index'])->name('events.classes.index');
        Route::post('/events/{event}/classes', [ClassController::class, 'store'])->name('events.classes.store');
        Route::get('/classes/{class}', [ClassController::class, 'show'])->name('classes.show');
        Route::patch('/classes/{class}', [ClassController::class, 'update'])->name('classes.update');
        Route::patch('/classes/{class}/status', [ClassController::class, 'updateStatus'])->name('classes.status');

        Route::get('/events/{event}/participants', [ParticipantController::class, 'index'])->name('events.participants.index');
        Route::post('/events/{event}/participants', [ParticipantController::class, 'store'])->name('events.participants.store');
        Route::get('/participants/{participant}', [ParticipantController::class, 'show'])->name('participants.show');
        Route::patch('/participants/{participant}', [ParticipantController::class, 'update'])->name('participants.update');
        Route::patch('/participants/{participant}/team', [ParticipantController::class, 'updateTeam'])->name('participants.team');

        Route::get('/events/{event}/teams', [TeamController::class, 'index'])->name('events.teams.index');
        Route::post('/events/{event}/teams', [TeamController::class, 'store'])->name('events.teams.store');
        Route::get('/teams/{team}', [TeamController::class, 'show'])->name('teams.show');
        Route::patch('/teams/{team}', [TeamController::class, 'update'])->name('teams.update');
        Route::get('/teams/{team}/members', [TeamController::class, 'members'])->name('teams.members.index');
        Route::post('/teams/{team}/members', [TeamController::class, 'addMember'])->name('teams.members.store');
        Route::delete('/teams/{team}/members/{participant}', [TeamController::class, 'removeMember'])->name('teams.members.destroy');

        Route::get('/events/{event}/companies', [CompanyController::class, 'index'])->name('events.companies.index');
        Route::post('/events/{event}/companies', [CompanyController::class, 'store'])->name('events.companies.store');
        Route::get('/companies/{company}', [CompanyController::class, 'show'])->name('companies.show');
        Route::patch('/companies/{company}', [CompanyController::class, 'update'])->name('companies.update');
        Route::patch('/companies/{company}/status', [CompanyController::class, 'updateStatus'])->name('companies.status');

        Route::get('/companies/{company}/representatives', [CompanyRepresentativeController::class, 'index'])->name('companies.representatives.index');
        Route::post('/companies/{company}/representatives', [CompanyRepresentativeController::class, 'store'])->name('companies.representatives.store');
        Route::patch('/company-representatives/{representative}', [CompanyRepresentativeController::class, 'update'])->name('company-representatives.update');
        Route::patch('/company-representatives/{representative}/status', [CompanyRepresentativeController::class, 'updateStatus'])->name('company-representatives.status');

        Route::get('/events/{event}/challenges', [ChallengeController::class, 'index'])->name('events.challenges.index');
        Route::post('/events/{event}/challenges', [ChallengeController::class, 'store'])->name('events.challenges.store');
        Route::get('/challenges/{challenge}', [ChallengeController::class, 'show'])->name('challenges.show');
        Route::patch('/challenges/{challenge}', [ChallengeController::class, 'update'])->name('challenges.update');
        Route::patch('/challenges/{challenge}/status', [ChallengeController::class, 'updateStatus'])->name('challenges.status');
        Route::patch('/challenges/{challenge}/team', [ChallengeController::class, 'updateTeam'])->name('challenges.team');
    });
});
