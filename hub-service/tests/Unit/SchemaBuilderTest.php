<?php

namespace Tests\Unit;

use App\ServerUI\SchemaBuilder;
use Tests\TestCase;

class SchemaBuilderTest extends TestCase
{
    private SchemaBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new SchemaBuilder();
    }

    // ── Dashboard widgets ────────────────────────────────────────────────

    public function test_dashboard_usa_returns_three_widgets(): void
    {
        $schema = $this->builder->getSchema('dashboard', 'USA');

        $this->assertArrayHasKey('widgets', $schema);
        $this->assertCount(3, $schema['widgets']);
    }

    public function test_dashboard_germany_returns_two_widgets(): void
    {
        $schema = $this->builder->getSchema('dashboard', 'Germany');

        $this->assertArrayHasKey('widgets', $schema);
        $this->assertCount(2, $schema['widgets']);
    }

    public function test_dashboard_usa_widget_ids(): void
    {
        $schema = $this->builder->getSchema('dashboard', 'USA');
        $ids = array_column($schema['widgets'], 'id');

        $this->assertContains('employee_count', $ids);
        $this->assertContains('average_salary', $ids);
        $this->assertContains('completion_rate', $ids);
    }

    public function test_dashboard_germany_widget_ids(): void
    {
        $schema = $this->builder->getSchema('dashboard', 'Germany');
        $ids = array_column($schema['widgets'], 'id');

        $this->assertContains('employee_count', $ids);
        $this->assertContains('goal_tracking', $ids);
        $this->assertNotContains('completion_rate', $ids);
    }

    public function test_each_widget_has_required_fields(): void
    {
        $schema = $this->builder->getSchema('dashboard', 'USA');

        foreach ($schema['widgets'] as $widget) {
            $this->assertArrayHasKey('id', $widget);
            $this->assertArrayHasKey('type', $widget);
            $this->assertArrayHasKey('title', $widget);
            $this->assertArrayHasKey('data_source', $widget);
            $this->assertArrayHasKey('realtime_channel', $widget);
        }
    }

    public function test_completion_rate_widget_has_correct_realtime_channel(): void
    {
        $schema = $this->builder->getSchema('dashboard', 'USA');
        $widget = collect($schema['widgets'])->firstWhere('id', 'completion_rate');

        $this->assertEquals('checklist.USA', $widget['realtime_channel']);
    }

    // ── Employees step schema (field definitions) ────────────────────────

    public function test_employees_usa_returns_field_definitions(): void
    {
        $schema = $this->builder->getSchema('employees', 'USA');

        $this->assertArrayHasKey('fields', $schema);
        $names = array_column($schema['fields'], 'name');

        $this->assertContains('name', $names);
        $this->assertContains('last_name', $names);
        $this->assertContains('salary', $names);
        $this->assertContains('ssn', $names);
        $this->assertContains('address', $names);
    }

    public function test_employees_germany_returns_field_definitions(): void
    {
        $schema = $this->builder->getSchema('employees', 'Germany');

        $this->assertArrayHasKey('fields', $schema);
        $names = array_column($schema['fields'], 'name');

        $this->assertContains('name', $names);
        $this->assertContains('last_name', $names);
        $this->assertContains('salary', $names);
        $this->assertContains('tax_id', $names);
        $this->assertContains('goal', $names);
    }

    public function test_usa_schema_excludes_germany_fields(): void
    {
        $schema = $this->builder->getSchema('employees', 'USA');
        $names = array_column($schema['fields'], 'name');

        $this->assertNotContains('tax_id', $names);
        $this->assertNotContains('goal', $names);
    }

    public function test_germany_schema_excludes_usa_fields(): void
    {
        $schema = $this->builder->getSchema('employees', 'Germany');
        $names = array_column($schema['fields'], 'name');

        $this->assertNotContains('ssn', $names);
        $this->assertNotContains('address', $names);
    }

    public function test_each_field_has_required_properties(): void
    {
        $schema = $this->builder->getSchema('employees', 'USA');

        foreach ($schema['fields'] as $field) {
            $this->assertArrayHasKey('name', $field);
            $this->assertArrayHasKey('type', $field);
            $this->assertArrayHasKey('label', $field);
            $this->assertArrayHasKey('required', $field);
        }
    }

    // ── Unknown step ─────────────────────────────────────────────────────

    public function test_unknown_step_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->builder->getSchema('nonexistent', 'USA');
    }

    public function test_unsupported_country_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->builder->getSchema('dashboard', 'France');
    }
}
