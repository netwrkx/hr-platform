<?php

namespace Tests\Feature;

use App\Services\CacheService;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class EmployeeApiTest extends TestCase
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

    // ── Employee list ────────────────────────────────────────────────────

    public function test_returns_employee_list_for_usa(): void
    {
        $this->cacheUsaEmployee(1, ['name' => 'Alice', 'last_name' => 'Smith']);
        $this->cacheUsaEmployee(2, ['name' => 'Bob', 'last_name' => 'Jones']);

        $response = $this->getJson('/api/employees?country=USA');

        $response->assertOk();
        $response->assertJsonStructure([
            'columns',
            'data',
            'pagination' => ['total', 'per_page', 'current_page', 'last_page'],
        ]);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_returns_employee_list_for_germany(): void
    {
        $this->cacheGermanyEmployee(1);

        $response = $this->getJson('/api/employees?country=Germany');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_filters_by_country(): void
    {
        $this->cacheUsaEmployee(1);
        $this->cacheGermanyEmployee(2);

        $usaResponse = $this->getJson('/api/employees?country=USA');
        $germanyResponse = $this->getJson('/api/employees?country=Germany');

        $this->assertCount(1, $usaResponse->json('data'));
        $this->assertCount(1, $germanyResponse->json('data'));
    }

    // ── Column definitions ───────────────────────────────────────────────

    public function test_usa_response_includes_column_definitions(): void
    {
        $this->cacheUsaEmployee(1);

        $response = $this->getJson('/api/employees?country=USA');

        $response->assertOk();

        $columns = $response->json('columns');
        $keys = array_column($columns, 'key');

        $this->assertContains('ssn', $keys);
        $this->assertNotContains('goal', $keys);
    }

    public function test_germany_response_includes_column_definitions(): void
    {
        $this->cacheGermanyEmployee(1);

        $response = $this->getJson('/api/employees?country=Germany');

        $response->assertOk();

        $columns = $response->json('columns');
        $keys = array_column($columns, 'key');

        $this->assertContains('goal', $keys);
        $this->assertNotContains('ssn', $keys);
    }

    // ── SSN masking ──────────────────────────────────────────────────────

    public function test_usa_ssn_is_masked_in_response(): void
    {
        $this->cacheUsaEmployee(1, ['ssn' => '123-45-6789']);

        $response = $this->getJson('/api/employees?country=USA');

        $response->assertOk();

        $employee = $response->json('data.0');
        $this->assertEquals('***-**-6789', $employee['ssn']);
    }

    // ── Pagination ───────────────────────────────────────────────────────

    public function test_pagination_defaults(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->cacheUsaEmployee($i, ['name' => "Employee{$i}"]);
        }

        $response = $this->getJson('/api/employees?country=USA&page=1&per_page=2');

        $response->assertOk();

        $pagination = $response->json('pagination');
        $this->assertEquals(5, $pagination['total']);
        $this->assertEquals(2, $pagination['per_page']);
        $this->assertEquals(1, $pagination['current_page']);
        $this->assertEquals(3, $pagination['last_page']);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_pagination_second_page(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->cacheUsaEmployee($i, ['name' => "Employee{$i}"]);
        }

        $response = $this->getJson('/api/employees?country=USA&page=2&per_page=2');

        $response->assertOk();
        $this->assertEquals(2, $response->json('pagination.current_page'));
        $this->assertCount(2, $response->json('data'));
    }

    // ── Cache ────────────────────────────────────────────────────────────

    public function test_second_request_uses_cached_result(): void
    {
        $this->cacheUsaEmployee(1);

        $response1 = $this->getJson('/api/employees?country=USA');
        $response1->assertOk();

        // Remove from individual cache — the list cache should still serve
        $this->cacheService->removeEmployee(1);

        $response2 = $this->getJson('/api/employees?country=USA');
        $response2->assertOk();

        $this->assertEquals($response1->json(), $response2->json());
    }

    // ── Error cases ──────────────────────────────────────────────────────

    public function test_returns_422_for_unsupported_country(): void
    {
        $response = $this->getJson('/api/employees?country=France');

        $response->assertStatus(422);
        $response->assertJsonStructure(['error']);
    }

    public function test_returns_422_for_missing_country(): void
    {
        $response = $this->getJson('/api/employees');

        $response->assertStatus(422);
        $response->assertJsonStructure(['error']);
    }

    // ── Empty state ──────────────────────────────────────────────────────

    public function test_returns_empty_list_when_no_employees(): void
    {
        $response = $this->getJson('/api/employees?country=USA');

        $response->assertOk();
        $this->assertEmpty($response->json('data'));
        $this->assertEquals(0, $response->json('pagination.total'));
    }
}
