<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Services\RabbitMQService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeCrudTest extends TestCase
{
    use RefreshDatabase;

    // ── POST /api/employees — Create ──────────────────────────────────

    public function test_create_usa_employee_returns_201(): void
    {
        $payload = [
            'name' => 'John',
            'last_name' => 'Doe',
            'salary' => 75000,
            'country' => 'USA',
            'ssn' => '123-45-6789',
            'address' => '123 Main St, New York, NY',
        ];

        $response = $this->postJson('/api/employees', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'John'])
            ->assertJsonFragment(['country' => 'USA']);

        $this->assertDatabaseHas('employees', [
            'name' => 'John',
            'last_name' => 'Doe',
            'country' => 'USA',
            'ssn' => '123-45-6789',
        ]);
    }

    public function test_create_usa_employee_masks_ssn_in_response(): void
    {
        $payload = [
            'name' => 'Jane',
            'last_name' => 'Smith',
            'salary' => 80000,
            'country' => 'USA',
            'ssn' => '987-65-4321',
            'address' => '456 Oak Ave',
        ];

        $response = $this->postJson('/api/employees', $payload);

        $response->assertStatus(201);
        $data = $response->json('data');
        $this->assertEquals('***-**-4321', $data['ssn']);
        $this->assertStringNotContainsString('987-65-4321', json_encode($data));
    }

    public function test_create_germany_employee_returns_201(): void
    {
        $payload = [
            'name' => 'Hans',
            'last_name' => 'Mueller',
            'salary' => 65000,
            'country' => 'Germany',
            'tax_id' => 'DE123456789',
            'goal' => 'Increase productivity',
        ];

        $response = $this->postJson('/api/employees', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'Hans'])
            ->assertJsonFragment(['country' => 'Germany']);

        $this->assertDatabaseHas('employees', [
            'name' => 'Hans',
            'country' => 'Germany',
            'tax_id' => 'DE123456789',
        ]);
    }

    public function test_create_employee_with_invalid_country_returns_422(): void
    {
        $payload = [
            'name' => 'Pierre',
            'last_name' => 'Dupont',
            'salary' => 60000,
            'country' => 'France',
        ];

        $response = $this->postJson('/api/employees', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['country']);

        $this->assertDatabaseMissing('employees', ['name' => 'Pierre']);
    }

    public function test_create_germany_employee_with_invalid_tax_id_returns_422(): void
    {
        $payload = [
            'name' => 'Hans',
            'last_name' => 'Mueller',
            'salary' => 65000,
            'country' => 'Germany',
            'tax_id' => 'INVALID',
            'goal' => 'Increase productivity',
        ];

        $response = $this->postJson('/api/employees', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tax_id']);
    }

    public function test_create_employee_with_missing_required_fields_returns_422(): void
    {
        $response = $this->postJson('/api/employees', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'last_name', 'salary', 'country']);
    }

    // ── GET /api/employees — Index ────────────────────────────────────

    public function test_index_returns_paginated_employees(): void
    {
        Employee::factory()->count(3)->usa()->create();
        Employee::factory()->count(2)->germany()->create();

        $response = $this->getJson('/api/employees');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [['id', 'name', 'last_name', 'salary', 'country']],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);

        $this->assertEquals(5, $response->json('meta.total'));
    }

    public function test_index_filters_by_country(): void
    {
        Employee::factory()->count(3)->usa()->create();
        Employee::factory()->count(2)->germany()->create();

        $response = $this->getJson('/api/employees?country=USA');

        $response->assertStatus(200);
        $this->assertEquals(3, $response->json('meta.total'));

        $countries = collect($response->json('data'))->pluck('country')->unique();
        $this->assertEquals(['USA'], $countries->values()->all());
    }

    public function test_index_masks_ssn_for_usa_employees(): void
    {
        Employee::factory()->usa()->create(['ssn' => '123-45-6789']);

        $response = $this->getJson('/api/employees?country=USA');

        $response->assertStatus(200);
        $employee = $response->json('data.0');
        $this->assertEquals('***-**-6789', $employee['ssn']);
    }

    // ── GET /api/employees/{id} — Show ────────────────────────────────

    public function test_show_returns_single_employee(): void
    {
        $employee = Employee::factory()->usa()->create();

        $response = $this->getJson("/api/employees/{$employee->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['id' => $employee->id]);
    }

    public function test_show_masks_ssn(): void
    {
        $employee = Employee::factory()->usa()->create(['ssn' => '111-22-3333']);

        $response = $this->getJson("/api/employees/{$employee->id}");

        $response->assertStatus(200);
        $this->assertEquals('***-**-3333', $response->json('data.ssn'));
    }

    public function test_show_nonexistent_employee_returns_404(): void
    {
        $response = $this->getJson('/api/employees/99999');

        $response->assertStatus(404);
    }

    // ── PUT /api/employees/{id} — Update ──────────────────────────────

    public function test_update_employee_salary(): void
    {
        $employee = Employee::factory()->usa()->create(['salary' => 75000]);

        $response = $this->putJson("/api/employees/{$employee->id}", [
            'name' => $employee->name,
            'last_name' => $employee->last_name,
            'salary' => 80000,
            'country' => 'USA',
            'ssn' => $employee->ssn,
            'address' => $employee->address,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'salary' => 80000,
        ]);
    }

    public function test_update_nonexistent_employee_returns_404(): void
    {
        $response = $this->putJson('/api/employees/99999', [
            'name' => 'Ghost',
            'last_name' => 'User',
            'salary' => 50000,
            'country' => 'USA',
            'ssn' => '111-22-3333',
            'address' => '123 Nowhere',
        ]);

        $response->assertStatus(404);
    }

    public function test_update_with_invalid_data_returns_422(): void
    {
        $employee = Employee::factory()->germany()->create();

        $response = $this->putJson("/api/employees/{$employee->id}", [
            'name' => $employee->name,
            'last_name' => $employee->last_name,
            'salary' => 65000,
            'country' => 'Germany',
            'tax_id' => 'INVALID',
            'goal' => $employee->goal,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tax_id']);
    }

    // ── DELETE /api/employees/{id} — Destroy ──────────────────────────

    public function test_delete_employee_returns_204(): void
    {
        $employee = Employee::factory()->usa()->create();

        $response = $this->deleteJson("/api/employees/{$employee->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('employees', ['id' => $employee->id]);
    }

    public function test_delete_nonexistent_employee_returns_404(): void
    {
        $response = $this->deleteJson('/api/employees/99999');

        $response->assertStatus(404);
    }

    // ── Failure: RabbitMQ Unavailable ─────────────────────────────────

    public function test_create_succeeds_when_rabbitmq_unavailable(): void
    {
        // Mock RabbitMQService to throw (simulating RabbitMQ down)
        $mock = $this->mock(RabbitMQService::class);
        $mock->shouldReceive('publish')
            ->once()
            ->andThrow(new \RuntimeException('Connection refused'));

        $payload = [
            'name' => 'John',
            'last_name' => 'Doe',
            'salary' => 75000,
            'country' => 'USA',
            'ssn' => '123-45-6789',
            'address' => '123 Main St',
        ];

        $response = $this->postJson('/api/employees', $payload);

        // DB write must still succeed, never return 500
        $response->assertStatus(201);
        $this->assertDatabaseHas('employees', ['name' => 'John']);
    }

    public function test_update_succeeds_when_rabbitmq_unavailable(): void
    {
        $employee = Employee::factory()->usa()->create(['salary' => 70000]);

        $mock = $this->mock(RabbitMQService::class);
        $mock->shouldReceive('publish')
            ->once()
            ->andThrow(new \RuntimeException('Connection refused'));

        $response = $this->putJson("/api/employees/{$employee->id}", [
            'name' => $employee->name,
            'last_name' => $employee->last_name,
            'salary' => 85000,
            'country' => 'USA',
            'ssn' => $employee->ssn,
            'address' => $employee->address,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'salary' => 85000]);
    }

    public function test_delete_succeeds_when_rabbitmq_unavailable(): void
    {
        $employee = Employee::factory()->usa()->create();

        $mock = $this->mock(RabbitMQService::class);
        $mock->shouldReceive('publish')
            ->once()
            ->andThrow(new \RuntimeException('Connection refused'));

        $response = $this->deleteJson("/api/employees/{$employee->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('employees', ['id' => $employee->id]);
    }
}
