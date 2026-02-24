<?php

namespace App\Http\Controllers;

use App\Events\EmployeeCreated;
use App\Events\EmployeeDeleted;
use App\Events\EmployeeUpdated;
use App\Http\Requests\EmployeeFormRequest;
use App\Http\Resources\EmployeeCollection;
use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EmployeeController extends Controller
{
    /**
     * GET /api/employees — Paginated list, filterable by country.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Employee::query();

        if ($request->has('country')) {
            $query->country($request->input('country'));
        }

        $perPage = $request->input('per_page', 15);
        $employees = $query->paginate($perPage);

        return (new EmployeeCollection($employees))->response();
    }

    /**
     * POST /api/employees — Create a new employee.
     */
    public function store(EmployeeFormRequest $request): JsonResponse
    {
        $employee = Employee::create($request->validated());

        try {
            event(new EmployeeCreated($employee));
        } catch (\Throwable $e) {
            Log::error('Failed to publish EmployeeCreated event', [
                'event_type'   => 'EmployeeCreated',
                'employee_id'  => $employee->id,
                'exception'    => $e->getMessage(),
            ]);
        }

        return (new EmployeeResource($employee))->response()->setStatusCode(201);
    }

    /**
     * GET /api/employees/{id} — Show a single employee.
     */
    public function show(int $id): JsonResponse
    {
        $employee = Employee::find($id);

        if (!$employee) {
            return response()->json(['message' => 'Employee not found.'], 404);
        }

        return (new EmployeeResource($employee))->response();
    }

    /**
     * PUT /api/employees/{id} — Update an employee.
     */
    public function update(EmployeeFormRequest $request, int $id): JsonResponse
    {
        $employee = Employee::find($id);

        if (!$employee) {
            return response()->json(['message' => 'Employee not found.'], 404);
        }

        $original = $employee->getAttributes();
        $employee->update($request->validated());
        $employee->refresh();

        $changedFields = [];
        foreach ($request->validated() as $field => $value) {
            if (isset($original[$field]) && $original[$field] != $value) {
                $changedFields[] = $field;
            } elseif (!isset($original[$field]) && $value !== null) {
                $changedFields[] = $field;
            }
        }

        try {
            event(new EmployeeUpdated($employee, $changedFields));
        } catch (\Throwable $e) {
            Log::error('Failed to publish EmployeeUpdated event', [
                'event_type'   => 'EmployeeUpdated',
                'employee_id'  => $employee->id,
                'exception'    => $e->getMessage(),
            ]);
        }

        return (new EmployeeResource($employee))->response();
    }

    /**
     * DELETE /api/employees/{id} — Delete an employee.
     */
    public function destroy(int $id): JsonResponse
    {
        $employee = Employee::find($id);

        if (!$employee) {
            return response()->json(['message' => 'Employee not found.'], 404);
        }

        $employeeData = $employee->replicate();
        $employeeData->id = $employee->id;
        $employee->delete();

        try {
            event(new EmployeeDeleted($employeeData));
        } catch (\Throwable $e) {
            Log::error('Failed to publish EmployeeDeleted event', [
                'event_type'   => 'EmployeeDeleted',
                'employee_id'  => $employeeData->id,
                'exception'    => $e->getMessage(),
            ]);
        }

        return response()->json(null, 204);
    }
}
