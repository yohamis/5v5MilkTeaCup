<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\PlayerAuthController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\TournamentController;
use Illuminate\Support\Facades\Route;

Route::get('/tournament', TournamentController::class);
Route::get('/events', [RegistrationController::class, 'index']);
Route::post('/auth/player', [PlayerAuthController::class, 'login'])->middleware('throttle:10,1');
Route::middleware('player.token')->group(function () {
    Route::get('/auth/me', [PlayerAuthController::class, 'me']);
    Route::post('/auth/logout', [PlayerAuthController::class, 'logout']);
    Route::post('/events/{event}/register', [RegistrationController::class, 'store']);
    Route::delete('/events/{event}/register', [RegistrationController::class, 'destroy']);
});
Route::prefix('admin')->middleware('admin.key')->group(function () {
    Route::post('/tournament/import', [AdminController::class, 'import']);
    Route::put('/matches/{externalId}', [AdminController::class, 'updateMatch']);
    Route::delete('/matches/{externalId}', [AdminController::class, 'deleteMatch']);
    Route::get('/players', [AdminController::class, 'players']);
    Route::patch('/players/{player}', [AdminController::class, 'updatePlayer']);
    Route::get('/events', [AdminController::class, 'events']);
    Route::post('/events', [AdminController::class, 'createEvent']);
    Route::patch('/events/{event}', [AdminController::class, 'updateEvent']);
});
