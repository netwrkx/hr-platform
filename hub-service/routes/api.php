<?php

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
Route::get('/steps', function () {
    // TODO: Implement StepsController
    return response()->json(['message' => 'Not implemented'], 501);
});

// Server-Driven UI: form/widget schema per step and country
Route::get('/schema/{step_id}', function (string $step_id) {
    // TODO: Implement SchemaController
    return response()->json(['message' => 'Not implemented'], 501);
});

// Cached employee list (proxied from HR Service with column definitions)
Route::get('/employees', function () {
    // TODO: Implement EmployeeController (hub-service version)
    return response()->json(['message' => 'Not implemented'], 501);
});

// Checklist completion data per country
Route::get('/checklists', function () {
    // TODO: Implement ChecklistController
    return response()->json(['message' => 'Not implemented'], 501);
});
