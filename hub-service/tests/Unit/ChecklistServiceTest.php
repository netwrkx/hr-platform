<?php

namespace Tests\Unit;

use App\Services\CacheService;
use App\Services\ChecklistService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class ChecklistServiceTest extends TestCase
{
    private CacheService $cacheService;
    private ChecklistService $checklistService;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::flushall();
        $this->cacheService = new CacheService();
        $this->checklistService = new ChecklistService($this->cacheService);
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

    // ── USA checklist rules ─────────────────────────────────────────────

    public function test_usa_complete_employee_has_100_percent(): void
    {
        $this->cacheUsaEmployee(1);

        $result = $this->checklistService->evaluate('USA');

        $employee = $result['employees'][0];
        $this->assertEquals(100, $employee['overall_completion']);
        $this->assertCount(3, $employee['checklist']);

        foreach ($employee['checklist'] as $item) {
            $this->assertEquals('complete', $item['status']);
            $this->assertNull($item['message']);
        }
    }

    public function test_usa_missing_ssn_is_incomplete(): void
    {
        $this->cacheUsaEmployee(1, ['ssn' => null]);

        $result = $this->checklistService->evaluate('USA');

        $checklist = collect($result['employees'][0]['checklist']);
        $ssn = $checklist->firstWhere('field', 'ssn');

        $this->assertEquals('incomplete', $ssn['status']);
        $this->assertNotNull($ssn['message']);
        $this->assertStringContainsString('SSN', $ssn['message']);
    }

    public function test_usa_empty_ssn_is_incomplete(): void
    {
        $this->cacheUsaEmployee(1, ['ssn' => '']);

        $result = $this->checklistService->evaluate('USA');

        $checklist = collect($result['employees'][0]['checklist']);
        $ssn = $checklist->firstWhere('field', 'ssn');
        $this->assertEquals('incomplete', $ssn['status']);
    }

    public function test_usa_salary_zero_is_incomplete(): void
    {
        $this->cacheUsaEmployee(1, ['salary' => 0]);

        $result = $this->checklistService->evaluate('USA');

        $checklist = collect($result['employees'][0]['checklist']);
        $salary = $checklist->firstWhere('field', 'salary');

        $this->assertEquals('incomplete', $salary['status']);
        $this->assertStringContainsString('greater than 0', $salary['message']);
    }

    public function test_usa_salary_null_is_incomplete(): void
    {
        $this->cacheUsaEmployee(1, ['salary' => null]);

        $result = $this->checklistService->evaluate('USA');

        $checklist = collect($result['employees'][0]['checklist']);
        $salary = $checklist->firstWhere('field', 'salary');
        $this->assertEquals('incomplete', $salary['status']);
    }

    public function test_usa_empty_address_is_incomplete(): void
    {
        $this->cacheUsaEmployee(1, ['address' => '']);

        $result = $this->checklistService->evaluate('USA');

        $checklist = collect($result['employees'][0]['checklist']);
        $address = $checklist->firstWhere('field', 'address');

        $this->assertEquals('incomplete', $address['status']);
        $this->assertStringContainsString('Address', $address['message']);
    }

    // ── Germany checklist rules ─────────────────────────────────────────

    public function test_germany_complete_employee_has_100_percent(): void
    {
        $this->cacheGermanyEmployee(1);

        $result = $this->checklistService->evaluate('Germany');

        $employee = $result['employees'][0];
        $this->assertEquals(100, $employee['overall_completion']);

        foreach ($employee['checklist'] as $item) {
            $this->assertEquals('complete', $item['status']);
        }
    }

    public function test_germany_valid_tax_id_is_complete(): void
    {
        $this->cacheGermanyEmployee(1, ['tax_id' => 'DE123456789']);

        $result = $this->checklistService->evaluate('Germany');

        $checklist = collect($result['employees'][0]['checklist']);
        $taxId = $checklist->firstWhere('field', 'tax_id');
        $this->assertEquals('complete', $taxId['status']);
    }

    public function test_germany_invalid_tax_id_format_is_incomplete(): void
    {
        $this->cacheGermanyEmployee(1, ['tax_id' => 'INVALID']);

        $result = $this->checklistService->evaluate('Germany');

        $checklist = collect($result['employees'][0]['checklist']);
        $taxId = $checklist->firstWhere('field', 'tax_id');

        $this->assertEquals('incomplete', $taxId['status']);
        $this->assertStringContainsString('DE followed by 9 digits', $taxId['message']);
    }

    public function test_germany_tax_id_too_short_is_incomplete(): void
    {
        $this->cacheGermanyEmployee(1, ['tax_id' => 'DE12345678']); // 8 digits

        $result = $this->checklistService->evaluate('Germany');

        $checklist = collect($result['employees'][0]['checklist']);
        $taxId = $checklist->firstWhere('field', 'tax_id');
        $this->assertEquals('incomplete', $taxId['status']);
    }

    public function test_germany_empty_goal_is_incomplete(): void
    {
        $this->cacheGermanyEmployee(1, ['goal' => '']);

        $result = $this->checklistService->evaluate('Germany');

        $checklist = collect($result['employees'][0]['checklist']);
        $goal = $checklist->firstWhere('field', 'goal');

        $this->assertEquals('incomplete', $goal['status']);
        $this->assertStringContainsString('Goal', $goal['message']);
    }

    public function test_germany_salary_zero_is_incomplete(): void
    {
        $this->cacheGermanyEmployee(1, ['salary' => 0]);

        $result = $this->checklistService->evaluate('Germany');

        $checklist = collect($result['employees'][0]['checklist']);
        $salary = $checklist->firstWhere('field', 'salary');
        $this->assertEquals('incomplete', $salary['status']);
    }

    // ── Completion percentage calculation ────────────────────────────────

    public function test_completion_two_of_three_fields_is_66_67_percent(): void
    {
        $this->cacheUsaEmployee(1, ['address' => '']);

        $result = $this->checklistService->evaluate('USA');

        $this->assertEquals(66.67, $result['employees'][0]['overall_completion']);
    }

    public function test_completion_one_of_three_fields_is_33_33_percent(): void
    {
        $this->cacheUsaEmployee(1, ['ssn' => null, 'address' => '']);

        $result = $this->checklistService->evaluate('USA');

        $this->assertEquals(33.33, $result['employees'][0]['overall_completion']);
    }

    public function test_completion_zero_of_three_fields_is_zero_percent(): void
    {
        $this->cacheUsaEmployee(1, ['ssn' => null, 'salary' => 0, 'address' => '']);

        $result = $this->checklistService->evaluate('USA');

        $this->assertEquals(0, $result['employees'][0]['overall_completion']);
    }

    // ── Summary structure ───────────────────────────────────────────────

    public function test_summary_counts_complete_and_incomplete_employees(): void
    {
        $this->cacheUsaEmployee(1); // complete
        $this->cacheUsaEmployee(2); // complete
        $this->cacheUsaEmployee(3, ['address' => '']); // incomplete

        $result = $this->checklistService->evaluate('USA');

        $this->assertEquals('USA', $result['summary']['country']);
        $this->assertEquals(3, $result['summary']['total_employees']);
        $this->assertEquals(2, $result['summary']['complete']);
        $this->assertEquals(1, $result['summary']['incomplete']);
        $this->assertEquals(66.67, $result['summary']['completion_rate']);
    }

    public function test_employee_entry_includes_id_name_last_name(): void
    {
        $this->cacheUsaEmployee(5, ['name' => 'Alice', 'last_name' => 'Smith']);

        $result = $this->checklistService->evaluate('USA');

        $employee = $result['employees'][0];
        $this->assertEquals(5, $employee['id']);
        $this->assertEquals('Alice', $employee['name']);
        $this->assertEquals('Smith', $employee['last_name']);
    }

    // ── Edge cases ──────────────────────────────────────────────────────

    public function test_no_employees_returns_empty_with_zero_completion(): void
    {
        $result = $this->checklistService->evaluate('USA');

        $this->assertEquals(0, $result['summary']['total_employees']);
        $this->assertEquals(0, $result['summary']['completion_rate']);
        $this->assertEmpty($result['employees']);
    }

    public function test_unknown_country_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->checklistService->evaluate('France');
    }

    // ── Cache integration ───────────────────────────────────────────────

    public function test_checklist_result_is_cached_with_country_tag(): void
    {
        $this->cacheUsaEmployee(1);

        // First call — computes
        $result1 = $this->checklistService->evaluate('USA');

        // Remove the employee from cache to prove second call uses cached result
        $this->cacheService->removeEmployee(1);

        // Second call — should return cached result, not recompute
        $result2 = $this->checklistService->evaluate('USA');

        $this->assertEquals($result1, $result2);
    }

    public function test_cache_invalidation_causes_recomputation(): void
    {
        $this->cacheUsaEmployee(1, ['address' => '']);

        // First call — caches result with incomplete address
        $result1 = $this->checklistService->evaluate('USA');
        $this->assertEquals(66.67, $result1['employees'][0]['overall_completion']);

        // Simulate employee update — invalidate cache and update employee
        $this->cacheService->invalidateCountry('USA');
        $this->cacheUsaEmployee(1, ['address' => '123 Main St']);

        // Second call — should recompute with complete address
        $result2 = $this->checklistService->evaluate('USA');
        $this->assertEquals(100, $result2['employees'][0]['overall_completion']);
    }
}
