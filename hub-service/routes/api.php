<?php

use App\Http\Controllers\ChecklistController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\SchemaController;
use App\Http\Controllers\StepsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Hub Service API Routes
|--------------------------------------------------------------------------
|
| Read-only endpoints serving cached employee data, checklists,
| and server-driven UI configuration.
|
*/

// Server-Driven UI: navigation steps per country
Route::get('/steps', [StepsController::class, 'index']);

// Server-Driven UI: form/widget schema per step and country
Route::get('/schema/{step_id}', [SchemaController::class, 'show']);

// Cached employee list (proxied from HR Service with column definitions)
Route::get('/employees', [EmployeeController::class, 'index']);

// Checklist completion data per country
Route::get('/checklists', [ChecklistController::class, 'index']);
