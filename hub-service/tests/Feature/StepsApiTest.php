<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class StepsApiTest extends TestCase
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

    public function test_usa_returns_two_steps(): void
    {
        $response = $this->getJson('/api/steps?country=USA');

        $response->assertOk();

        $steps = $response->json('steps');
        $this->assertCount(2, $steps);

        $ids = array_column($steps, 'id');
        $this->assertEquals(['dashboard', 'employees'], $ids);
    }

    public function test_germany_returns_three_steps(): void
    {
        $response = $this->getJson('/api/steps?country=Germany');

        $response->assertOk();

        $steps = $response->json('steps');
        $this->assertCount(3, $steps);

        $ids = array_column($steps, 'id');
        $this->assertEquals(['dashboard', 'employees', 'documentation'], $ids);
    }

    public function test_each_step_has_required_fields(): void
    {
        $response = $this->getJson('/api/steps?country=USA');

        $response->assertOk();
        $response->assertJsonStructure([
            'steps' => [
                '*' => ['id', 'label', 'icon', 'path', 'order'],
            ],
        ]);
    }

    public function test_returns_422_for_missing_country(): void
    {
        $response = $this->getJson('/api/steps');

        $response->assertStatus(422);
        $response->assertJsonStructure(['error']);
    }

    public function test_returns_422_for_unsupported_country(): void
    {
        $response = $this->getJson('/api/steps?country=France');

        $response->assertStatus(422);
        $response->assertJsonStructure(['error']);
    }

    public function test_second_request_uses_cached_result(): void
    {
        $response1 = $this->getJson('/api/steps?country=USA');
        $response1->assertOk();

        $response2 = $this->getJson('/api/steps?country=USA');
        $response2->assertOk();

        $this->assertEquals($response1->json(), $response2->json());
    }
}
