<?php

namespace App\Services;

use App\Models\Employee;

class EmployeeEventPublisher
{
    private const EXCHANGE = 'hr.events';

    public function __construct(
        private EventPayloadBuilder $payloadBuilder,
        private RabbitMQService $rabbitMQ,
    ) {}

    /**
     * Publish an EmployeeCreated event to RabbitMQ.
     *
     * Routing key: employee.created.{country}
     */
    public function publishCreated(Employee $employee): void
    {
        $payload = $this->payloadBuilder->buildPayload('EmployeeCreated', $employee);
        $routingKey = $this->payloadBuilder->getRoutingKey('EmployeeCreated', $employee->country);

        $this->rabbitMQ->publish(self::EXCHANGE, $routingKey, $payload);
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
        $payload = $this->payloadBuilder->buildPayload('EmployeeUpdated', $employee, $changedFields);
        $routingKey = $this->payloadBuilder->getRoutingKey('EmployeeUpdated', $employee->country);

        $this->rabbitMQ->publish(self::EXCHANGE, $routingKey, $payload);
    }

    /**
     * Publish an EmployeeDeleted event to RabbitMQ.
     *
     * Routing key: employee.deleted.{country}
     */
    public function publishDeleted(Employee $employee): void
    {
        $payload = $this->payloadBuilder->buildPayload('EmployeeDeleted', $employee);
        $routingKey = $this->payloadBuilder->getRoutingKey('EmployeeDeleted', $employee->country);

        $this->rabbitMQ->publish(self::EXCHANGE, $routingKey, $payload);
    }
}
