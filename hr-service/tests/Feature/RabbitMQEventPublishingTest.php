<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Services\RabbitMQService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

class RabbitMQEventPublishingTest extends TestCase
{
    use RefreshDatabase;

    // ── Integration: Employee creation triggers EmployeeCreated ───────────

    public function test_employee_creation_publishes_created_event_to_rabbitmq(): void
    {
        $publishedMessages = [];

        $mock = $this->mock(RabbitMQService::class);
        $mock->shouldReceive('publish')
            ->once()
            ->withArgs(function (string $exchange, string $routingKey, array $payload) use (&$publishedMessages) {
                $publishedMessages[] = compact('exchange', 'routingKey', 'payload');
                return true;
            });

        $response = $this->postJson('/api/employees', [
            'name' => 'John',
            'last_name' => 'Doe',
            'salary' => 75000,
            'country' => 'USA',
            'ssn' => '123-45-6789',
            'address' => '123 Main St',
        ]);

        $response->assertStatus(201);
        $this->assertCount(1, $publishedMessages);

        $msg = $publishedMessages[0];
        $this->assertEquals('hr.events', $msg['exchange']);
        $this->assertEquals('employee.created.USA', $msg['routingKey']);
        $this->assertEquals('EmployeeCreated', $msg['payload']['event_type']);
        $this->assertTrue(Str::isUuid($msg['payload']['event_id']));
        $this->assertEquals('USA', $msg['payload']['country']);
        $this->assertEmpty($msg['payload']['data']['changed_fields']);
        $this->assertEquals('John', $msg['payload']['data']['employee']['name']);
        $this->assertEquals('Doe', $msg['payload']['data']['employee']['last_name']);
        $this->assertEquals(75000, $msg['payload']['data']['employee']['salary']);
        $this->assertEquals('123-45-6789', $msg['payload']['data']['employee']['ssn']);
    }

    // ── Integration: Employee update triggers EmployeeUpdated ─────────────

    public function test_employee_update_publishes_updated_event_with_changed_fields(): void
    {
        $employee = Employee::factory()->usa()->create(['salary' => 75000]);

        $publishedMessages = [];

        $mock = $this->mock(RabbitMQService::class);
        $mock->shouldReceive('publish')
            ->once()
            ->withArgs(function (string $exchange, string $routingKey, array $payload) use (&$publishedMessages) {
                $publishedMessages[] = compact('exchange', 'routingKey', 'payload');
                return true;
            });

        $response = $this->putJson("/api/employees/{$employee->id}", [
            'name' => $employee->name,
            'last_name' => $employee->last_name,
            'salary' => 80000,
            'country' => 'USA',
            'ssn' => $employee->ssn,
            'address' => $employee->address,
        ]);

        $response->assertStatus(200);
        $this->assertCount(1, $publishedMessages);

        $msg = $publishedMessages[0];
        $this->assertEquals('hr.events', $msg['exchange']);
        $this->assertEquals('employee.updated.USA', $msg['routingKey']);
        $this->assertEquals('EmployeeUpdated', $msg['payload']['event_type']);
        $this->assertContains('salary', $msg['payload']['data']['changed_fields']);
        $this->assertEquals(80000, $msg['payload']['data']['employee']['salary']);
        $this->assertNotEmpty($msg['payload']['data']['employee_id']);
    }

    // ── Integration: Employee deletion triggers EmployeeDeleted ──────────

    public function test_employee_deletion_publishes_deleted_event_with_last_known_state(): void
    {
        $employee = Employee::factory()->germany()->create([
            'name' => 'Hans',
            'last_name' => 'Mueller',
        ]);

        $publishedMessages = [];

        $mock = $this->mock(RabbitMQService::class);
        $mock->shouldReceive('publish')
            ->once()
            ->withArgs(function (string $exchange, string $routingKey, array $payload) use (&$publishedMessages) {
                $publishedMessages[] = compact('exchange', 'routingKey', 'payload');
                return true;
            });

        $response = $this->deleteJson("/api/employees/{$employee->id}");

        $response->assertStatus(204);
        $this->assertCount(1, $publishedMessages);

        $msg = $publishedMessages[0];
        $this->assertEquals('hr.events', $msg['exchange']);
        $this->assertEquals('employee.deleted.Germany', $msg['routingKey']);
        $this->assertEquals('EmployeeDeleted', $msg['payload']['event_type']);
        $this->assertEquals($employee->id, $msg['payload']['data']['employee_id']);
        $this->assertEquals('Germany', $msg['payload']['country']);
        $this->assertEmpty($msg['payload']['data']['changed_fields']);

        // Last known state must include the employee data
        $this->assertEquals('Hans', $msg['payload']['data']['employee']['name']);
        $this->assertEquals('Mueller', $msg['payload']['data']['employee']['last_name']);
    }

    // ── Failure: RabbitMQ unavailable ─────────────────────────────────────

    public function test_rabbitmq_unavailable_db_write_succeeds_error_logged_201_returned(): void
    {
        Log::spy();

        $mock = $this->mock(RabbitMQService::class);
        $mock->shouldReceive('publish')
            ->once()
            ->andThrow(new \RuntimeException('Connection refused'));

        $response = $this->postJson('/api/employees', [
            'name' => 'John',
            'last_name' => 'Doe',
            'salary' => 75000,
            'country' => 'USA',
            'ssn' => '123-45-6789',
            'address' => '123 Main St',
        ]);

        // DB write must succeed — never return 500
        $response->assertStatus(201);
        $this->assertDatabaseHas('employees', ['name' => 'John']);

        // Structured error log with event_type, employee_id, exception message
        Log::shouldHaveReceived('error')
            ->withArgs(function (string $message, array $context) {
                return str_contains($message, 'Failed to publish')
                    && $context['event_type'] === 'EmployeeCreated'
                    && isset($context['employee_id'])
                    && str_contains($context['exception'], 'Connection refused');
            });
    }
}
