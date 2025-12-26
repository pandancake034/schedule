<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ScheduleController;

// 1. Dashboard
Route::get('/nieuwegein/schedule', [ScheduleController::class, 'index']);
Route::get('/nieuwegein/schedule/api', [ScheduleController::class, 'getEvents']);

// 2. Team
Route::get('/nieuwegein/team', [ScheduleController::class, 'team']);

// 3. Admin
Route::get('/nieuwegein/admin', [ScheduleController::class, 'admin']);
Route::post('/nieuwegein/admin/create-user', [ScheduleController::class, 'storeUser']);

// De route die het algoritme start
Route::post('/nieuwegein/admin/generate', [ScheduleController::class, 'generateSchedule']);
