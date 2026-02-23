<?php

namespace Tests\Unit;

use App\ServerUI\StepRegistry;
use Tests\TestCase;

class StepRegistryTest extends TestCase
{
    private StepRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new StepRegistry();
    }

    public function test_usa_returns_two_steps(): void
    {
        $steps = $this->registry->getSteps('USA');

        $this->assertCount(2, $steps);
    }

    public function test_germany_returns_three_steps(): void
    {
        $steps = $this->registry->getSteps('Germany');

        $this->assertCount(3, $steps);
    }

    public function test_usa_steps_are_dashboard_and_employees(): void
    {
        $steps = $this->registry->getSteps('USA');
        $ids = array_column($steps, 'id');

        $this->assertEquals(['dashboard', 'employees'], $ids);
    }

    public function test_germany_steps_include_documentation(): void
    {
        $steps = $this->registry->getSteps('Germany');
        $ids = array_column($steps, 'id');

        $this->assertEquals(['dashboard', 'employees', 'documentation'], $ids);
    }

    public function test_each_step_has_required_fields(): void
    {
        $steps = $this->registry->getSteps('USA');

        foreach ($steps as $step) {
            $this->assertArrayHasKey('id', $step);
            $this->assertArrayHasKey('label', $step);
            $this->assertArrayHasKey('icon', $step);
            $this->assertArrayHasKey('path', $step);
            $this->assertArrayHasKey('order', $step);
        }
    }

    public function test_steps_are_ordered_correctly(): void
    {
        $steps = $this->registry->getSteps('Germany');

        $this->assertEquals(1, $steps[0]['order']);
        $this->assertEquals(2, $steps[1]['order']);
        $this->assertEquals(3, $steps[2]['order']);
    }

    public function test_unsupported_country_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->registry->getSteps('France');
    }
}
