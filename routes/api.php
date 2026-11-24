<?php

use App\Http\Controllers\MantenimientoController;
use App\Http\Controllers\RenoController;
use App\Http\Controllers\VueloController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas API - Escenario B: Establo de Renos y Mantenimiento del Terreno
|--------------------------------------------------------------------------
*/

// --- Endpoints públicos de Renos ---
Route::get('/renos', [RenoController::class, 'index']);
Route::get('/renos/{id}', [RenoController::class, 'show']);

// --- Endpoints protegidos de Renos (requieren rol JEFE_ESTABLO) ---
Route::middleware('rol:JEFE_ESTABLO')->group(function () {
    Route::post('/renos', [RenoController::class, 'store']);
    Route::put('/renos/{id}', [RenoController::class, 'update']);
    Route::delete('/renos/{id}', [RenoController::class, 'destroy']);
    Route::patch('/renos/{id}/asignacion', [RenoController::class, 'asignarVuelo']);
});

// --- Endpoints públicos de Mantenimientos ---
Route::post('/mantenimientos', [MantenimientoController::class, 'store']);
Route::get('/mantenimientos', [MantenimientoController::class, 'index']);
Route::get('/mantenimientos/{id}', [MantenimientoController::class, 'show']);
Route::patch('/mantenimientos/{id}/estado', [MantenimientoController::class, 'cambiarEstado']);

// --- Extra Escenario B: Resumen del vuelo ---
Route::get('/vuelo/resumen', [VueloController::class, 'resumen']);
