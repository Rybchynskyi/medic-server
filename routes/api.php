<?php

use App\Enums\ModuleKeys;
use App\Http\Controllers\Admin\DirectoryImportController;
use App\Http\Controllers\Admin\ModulesController;
use App\Http\Controllers\Admin\OrganizationsController;
use App\Http\Controllers\Admin\UsersController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\PeriodsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ServicesController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [LoginController::class, 'login']);

Route::middleware('throttle:6,1')->group(function () {
    Route::post('/register', [RegisterController::class, 'register']);
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink']);
    Route::post('/reset-password', [PasswordResetController::class, 'reset']);
});

Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::get('/user', [LoginController::class, 'user']);
    Route::post('/logout', [LoginController::class, 'logout']);
    Route::put('/user/profile', [ProfileController::class, 'update']);
    Route::put('/user/password', [ProfileController::class, 'updatePassword']);

    Route::middleware('module:'.ModuleKeys::MATERIALS_CALCULATOR)->group(function () {
        Route::get('/services', [ServicesController::class, 'index']);

        Route::get('/periods', [PeriodsController::class, 'index']);
        Route::post('/periods', [PeriodsController::class, 'store']);
        Route::get('/periods/{identifier}', [PeriodsController::class, 'show']);
        Route::post('/periods/{identifier}/close', [PeriodsController::class, 'close']);
        Route::get('/periods/{identifier}/export', [PeriodsController::class, 'export']);
        Route::post('/periods/{identifier}/entries', [PeriodsController::class, 'storeEntry']);
        Route::post('/periods/{identifier}/reopen', [PeriodsController::class, 'reopen']);
        Route::put('/periods/{identifier}/entries/{entryIdentifier}', [PeriodsController::class, 'updateEntry']);
        Route::delete('/periods/{identifier}/entries/{entryIdentifier}', [PeriodsController::class, 'destroyEntry']);
    });

    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/users', [UsersController::class, 'index']);
        Route::post('/users', [UsersController::class, 'store']);
        Route::put('/users/{identifier}', [UsersController::class, 'update']);
        Route::post('/users/{identifier}/approve', [UsersController::class, 'approve']);
        Route::post('/users/{identifier}/reject', [UsersController::class, 'reject']);
        Route::post('/users/{identifier}/invite', [UsersController::class, 'invite']);
        Route::delete('/users/{identifier}', [UsersController::class, 'destroy']);
        Route::put('/users/{identifier}/modules/{moduleKey}', [UsersController::class, 'toggleModule']);

        Route::get('/modules', [ModulesController::class, 'index']);
        Route::put('/modules/{moduleKey}', [ModulesController::class, 'update'])
            ->whereIn('moduleKey', ModuleKeys::getValues());

        Route::get('/organizations', [OrganizationsController::class, 'index']);
        Route::post('/organizations', [OrganizationsController::class, 'store']);
        Route::get('/organizations/{identifier}', [OrganizationsController::class, 'show']);
        Route::put('/organizations/{identifier}', [OrganizationsController::class, 'update']);
        Route::delete('/organizations/{identifier}', [OrganizationsController::class, 'destroy']);

        Route::post('/organizations/{identifier}/import/material-norms', [DirectoryImportController::class, 'materialNorms']);
    });
});
