<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Services\EventPayloadBuilder;
use Illuminate\Support\Str;
use PHPUnit\Framework\TestCase;

class EventPayloadBuilderTest extends TestCase
{
    private EventPayloadBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new EventPayloadBuilder();
    }

    // ── EmployeeCreated payload structure ────────────────────────────────

    public function test_created_payload_contains_all_required_fields(): void
    {
        $employee = $this->makeEmployee(['id' => 1, 'country' => 'USA']);

        $payload = $this->builder->buildPayload('EmployeeCreated', $employee);

        $this->assertArrayHasKey('event_id', $payload);
        $this->assertArrayHasKey('event_type', $payload);
        $this->assertArrayHasKey('timestamp', $payload);
        $this->assertArrayHasKey('country', $payload);
        $this->assertArrayHasKey('data', $payload);
        $this->assertArrayHasKey('employee_id', $payload['data']);
        $this->assertArrayHasKey('changed_fields', $payload['data']);
        $this->assertArrayHasKey('employee', $payload['data']);
    }

    public function test_created_payload_has_valid_uuid_v4(): void
    {
        $employee = $this->makeEmployee(['id' => 1, 'country' => 'USA']);

        $payload = $this->builder->buildPayload('EmployeeCreated', $employee);

        $this->assertTrue(Str::isUuid($payload['event_id']));

        // Verify UUID version 4 (13th hex digit is '4')
        $hex = str_replace('-', '', $payload['event_id']);
        $this->assertEquals('4', $hex[12]);
    }

    public function test_created_payload_has_correct_event_type(): void
    {
        $employee = $this->makeEmployee(['id' => 1, 'country' => 'USA']);

        $payload = $this->builder->buildPayload('EmployeeCreated', $employee);

        $this->assertEquals('EmployeeCreated', $payload['event_type']);
    }

    public function test_created_payload_has_iso8601_timestamp(): void
    {
        $employee = $this->makeEmployee(['id' => 1, 'country' => 'USA']);

        $payload = $this->builder->buildPayload('EmployeeCreated', $employee);

        $this->assertNotEmpty($payload['timestamp']);
        $dt = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $payload['timestamp']);
        $this->assertInstanceOf(\DateTimeImmutable::class, $dt);
    }

    public function test_created_payload_has_empty_changed_fields(): void
    {
        $employee = $this->makeEmployee(['id' => 1, 'country' => 'USA']);

        $payload = $this->builder->buildPayload('EmployeeCreated', $employee);

        $this->assertIsArray($payload['data']['changed_fields']);
        $this->assertEmpty($payload['data']['changed_fields']);
    }

    public function test_created_payload_includes_full_usa_employee_object(): void
    {
        $employee = $this->makeEmployee([
            'id' => 1,
            'name' => 'John',
            'last_name' => 'Doe',
            'salary' => 75000,
            'country' => 'USA',
            'ssn' => '123-45-6789',
            'address' => '123 Main St',
        ]);

        $payload = $this->builder->buildPayload('EmployeeCreated', $employee);

        $empData = $payload['data']['employee'];
        $this->assertEquals(1, $empData['id']);
        $this->assertEquals('John', $empData['name']);
        $this->assertEquals('Doe', $empData['last_name']);
        $this->assertEquals(75000, $empData['salary']);
        $this->assertEquals('USA', $empData['country']);
        $this->assertEquals('123-45-6789', $empData['ssn']);
        $this->assertEquals('123 Main St', $empData['address']);
    }

    public function test_created_payload_includes_full_germany_employee_object(): void
    {
        $employee = $this->makeEmployee([
            'id' => 2,
            'name' => 'Hans',
            'last_name' => 'Mueller',
            'salary' => 65000,
            'country' => 'Germany',
            'tax_id' => 'DE123456789',
            'goal' => 'Increase productivity',
            'ssn' => null,
            'address' => null,
        ]);

        $payload = $this->builder->buildPayload('EmployeeCreated', $employee);

        $empData = $payload['data']['employee'];
        $this->assertEquals(2, $empData['id']);
        $this->assertEquals('Hans', $empData['name']);
        $this->assertEquals('Germany', $empData['country']);
        $this->assertEquals('DE123456789', $empData['tax_id']);
        $this->assertEquals('Increase productivity', $empData['goal']);
    }

    // ── EmployeeUpdated payload structure ────────────────────────────────

    public function test_updated_payload_has_correct_event_type(): void
    {
        $employee = $this->makeEmployee(['id' => 1, 'country' => 'USA']);

        $payload = $this->builder->buildPayload('EmployeeUpdated', $employee, ['salary']);

        $this->assertEquals('EmployeeUpdated', $payload['event_type']);
    }

    public function test_updated_payload_has_valid_uuid(): void
    {
        $employee = $this->makeEmployee(['id' => 1, 'country' => 'USA']);

        $payload = $this->builder->buildPayload('EmployeeUpdated', $employee, ['salary']);

        $this->assertTrue(Str::isUuid($payload['event_id']));
    }

    public function test_updated_payload_has_iso8601_timestamp(): void
    {
        $employee = $this->makeEmployee(['id' => 1, 'country' => 'USA']);

        $payload = $this->builder->buildPayload('EmployeeUpdated', $employee, ['salary']);

        $dt = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $payload['timestamp']);
        $this->assertInstanceOf(\DateTimeImmutable::class, $dt);
    }

    public function test_updated_payload_includes_full_employee_with_new_values(): void
    {
        $employee = $this->makeEmployee([
            'id' => 1,
            'name' => 'John',
            'last_name' => 'Doe',
            'salary' => 80000,
            'country' => 'USA',
            'ssn' => '123-45-6789',
            'address' => '123 Main St',
        ]);

        $payload = $this->builder->buildPayload('EmployeeUpdated', $employee, ['salary']);

        $this->assertEquals(80000, $payload['data']['employee']['salary']);
        $this->assertEquals('John', $payload['data']['employee']['name']);
    }

    // ── EmployeeDeleted payload structure ────────────────────────────────

    public function test_deleted_payload_has_correct_event_type(): void
    {
        $employee = $this->makeEmployee(['id' => 1, 'country' => 'USA']);

        $payload = $this->builder->buildPayload('EmployeeDeleted', $employee);

        $this->assertEquals('EmployeeDeleted', $payload['event_type']);
    }

    public function test_deleted_payload_has_empty_changed_fields(): void
    {
        $employee = $this->makeEmployee(['id' => 1, 'country' => 'USA']);

        $payload = $this->builder->buildPayload('EmployeeDeleted', $employee);

        $this->assertIsArray($payload['data']['changed_fields']);
        $this->assertEmpty($payload['data']['changed_fields']);
    }

    public function test_deleted_payload_includes_last_known_employee_state(): void
    {
        $employee = $this->makeEmployee([
            'id' => 3,
            'name' => 'Hans',
            'last_name' => 'Mueller',
            'salary' => 65000,
            'country' => 'Germany',
            'tax_id' => 'DE123456789',
            'goal' => 'Learn new skill',
        ]);

        $payload = $this->builder->buildPayload('EmployeeDeleted', $employee);

        $this->assertEquals(3, $payload['data']['employee_id']);
        $this->assertEquals('Germany', $payload['country']);
        $this->assertEquals(3, $payload['data']['employee']['id']);
        $this->assertEquals('Hans', $payload['data']['employee']['name']);
        $this->assertEquals('DE123456789', $payload['data']['employee']['tax_id']);
    }

    // ── changed_fields array ─────────────────────────────────────────────

    public function test_changed_fields_lists_only_modified_fields(): void
    {
        $employee = $this->makeEmployee(['id' => 1, 'country' => 'USA', 'salary' => 80000]);

        $payload = $this->builder->buildPayload('EmployeeUpdated', $employee, ['salary']);

        $this->assertEquals(['salary'], $payload['data']['changed_fields']);
    }

    public function test_changed_fields_lists_multiple_modified_fields(): void
    {
        $employee = $this->makeEmployee(['id' => 1, 'country' => 'Germany']);

        $payload = $this->builder->buildPayload('EmployeeUpdated', $employee, ['salary', 'goal']);

        $this->assertEquals(['salary', 'goal'], $payload['data']['changed_fields']);
    }

    public function test_changed_fields_excludes_unchanged_fields(): void
    {
        $employee = $this->makeEmployee(['id' => 1, 'country' => 'USA']);

        $payload = $this->builder->buildPayload('EmployeeUpdated', $employee, ['salary']);

        $this->assertNotContains('name', $payload['data']['changed_fields']);
        $this->assertNotContains('last_name', $payload['data']['changed_fields']);
        $this->assertNotContains('address', $payload['data']['changed_fields']);
    }

    // ── Routing key strategy ─────────────────────────────────────────────

    public function test_routing_key_for_created_usa(): void
    {
        $key = $this->builder->getRoutingKey('EmployeeCreated', 'USA');

        $this->assertEquals('employee.created.USA', $key);
    }

    public function test_routing_key_for_updated_germany(): void
    {
        $key = $this->builder->getRoutingKey('EmployeeUpdated', 'Germany');

        $this->assertEquals('employee.updated.Germany', $key);
    }

    public function test_routing_key_for_deleted_usa(): void
    {
        $key = $this->builder->getRoutingKey('EmployeeDeleted', 'USA');

        $this->assertEquals('employee.deleted.USA', $key);
    }

    // ── Edge case: concurrent updates → distinct event_ids ───────────────

    public function test_concurrent_updates_generate_distinct_event_ids(): void
    {
        $employee = $this->makeEmployee(['id' => 1, 'country' => 'USA']);

        $eventIds = [];
        for ($i = 0; $i < 100; $i++) {
            $payload = $this->builder->buildPayload('EmployeeUpdated', $employee, ['salary']);
            $eventIds[] = $payload['event_id'];
        }

        $this->assertCount(100, array_unique($eventIds));
    }

    // ── Country propagation ──────────────────────────────────────────────

    public function test_payload_country_matches_usa_employee(): void
    {
        $employee = $this->makeEmployee(['id' => 1, 'country' => 'USA']);

        $payload = $this->builder->buildPayload('EmployeeCreated', $employee);

        $this->assertEquals('USA', $payload['country']);
    }

    public function test_payload_country_matches_germany_employee(): void
    {
        $employee = $this->makeEmployee(['id' => 2, 'country' => 'Germany']);

        $payload = $this->builder->buildPayload('EmployeeCreated', $employee);

        $this->assertEquals('Germany', $payload['country']);
    }

    // ── Employee ID in data ──────────────────────────────────────────────

    public function test_payload_data_employee_id_matches_employee(): void
    {
        $employee = $this->makeEmployee(['id' => 42, 'country' => 'USA']);

        $payload = $this->builder->buildPayload('EmployeeCreated', $employee);

        $this->assertEquals(42, $payload['data']['employee_id']);
    }

    // ── Helper ───────────────────────────────────────────────────────────

    private function makeEmployee(array $attributes = []): Employee
    {
        $defaults = [
            'id' => 1,
            'name' => 'John',
            'last_name' => 'Doe',
            'salary' => 75000,
            'country' => 'USA',
            'ssn' => '123-45-6789',
            'address' => '123 Main St',
            'tax_id' => null,
            'goal' => null,
        ];

        $employee = new Employee();
        $employee->forceFill(array_merge($defaults, $attributes));

        return $employee;
    }
}
