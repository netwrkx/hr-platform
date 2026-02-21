<?php

use App\Http\Controllers\EmployeeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| HR Service API Routes
|--------------------------------------------------------------------------
|
| Employee CRUD endpoints — the authoritative source of truth for employee data.
| All mutations publish events to RabbitMQ for downstream consumption.
|
*/

Route::apiResource('employees', EmployeeController::class);
