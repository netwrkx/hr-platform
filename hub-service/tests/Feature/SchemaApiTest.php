<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class SchemaApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Redis::flushall();
    }

    protected function tearDown(): void
    {
        Redis::flushall();
        parent::tearDown();
    }

    // ── Dashboard schema ─────────────────────────────────────────────────

    public function test_dashboard_usa_returns_three_widgets(): void
    {
        $response = $this->getJson('/api/schema/dashboard?country=USA');

        $response->assertOk();

        $widgets = $response->json('widgets');
        $this->assertCount(3, $widgets);

        $ids = array_column($widgets, 'id');
        $this->assertContains('employee_count', $ids);
        $this->assertContains('average_salary', $ids);
        $this->assertContains('completion_rate', $ids);
    }

    public function test_dashboard_germany_returns_two_widgets(): void
    {
        $response = $this->getJson('/api/schema/dashboard?country=Germany');

        $response->assertOk();

        $widgets = $response->json('widgets');
        $this->assertCount(2, $widgets);

        $ids = array_column($widgets, 'id');
        $this->assertContains('employee_count', $ids);
        $this->assertContains('goal_tracking', $ids);
    }

    public function test_each_widget_has_required_fields(): void
    {
        $response = $this->getJson('/api/schema/dashboard?country=USA');

        $response->assertOk();
        $response->assertJsonStructure([
            'widgets' => [
                '*' => ['id', 'type', 'title', 'data_source', 'realtime_channel'],
            ],
        ]);
    }

    public function test_completion_rate_widget_realtime_channel(): void
    {
        $response = $this->getJson('/api/schema/dashboard?country=USA');

        $response->assertOk();

        $widgets = $response->json('widgets');
        $completionRate = collect($widgets)->firstWhere('id', 'completion_rate');
        $this->assertEquals('checklist.USA', $completionRate['realtime_channel']);
    }

    // ── Employees step schema ────────────────────────────────────────────

    public function test_employees_usa_returns_field_definitions(): void
    {
        $response = $this->getJson('/api/schema/employees?country=USA');

        $response->assertOk();

        $fields = $response->json('fields');
        $names = array_column($fields, 'name');

        $this->assertContains('name', $names);
        $this->assertContains('last_name', $names);
        $this->assertContains('salary', $names);
        $this->assertContains('ssn', $names);
        $this->assertContains('address', $names);
        $this->assertNotContains('tax_id', $names);
        $this->assertNotContains('goal', $names);
    }

    public function test_employees_germany_returns_field_definitions(): void
    {
        $response = $this->getJson('/api/schema/employees?country=Germany');

        $response->assertOk();

        $fields = $response->json('fields');
        $names = array_column($fields, 'name');

        $this->assertContains('name', $names);
        $this->assertContains('last_name', $names);
        $this->assertContains('salary', $names);
        $this->assertContains('tax_id', $names);
        $this->assertContains('goal', $names);
        $this->assertNotContains('ssn', $names);
        $this->assertNotContains('address', $names);
    }

    public function test_each_field_has_required_properties(): void
    {
        $response = $this->getJson('/api/schema/employees?country=USA');

        $response->assertOk();
        $response->assertJsonStructure([
            'fields' => [
                '*' => ['name', 'type', 'label', 'required'],
            ],
        ]);
    }

    // ── Error cases ──────────────────────────────────────────────────────

    public function test_unknown_step_returns_404(): void
    {
        $response = $this->getJson('/api/schema/nonexistent?country=USA');

        $response->assertStatus(404);
    }

    public function test_missing_country_returns_422(): void
    {
        $response = $this->getJson('/api/schema/dashboard');

        $response->assertStatus(422);
        $response->assertJsonStructure(['error']);
    }

    public function test_unsupported_country_returns_422(): void
    {
        $response = $this->getJson('/api/schema/dashboard?country=France');

        $response->assertStatus(422);
        $response->assertJsonStructure(['error']);
    }

    // ── Cache ────────────────────────────────────────────────────────────

    public function test_second_request_uses_cached_result(): void
    {
        $response1 = $this->getJson('/api/schema/dashboard?country=USA');
        $response1->assertOk();

        $response2 = $this->getJson('/api/schema/dashboard?country=USA');
        $response2->assertOk();

        $this->assertEquals($response1->json(), $response2->json());
    }
}
