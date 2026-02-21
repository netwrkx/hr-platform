<?php

namespace App\Services;

use App\Models\Employee;

class EmployeeEventPublisher
{
    /**
     * Publish an EmployeeCreated event to RabbitMQ.
     *
     * Routing key: employee.created.{country}
     */
    public function publishCreated(Employee $employee): void
    {
        // TODO: Build event payload and publish to RabbitMQ
        // Graceful degradation: catch exceptions, log, never fail the HTTP response
    }

    /**
     * Publish an EmployeeUpdated event to RabbitMQ.
     *
     * Routing key: employee.updated.{country}
     *
     * @param array $changedFields List of field names that were modified
     */
    public function publishUpdated(Employee $employee, array $changedFields): void
    {
        // TODO: Build event payload with changed_fields and publish to RabbitMQ
    }

    /**
     * Publish an EmployeeDeleted event to RabbitMQ.
     *
     * Routing key: employee.deleted.{country}
     */
    public function publishDeleted(Employee $employee): void
    {
        // TODO: Build event payload and publish to RabbitMQ
    }

    /**
     * Build the standardised event payload.
     *
     * @return array{event_id: string, event_type: string, timestamp: string, country: string, data: array}
     */
    protected function buildPayload(string $eventType, Employee $employee, array $changedFields = []): array
    {
        // TODO: Implement event payload builder per PRD spec
        return [];
    }
}
