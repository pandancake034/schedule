<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ScheduleController;

// 1. Dashboard & Kalender
Route::get('/nieuwegein/schedule', [ScheduleController::class, 'index']);
Route::get('/nieuwegein/schedule/api', [ScheduleController::class, 'getEvents']);
Route::get('/nieuwegein/schedule/day/{date}', [ScheduleController::class, 'getDayDetails']); // Detail pop-up

// 2. Acties (Genereren & Wissen)
// Deze regel zorgt dat de knop 'Genereer Rooster' werkt:
Route::post('/nieuwegein/schedule/generate', [ScheduleController::class, 'generateSchedule']);
Route::post('/nieuwegein/admin/clear', [ScheduleController::class, 'clearSchedule']);

// 3. Team Beheer
Route::get('/nieuwegein/team', [ScheduleController::class, 'team']);
Route::get('/nieuwegein/team/{id}/edit', [ScheduleController::class, 'editUser']); 
Route::put('/nieuwegein/team/{id}', [ScheduleController::class, 'updateUser']);   
Route::delete('/nieuwegein/team/{id}', [ScheduleController::class, 'deleteUser']); 

// 4. Admin (Aanmaken gebruikers)
Route::get('/nieuwegein/admin', [ScheduleController::class, 'admin']);
Route::post('/nieuwegein/admin/create-user', [ScheduleController::class, 'storeUser']);

// Root redirect (optioneel, handig als je de app opent)
Route::get('/', function () {
    return redirect('/nieuwegein/schedule');
});