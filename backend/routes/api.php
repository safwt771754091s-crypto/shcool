<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LeaderboardController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\TwoFactorController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — School Digital Platform (المرحلة الأولى)
|--------------------------------------------------------------------------
| All routes are prefixed with /api and pass through InitializeTenancy.
*/

Route::prefix('v1')->group(function (): void {
    // ---- Public -------------------------------------------------------
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1');

    // ---- Authenticated ------------------------------------------------
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        // Two-factor challenge (uses the restricted 2fa-challenge token).
        Route::post('auth/2fa/challenge', [AuthController::class, 'challenge'])
            ->middleware('throttle:6,1');

        // Everything below requires a fully verified session.
        Route::middleware('2fa')->group(function (): void {
            // 2FA management
            Route::post('auth/2fa/enable', [TwoFactorController::class, 'enable']);
            Route::post('auth/2fa/confirm', [TwoFactorController::class, 'confirm']);
            Route::post('auth/2fa/disable', [TwoFactorController::class, 'disable']);

            // Administrative hierarchy
            Route::get('organizations/tree', [OrganizationController::class, 'tree'])
                ->middleware('permission:organizations.view');
            Route::get('organizations', [OrganizationController::class, 'index'])
                ->middleware('permission:organizations.view');
            Route::post('organizations', [OrganizationController::class, 'store'])
                ->middleware('permission:organizations.create');
            Route::get('organizations/{organization}', [OrganizationController::class, 'show'])
                ->middleware('permission:organizations.view');
            Route::put('organizations/{organization}', [OrganizationController::class, 'update'])
                ->middleware('permission:organizations.update');

            // Competition / leaderboards
            Route::get('ranking-periods/{period}/leaderboard', [LeaderboardController::class, 'index'])
                ->middleware('permission:reports.view');
            Route::get('ranking-periods/{period}/my-position', [LeaderboardController::class, 'myPosition'])
                ->middleware('permission:reports.view');
            Route::post('ranking-periods/{period}/recompute', [LeaderboardController::class, 'recompute'])
                ->middleware('permission:reports.export');
        });
    });
});
