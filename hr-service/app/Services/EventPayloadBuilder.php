<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Support\Str;
use Carbon\Carbon;

class EventPayloadBuilder
{
    /**
     * Build a standardised event payload for RabbitMQ publishing.
     *
     * @param string $eventType EmployeeCreated|EmployeeUpdated|EmployeeDeleted
     * @param Employee $employee The employee model instance
     * @param array $changedFields Fields that were modified (empty for Created/Deleted)
     * @return array{event_id: string, event_type: string, timestamp: string, country: string, data: array}
     */
    public function buildPayload(string $eventType, Employee $employee, array $changedFields = []): array
    {
        return [
            'event_id' => (string) Str::uuid(),
            'event_type' => $eventType,
            'timestamp' => Carbon::now()->toIso8601String(),
            'country' => $employee->country,
            'data' => [
                'employee_id' => $employee->id,
                'changed_fields' => $changedFields,
                'employee' => $this->serializeEmployee($employee),
            ],
        ];
    }

    /**
     * Generate the RabbitMQ routing key.
     *
     * Format: employee.{event_type_lower}.{country}
     * Examples: employee.created.USA, employee.updated.Germany
     */
    public function getRoutingKey(string $eventType, string $country): string
    {
        $action = strtolower(str_replace('Employee', '', $eventType));

        return "employee.{$action}.{$country}";
    }

    /**
     * Serialize an Employee model to a plain array for the event payload.
     *
     * Includes all fields — raw SSN (not masked) so HubService has full data.
     */
    private function serializeEmployee(Employee $employee): array
    {
        $data = [
            'id' => $employee->id,
            'name' => $employee->name,
            'last_name' => $employee->last_name,
            'salary' => $employee->salary,
            'country' => $employee->country,
        ];

        if ($employee->country === 'USA') {
            $data['ssn'] = $employee->ssn;
            $data['address'] = $employee->address;
        }

        if ($employee->country === 'Germany') {
            $data['tax_id'] = $employee->tax_id;
            $data['goal'] = $employee->goal;
        }

        return $data;
    }
}
