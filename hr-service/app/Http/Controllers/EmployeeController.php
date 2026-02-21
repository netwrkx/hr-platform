<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmployeeFormRequest;
use App\Models\Employee;
use App\Services\EmployeeEventPublisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function __construct(
        private EmployeeEventPublisher $eventPublisher
    ) {}

    /**
     * GET /api/employees — Paginated list, filterable by country.
     */
    public function index(Request $request): JsonResponse
    {
        // TODO: Implement paginated employee listing with country filter
        return response()->json(['message' => 'Not implemented'], 501);
    }

    /**
     * POST /api/employees — Create a new employee.
     */
    public function store(EmployeeFormRequest $request): JsonResponse
    {
        // TODO: Create employee, publish EmployeeCreated event
        return response()->json(['message' => 'Not implemented'], 501);
    }

    /**
     * GET /api/employees/{id} — Show a single employee.
     */
    public function show(int $id): JsonResponse
    {
        // TODO: Return single employee with SSN masking
        return response()->json(['message' => 'Not implemented'], 501);
    }

    /**
     * PUT /api/employees/{id} — Update an employee.
     */
    public function update(EmployeeFormRequest $request, int $id): JsonResponse
    {
        // TODO: Update employee, publish EmployeeUpdated event with changed_fields
        return response()->json(['message' => 'Not implemented'], 501);
    }

    /**
     * DELETE /api/employees/{id} — Delete an employee.
     */
    public function destroy(int $id): JsonResponse
    {
        // TODO: Delete employee, publish EmployeeDeleted event
        return response()->json(['message' => 'Not implemented'], 501);
    }
}
