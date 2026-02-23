<?php

namespace Tests\Feature;

use App\Services\CacheService;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class ChecklistApiTest extends TestCase
{
    private CacheService $cacheService;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::flushall();
        $this->cacheService = new CacheService();
    }

    protected function tearDown(): void
    {
        Redis::flushall();
        parent::tearDown();
    }

    private function cacheUsaEmployee(int $id, array $overrides = []): void
    {
        $this->cacheService->cacheEmployee($id, array_merge([
            'id' => $id,
            'name' => 'John',
            'last_name' => 'Doe',
            'salary' => 75000,
            'country' => 'USA',
            'ssn' => '123-45-6789',
            'address' => '123 Main St',
        ], $overrides));
    }

    private function cacheGermanyEmployee(int $id, array $overrides = []): void
    {
        $this->cacheService->cacheEmployee($id, array_merge([
            'id' => $id,
            'name' => 'Hans',
            'last_name' => 'Mueller',
            'salary' => 65000,
            'country' => 'Germany',
            'tax_id' => 'DE123456789',
            'goal' => 'Increase productivity',
        ], $overrides));
    }

    // ── Successful responses ─────────────────────────────────────────────

    public function test_returns_checklist_data_for_usa_employees(): void
    {
        $this->cacheUsaEmployee(1);
        $this->cacheUsaEmployee(2, ['ssn' => '']);

        $response = $this->getJson('/api/checklists?country=USA');

        $response->assertOk();
        $response->assertJsonStructure([
            'summary' => ['country', 'total_employees', 'complete', 'incomplete', 'completion_rate'],
            'employees' => [
                '*' => ['id', 'name', 'last_name', 'overall_completion', 'checklist'],
            ],
        ]);

        $data = $response->json();
        $this->assertEquals('USA', $data['summary']['country']);
        $this->assertEquals(2, $data['summary']['total_employees']);
    }

    public function test_returns_checklist_data_for_germany_employees(): void
    {
        $this->cacheGermanyEmployee(1);

        $response = $this->getJson('/api/checklists?country=Germany');

        $response->assertOk();

        $data = $response->json();
        $this->assertEquals('Germany', $data['summary']['country']);
        $this->assertEquals(1, $data['summary']['total_employees']);
        $this->assertEquals(100, $data['employees'][0]['overall_completion']);
    }

    public function test_returns_correct_completion_percentage_per_employee(): void
    {
        $this->cacheUsaEmployee(1); // complete
        $this->cacheUsaEmployee(2, ['address' => '']); // 2/3

        $response = $this->getJson('/api/checklists?country=USA');

        $response->assertOk();

        $employees = $response->json('employees');
        $emp1 = collect($employees)->firstWhere('id', 1);
        $emp2 = collect($employees)->firstWhere('id', 2);

        $this->assertEquals(100, $emp1['overall_completion']);
        $this->assertEquals(66.67, $emp2['overall_completion']);
    }

    public function test_returns_list_of_missing_fields_per_employee(): void
    {
        $this->cacheUsaEmployee(1, ['ssn' => null, 'address' => '']);

        $response = $this->getJson('/api/checklists?country=USA');

        $response->assertOk();

        $checklist = $response->json('employees.0.checklist');
        $incomplete = collect($checklist)->where('status', 'incomplete');

        $this->assertCount(2, $incomplete);
        $fields = $incomplete->pluck('field')->toArray();
        $this->assertContains('ssn', $fields);
        $this->assertContains('address', $fields);

        foreach ($incomplete as $item) {
            $this->assertNotNull($item['message']);
        }
    }

    // ── Cache behavior ───────────────────────────────────────────────────

    public function test_second_request_uses_cached_result(): void
    {
        $this->cacheUsaEmployee(1);

        // First request — computes and caches
        $response1 = $this->getJson('/api/checklists?country=USA');
        $response1->assertOk();

        // Remove employee from cache to prove second request uses cached checklist
        $this->cacheService->removeEmployee(1);

        // Second request — should return cached checklist result
        $response2 = $this->getJson('/api/checklists?country=USA');
        $response2->assertOk();

        $this->assertEquals($response1->json(), $response2->json());
    }

    // ── Error responses ──────────────────────────────────────────────────

    public function test_returns_422_for_unsupported_country(): void
    {
        $response = $this->getJson('/api/checklists?country=France');

        $response->assertStatus(422);
        $response->assertJsonStructure(['error']);
    }

    public function test_returns_422_for_missing_country_parameter(): void
    {
        $response = $this->getJson('/api/checklists');

        $response->assertStatus(422);
        $response->assertJsonStructure(['error']);
    }

    // ── Empty state ──────────────────────────────────────────────────────

    public function test_returns_empty_employees_with_zero_completion_when_no_employees(): void
    {
        $response = $this->getJson('/api/checklists?country=USA');

        $response->assertOk();

        $data = $response->json();
        $this->assertEquals(0, $data['summary']['total_employees']);
        $this->assertEquals(0, $data['summary']['completion_rate']);
        $this->assertEmpty($data['employees']);
    }
}
